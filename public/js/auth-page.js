// Shared by login.html and register.html.
(function () {
  const mode = document.body.dataset.mode; // "login" or "register"
  const form = document.getElementById("auth-form");
  const errorBox = document.getElementById("form-error");
  const button = document.getElementById("submit");
  const label = button.textContent;

  // Already signed in: go straight to the app.
  if (Api.token()) window.location.href = "/app.html";

  function showError(message) {
    errorBox.textContent = message;
    errorBox.classList.add("show");
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    errorBox.classList.remove("show");

    const payload = {
      email: document.getElementById("email").value.trim(),
      password: document.getElementById("password").value,
    };
    if (mode === "register") payload.name = document.getElementById("name").value.trim();

    if (!payload.email || !payload.password || (mode === "register" && !payload.name)) {
      return showError("Please fill in every field.");
    }
    if (mode === "register" && payload.password.length < 8) {
      return showError("The password must be at least 8 characters.");
    }

    button.disabled = true;
    button.textContent = "Please wait...";
    try {
      const data = await Api.post(mode === "register" ? "/register" : "/login", payload, { auth: false });
      Api.setToken(data.token);
      window.location.href = "/app.html";
    } catch (error) {
      showError(error.message);
      button.disabled = false;
      button.textContent = label;
    }
  });
})();
