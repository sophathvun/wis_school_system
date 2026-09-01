document.addEventListener("DOMContentLoaded", () => {
    const card = document.getElementById("authFlipCard");
    const loginBy = document.getElementById("loginBy");
    const identifier = document.getElementById("loginIdentifier");
    const usernameGroup = document.getElementById("usernameInputGroup");
    const emailGroup = document.getElementById("emailInputGroup");
    const usernameInput = document.getElementById("usernameIdentifier");
    const emailInput = document.getElementById("emailIdentifier");
    const title = document.getElementById("authTitle");
    const subtitle = document.getElementById("authSubtitle");
    const switchTitle = document.getElementById("switchTitle");
    const switchText = document.getElementById("switchText");
    const switchButton = document.getElementById("switchAuthMode");
    const form = document.getElementById("authLoginForm");
    const password = document.getElementById("authPassword");
    const togglePassword = document.getElementById("togglePassword");
    const themeToggle = document.getElementById("authThemeToggle");
    if (!card || !loginBy || !identifier || !usernameGroup || !emailGroup || !usernameInput || !emailInput || !title || !subtitle || !switchTitle || !switchText || !switchButton || !form || !password || !togglePassword || !themeToggle) return;

    const updateThemeToggle = () => {
        const dark = document.documentElement.getAttribute("data-bs-theme") === "dark";
        themeToggle.innerHTML = `<i class="ti ti-${dark ? "sun" : "moon"}" aria-hidden="true"></i><span>${dark ? "Light mode" : "Night mode"}</span>`;
        themeToggle.setAttribute("aria-label", dark ? "Switch to light mode" : "Switch to night mode");
        themeToggle.title = dark ? "Switch to light mode" : "Switch to night mode";
    };

    let mode = form.dataset.loginMode === "email" ? "email" : "username";

    const render = () => {
        const email = mode === "email";
        card.classList.toggle("is-email", email);
        card.classList.toggle("is-username", !email);
        usernameGroup.classList.toggle("d-none", email);
        emailGroup.classList.toggle("d-none", !email);
        title.textContent = email ? "Email Login" : "Username Login";
        subtitle.textContent = email
            ? "Sign in with your school system email."
            : "Sign in with your school system username.";
        switchTitle.textContent = email ? "Use username instead?" : "Use email instead?";
        switchText.textContent = email
            ? "Flip the form and sign in with your username."
            : "Flip the form and sign in with your email address.";
        switchButton.innerHTML = email
            ? 'Username Login <i class="ti ti-arrow-left ms-1"></i>'
            : 'Email Login <i class="ti ti-arrow-right ms-1"></i>';
        (email ? emailInput : usernameInput).focus({ preventScroll: true });
    };

    switchButton.addEventListener("click", () => {
        mode = mode === "email" ? "username" : "email";
        render();
    });
    togglePassword.addEventListener("click", () => {
        const visible = password.type === "text";
        password.type = visible ? "password" : "text";
        togglePassword.setAttribute("aria-label", visible ? "Show password" : "Hide password");
        togglePassword.title = visible ? "Show password" : "Hide password";
        togglePassword.innerHTML = `<i class="ti ti-${visible ? "eye" : "eye-off"}"></i>`;
    });
    themeToggle.addEventListener("click", () => {
        const dark = document.documentElement.getAttribute("data-bs-theme") === "dark";
        const next = dark ? "light" : "dark";
        document.documentElement.setAttribute("data-bs-theme", next);
        window.localStorage.setItem("tabler-theme", next);
        updateThemeToggle();
    });
    form.addEventListener("submit", () => {
        loginBy.value = mode;
        identifier.value = mode === "email" ? emailInput.value : usernameInput.value;
    });

    updateThemeToggle();
    render();
});
