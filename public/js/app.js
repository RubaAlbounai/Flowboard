// The Flowboard app: projects in the sidebar, tasks on a board or in a list.
(function () {
  "use strict";

  if (!Api.token()) { Api.goLogin(); return; }

  const STATUSES = [
    { key: "pending", label: "To do" },
    { key: "in-progress", label: "In progress" },
    { key: "done", label: "Done" },
  ];
  const PRIORITY_LABEL = { low: "Low", normal: "Normal", high: "High", urgent: "Urgent" };
  const COLORS = ["#6d5dfc", "#3b82f6", "#22b07d", "#f59e0b", "#ef4444", "#ec4899", "#14b8a6", "#64748b"];

  const $ = (selector) => document.querySelector(selector);
  const esc = (value) =>
    String(value ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  const state = {
    user: null,
    projects: [],
    projectId: Number(localStorage.getItem("flowboard_project")) || null,
    tasks: [],
    view: localStorage.getItem("flowboard_view") === "list" ? "list" : "board",
    search: "",
    priority: "",
    dragId: null,
    editingTaskId: null,
    editingProjectId: null,
    chosenColor: COLORS[0],
  };

  const currentProject = () => state.projects.find((p) => p.id === state.projectId) || null;

  /* ---------------- helpers ---------------- */
  let toastTimer;
  function toast(message) {
    let el = $("#toast");
    if (!el) {
      el = document.createElement("div");
      el.id = "toast";
      el.className = "toast";
      el.setAttribute("role", "status");
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove("show"), 2400);
  }

  const todayString = () => {
    const d = new Date();
    return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, "0"), String(d.getDate()).padStart(2, "0")].join("-");
  };

  function dueChip(task) {
    if (!task.due_date) return "";
    const [y, m, d] = task.due_date.split("-").map(Number);
    const text = new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric" }).format(new Date(y, m - 1, d));
    let cls = "";
    if (task.status !== "done") {
      const today = todayString();
      if (task.due_date < today) cls = " overdue";
      else {
        const soon = new Date(); soon.setDate(soon.getDate() + 2);
        const soonString = [soon.getFullYear(), String(soon.getMonth() + 1).padStart(2, "0"), String(soon.getDate()).padStart(2, "0")].join("-");
        if (task.due_date <= soonString) cls = " soon";
      }
    }
    return `<span class="chip due${cls}">${text}</span>`;
  }

  const priorityChip = (task) =>
    `<span class="chip prio-${esc(task.priority)}">${PRIORITY_LABEL[task.priority] || esc(task.priority)}</span>`;

  function visibleTasks() {
    const q = state.search.trim().toLowerCase();
    return state.tasks.filter(
      (t) => (!state.priority || t.priority === state.priority) && (!q || t.title.toLowerCase().includes(q))
    );
  }

  const column = (status) =>
    visibleTasks().filter((t) => t.status === status).sort((a, b) => a.position - b.position || a.id - b.id);

  function open(modal) { modal.classList.add("open"); }
  function close(modal) { modal.classList.remove("open"); }
  function showFormError(box, message) { box.textContent = message; box.classList.add("show"); }

  /* ---------------- loading ---------------- */
  async function loadProjects() {
    state.projects = await Api.get("/projects");
    if (!currentProject()) state.projectId = state.projects.length ? state.projects[0].id : null;
    if (state.projectId) localStorage.setItem("flowboard_project", state.projectId);
  }

  async function loadTasks() {
    state.tasks = state.projectId ? await Api.get(`/projects/${state.projectId}/tasks`) : [];
  }

  async function refreshAll() {
    await loadProjects();
    await loadTasks();
    render();
  }

  /* ---------------- rendering ---------------- */
  function render() {
    renderSidebar();
    renderTopbar();
    renderContent();
  }

  function renderSidebar() {
    const list = $("#project-list");
    list.innerHTML = state.projects.length
      ? state.projects.map((p) => `
          <button class="project-item${p.id === state.projectId ? " active" : ""}" type="button" data-project="${p.id}">
            <span class="dot" style="background:${esc(p.color)}"></span>
            <span class="name">${esc(p.name)}</span>
            <span class="num">${p.done_count ?? 0}/${p.tasks_count ?? 0}</span>
          </button>`).join("")
      : '<div class="side-empty">No projects yet</div>';

    $("#who").textContent = state.user ? state.user.name : "";
    $("#avatar").textContent = state.user ? state.user.name.trim().charAt(0).toUpperCase() : "";
  }

  function renderTopbar() {
    const project = currentProject();
    const hasProject = Boolean(project);

    $("#project-title").innerHTML = hasProject
      ? `<span class="dot" style="background:${esc(project.color)}"></span>${esc(project.name)}`
      : "Welcome";

    const total = state.tasks.length;
    const done = state.tasks.filter((t) => t.status === "done").length;
    $("#progress").innerHTML = hasProject && total
      ? `<span class="bar"><i style="width:${Math.round((done / total) * 100)}%"></i></span><span>${done} of ${total} done</span>`
      : "";

    document.querySelectorAll("[data-view]").forEach((b) => b.classList.toggle("active", b.dataset.view === state.view));
    ["#new-task", "#edit-project", "#search", "#priority-filter"].forEach((s) => { $(s).style.display = hasProject ? "" : "none"; });
    $(".seg").style.display = hasProject ? "" : "none";
  }

  function renderContent() {
    const content = $("#content");
    const project = currentProject();

    if (!project) {
      content.innerHTML = `
        <div class="empty">
          <h2>Create your first project</h2>
          <p>A project holds your tasks. Give it a name and start adding work to the board.</p>
          <button class="btn btn-primary btn-lg" type="button" data-action="new-project">New project</button>
        </div>`;
      return;
    }

    const description = project.description ? `<p class="desc">${esc(project.description)}</p>` : "";
    content.innerHTML = description + (state.view === "board" ? boardHtml() : listHtml());
  }

  function boardHtml() {
    return `<div class="board">${STATUSES.map(({ key, label }) => {
      const tasks = column(key);
      return `
        <section class="column" data-status="${key}">
          <header class="column-head"><span class="dot s-${key}"></span><h3>${label}</h3><span class="count">${tasks.length}</span></header>
          <div class="column-body" data-status="${key}">
            ${tasks.map(cardHtml).join("") || '<div class="col-empty">Nothing here yet</div>'}
          </div>
          <form class="quick-add" data-status="${key}">
            <input type="text" maxlength="255" placeholder="+ Add a task" aria-label="Add a task to ${label}">
          </form>
        </section>`;
    }).join("")}</div>`;
  }

  function cardHtml(task) {
    return `
      <article class="card${task.status === "done" ? " is-done" : ""}" draggable="true" data-id="${task.id}">
        <div class="card-title">${esc(task.title)}</div>
        <div class="card-meta">${priorityChip(task)}${dueChip(task)}</div>
      </article>`;
  }

  function listHtml() {
    const rows = STATUSES.flatMap(({ key }) => column(key));
    if (!rows.length) return '<div class="empty"><h2>No tasks to show</h2><p>Add a task with the New task button, or clear the filters.</p></div>';

    return `
      <div class="table-wrap">
        <table class="list">
          <thead><tr><th>Task</th><th>Status</th><th>Priority</th><th>Due</th></tr></thead>
          <tbody>
            ${rows.map((t) => `
              <tr data-id="${t.id}"${t.status === "done" ? ' class="is-done"' : ""}>
                <td class="t">${esc(t.title)}</td>
                <td><select class="status-select" data-status-for="${t.id}" aria-label="Status of ${esc(t.title)}">
                  ${STATUSES.map((s) => `<option value="${s.key}"${s.key === t.status ? " selected" : ""}>${s.label}</option>`).join("")}
                </select></td>
                <td>${priorityChip(t)}</td>
                <td>${dueChip(t) || '<span style="color:var(--muted)">-</span>'}</td>
              </tr>`).join("")}
          </tbody>
        </table>
      </div>`;
  }

  /* ---------------- tasks ---------------- */
  async function quickAdd(status, title) {
    try {
      await Api.post(`/projects/${state.projectId}/tasks`, { title, status });
      await refreshAll();
    } catch (error) {
      toast(error.message);
    }
  }

  function openTaskModal(task, status) {
    state.editingTaskId = task ? task.id : null;
    $("#task-modal-title").textContent = task ? "Edit task" : "New task";
    $("#t-title").value = task ? task.title : "";
    $("#t-desc").value = task ? task.description || "" : "";
    $("#t-status").value = task ? task.status : status || "pending";
    $("#t-priority").value = task ? task.priority : "normal";
    $("#t-due").value = task && task.due_date ? task.due_date : "";
    $("#t-delete").style.display = task ? "" : "none";
    $("#task-error").classList.remove("show");
    open($("#task-modal"));
    $("#t-title").focus();
  }

  async function saveTask(event) {
    event.preventDefault();
    const payload = {
      title: $("#t-title").value.trim(),
      description: $("#t-desc").value.trim() || null,
      status: $("#t-status").value,
      priority: $("#t-priority").value,
      due_date: $("#t-due").value || null,
    };
    if (!payload.title) return showFormError($("#task-error"), "Please give the task a title.");

    const button = $("#t-save");
    button.disabled = true;
    try {
      if (state.editingTaskId) await Api.put(`/tasks/${state.editingTaskId}`, payload);
      else await Api.post(`/projects/${state.projectId}/tasks`, payload);
      close($("#task-modal"));
      await refreshAll();
    } catch (error) {
      showFormError($("#task-error"), error.message);
    }
    button.disabled = false;
  }

  async function deleteTask() {
    if (!state.editingTaskId || !confirm("Delete this task?")) return;
    try {
      await Api.del(`/tasks/${state.editingTaskId}`);
      close($("#task-modal"));
      await refreshAll();
      toast("Task deleted");
    } catch (error) {
      toast(error.message);
    }
  }

  /* ---------------- drag and drop ---------------- */
  // Where would a card dropped at this height end up in the column?
  function dropIndex(body, clientY) {
    const cards = [...body.querySelectorAll(".card:not(.dragging)")];
    let index = 0;
    cards.forEach((card) => {
      const box = card.getBoundingClientRect();
      if (clientY > box.top + box.height / 2) index += 1;
    });
    return index;
  }

  function moveLocally(id, status, index) {
    const task = state.tasks.find((t) => t.id === id);
    const oldStatus = task.status;
    const others = (key) => state.tasks.filter((t) => t.status === key && t.id !== id).sort((a, b) => a.position - b.position || a.id - b.id);

    const target = others(status);
    target.splice(Math.min(index, target.length), 0, task);
    task.status = status;
    target.forEach((t, i) => { t.position = i; });
    if (oldStatus !== status) others(oldStatus).forEach((t, i) => { t.position = i; });
  }

  async function moveTask(id, status, index) {
    moveLocally(id, status, index);
    render();
    try {
      await Api.post(`/tasks/${id}/move`, { status, position: index });
      await loadProjects();
      renderSidebar();
    } catch (error) {
      toast(error.message);
      await refreshAll(); // put the board back the way the server has it
    }
  }

  function clearDropMarks() {
    document.querySelectorAll(".drag-over").forEach((el) => el.classList.remove("drag-over"));
    document.querySelectorAll(".drop-line").forEach((el) => el.remove());
  }

  /* ---------------- projects ---------------- */
  function renderSwatches() {
    $("#swatches").innerHTML = COLORS.map(
      (c) => `<button type="button" class="swatch${c === state.chosenColor ? " selected" : ""}" data-color="${c}" style="background:${c}" aria-label="Colour ${c}"></button>`
    ).join("");
  }

  function openProjectModal(project) {
    state.editingProjectId = project ? project.id : null;
    state.chosenColor = project ? project.color : COLORS[0];
    $("#project-modal-title").textContent = project ? "Edit project" : "New project";
    $("#p-name").value = project ? project.name : "";
    $("#p-desc").value = project ? project.description || "" : "";
    $("#p-delete").style.display = project ? "" : "none";
    $("#project-error").classList.remove("show");
    renderSwatches();
    open($("#project-modal"));
    $("#p-name").focus();
  }

  async function saveProject(event) {
    event.preventDefault();
    const payload = { name: $("#p-name").value.trim(), description: $("#p-desc").value.trim() || null, color: state.chosenColor };
    if (!payload.name) return showFormError($("#project-error"), "Please give the project a name.");

    try {
      if (state.editingProjectId) {
        await Api.put(`/projects/${state.editingProjectId}`, payload);
      } else {
        const created = await Api.post("/projects", payload);
        state.projectId = created.id;
        localStorage.setItem("flowboard_project", created.id);
      }
      close($("#project-modal"));
      await refreshAll();
    } catch (error) {
      showFormError($("#project-error"), error.message);
    }
  }

  async function deleteProject() {
    const project = state.projects.find((p) => p.id === state.editingProjectId);
    if (!project || !confirm(`Delete "${project.name}" and all of its tasks?`)) return;
    try {
      await Api.del(`/projects/${project.id}`);
      state.projectId = null;
      close($("#project-modal"));
      await refreshAll();
      toast("Project deleted");
    } catch (error) {
      toast(error.message);
    }
  }

  async function selectProject(id) {
    state.projectId = id;
    localStorage.setItem("flowboard_project", id);
    $("#sidebar").classList.remove("open");
    await loadTasks();
    render();
  }

  /* ---------------- events ---------------- */
  document.addEventListener("click", async (event) => {
    const t = event.target;

    const projectBtn = t.closest("[data-project]");
    if (projectBtn) return selectProject(Number(projectBtn.dataset.project));

    if (t.closest("[data-action='new-project']") || t.closest("#new-project")) return openProjectModal(null);
    if (t.closest("#edit-project")) return openProjectModal(currentProject());
    if (t.closest("#new-task")) return openTaskModal(null, "pending");

    const viewBtn = t.closest("[data-view]");
    if (viewBtn) {
      state.view = viewBtn.dataset.view;
      localStorage.setItem("flowboard_view", state.view);
      return render();
    }

    const swatch = t.closest("[data-color]");
    if (swatch) { state.chosenColor = swatch.dataset.color; return renderSwatches(); }

    if (t.closest("[data-close]")) return close(t.closest(".modal"));
    if (t.classList.contains("modal")) return close(t);

    if (t.closest("#menu-btn")) return $("#sidebar").classList.toggle("open");

    if (t.closest("#logout")) {
      try { await Api.post("/logout"); } catch (e) { /* token may already be gone */ }
      Api.clear();
      return Api.goLogin();
    }

    // open a task from a card or a list row (but not when using the status select)
    if (t.closest(".status-select") || t.closest(".quick-add")) return;
    const target = t.closest(".card, tr[data-id]");
    if (target) {
      const task = state.tasks.find((x) => x.id === Number(target.dataset.id));
      if (task) openTaskModal(task);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") document.querySelectorAll(".modal.open").forEach(close);
  });

  document.addEventListener("submit", (event) => {
    const quick = event.target.closest(".quick-add");
    if (!quick) return;
    event.preventDefault();
    const input = quick.querySelector("input");
    const title = input.value.trim();
    if (title) quickAdd(quick.dataset.status, title);
  });

  document.addEventListener("change", async (event) => {
    const select = event.target.closest("[data-status-for]");
    if (!select) return;
    try {
      await Api.put(`/tasks/${select.dataset.statusFor}`, { status: select.value });
      await refreshAll();
    } catch (error) {
      toast(error.message);
      await refreshAll();
    }
  });

  $("#search").addEventListener("input", (e) => { state.search = e.target.value; renderContent(); });
  $("#priority-filter").addEventListener("change", (e) => { state.priority = e.target.value; renderContent(); });
  $("#task-form").addEventListener("submit", saveTask);
  $("#t-delete").addEventListener("click", deleteTask);
  $("#project-form").addEventListener("submit", saveProject);
  $("#p-delete").addEventListener("click", deleteProject);

  // drag and drop (events bubble, so one set of listeners covers every card)
  document.addEventListener("dragstart", (event) => {
    const card = event.target.closest && event.target.closest(".card");
    if (!card) return;
    state.dragId = Number(card.dataset.id);
    card.classList.add("dragging");
    if (event.dataTransfer) { event.dataTransfer.effectAllowed = "move"; event.dataTransfer.setData("text/plain", String(state.dragId)); }
  });
  document.addEventListener("dragend", () => { state.dragId = null; clearDropMarks(); document.querySelectorAll(".dragging").forEach((c) => c.classList.remove("dragging")); });
  document.addEventListener("dragover", (event) => {
    const body = event.target.closest && event.target.closest(".column-body");
    if (!body || state.dragId === null) return;
    event.preventDefault();
    clearDropMarks();
    body.closest(".column").classList.add("drag-over");
    const cards = [...body.querySelectorAll(".card:not(.dragging)")];
    const line = document.createElement("div");
    line.className = "drop-line";
    const index = dropIndex(body, event.clientY);
    if (cards[index]) body.insertBefore(line, cards[index]); else body.appendChild(line);
  });
  document.addEventListener("drop", (event) => {
    const body = event.target.closest && event.target.closest(".column-body");
    if (!body || state.dragId === null) return;
    event.preventDefault();
    const index = dropIndex(body, event.clientY);
    const id = state.dragId;
    state.dragId = null;
    clearDropMarks();
    moveTask(id, body.dataset.status, index);
  });

  /* ---------------- start ---------------- */
  (async function start() {
    try {
      state.user = await Api.get("/user");
      await loadProjects();
      await loadTasks();
      render();
    } catch (error) {
      if (error.status !== 401) {
        $("#content").innerHTML = '<div class="empty"><h2>We could not load your projects</h2><p>Check that the server is running and refresh the page.</p></div>';
      }
    }
  })();

  window.Flowboard = { moveTask, state }; // handy for debugging in the browser console
})();
