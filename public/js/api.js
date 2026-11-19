// Small wrapper around fetch for the Flowboard API.
const Api = (() => {
  const TOKEN_KEY = "flowboard_token";

  const token = () => localStorage.getItem(TOKEN_KEY);
  const setToken = (value) => localStorage.setItem(TOKEN_KEY, value);
  const clear = () => localStorage.removeItem(TOKEN_KEY);
  const goLogin = () => { window.location.href = "/login.html"; };

  async function request(method, path, body, { auth = true } = {}) {
    const headers = { Accept: "application/json" };
    if (body !== undefined) headers["Content-Type"] = "application/json";
    if (auth && token()) headers.Authorization = "Bearer " + token();

    let response;
    try {
      response = await fetch("/api" + path, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
      });
    } catch (e) {
      const error = new Error("Cannot reach the server. Please try again.");
      error.status = 0;
      throw error;
    }

    const data = await response.json().catch(() => ({}));

    if (response.status === 401 && auth) {
      clear();
      goLogin();
    }
    if (!response.ok) {
      const error = new Error(firstError(data) || "Something went wrong.");
      error.status = response.status;
      error.data = data;
      throw error;
    }
    return data;
  }

  // The first validation message, or the general message of the response.
  function firstError(data) {
    if (data && data.errors) return Object.values(data.errors).flat()[0];
    return data && data.message;
  }

  return {
    token, setToken, clear, goLogin,
    get: (path) => request("GET", path),
    post: (path, body, options) => request("POST", path, body === undefined ? {} : body, options),
    put: (path, body) => request("PUT", path, body),
    del: (path) => request("DELETE", path),
  };
})();
