const storedThemeFont = window.localStorage.getItem("tabler-theme-font");
const storedSidebarTheme =
    window.localStorage.getItem("tabler-sidebar-theme") || "system";
document.documentElement.setAttribute(
    "data-bs-theme-font",
    storedThemeFont || "poppins",
);
document.documentElement.setAttribute(
    "data-bs-sidebar-theme",
    storedSidebarTheme,
);

document.addEventListener("DOMContentLoaded", function () {
    var themeConfig = {
        theme: "light",
        "theme-base": "gray",
        "theme-font": "poppins",
        "theme-primary": "blue",
        "sidebar-theme": "system",
        "theme-radius": "1",
    };
    var url = new URL(window.location);
    var form = document.getElementById("offcanvasSettings");
    var resetButton = document.getElementById("reset-changes");
    var syncSidebarComponentTheme = function () {
        var sidebar = document.getElementById("appSidebar");
        if (!sidebar) return;

        var colorMode =
            document.documentElement.getAttribute("data-bs-theme") ||
            window.localStorage.getItem("tabler-theme") ||
            "light";
        var sidebarTheme =
            document.documentElement.getAttribute("data-bs-sidebar-theme") ||
            "system";
        var useLightComponents =
            sidebarTheme === "light" && colorMode !== "dark";

        sidebar.setAttribute(
            "data-bs-theme",
            useLightComponents ? "light" : "dark",
        );
    };

    syncSidebarComponentTheme();
    new MutationObserver(syncSidebarComponentTheme).observe(
        document.documentElement,
        {
            attributes: true,
            attributeFilter: ["data-bs-theme", "data-bs-sidebar-theme"],
        },
    );
    // The settings panel is not rendered on public pages such as login.
    // Tabler's theme bootstrap script already applies the saved theme there.
    if (!form || !resetButton) return;
    var checkItems = function () {
        for (var key in themeConfig) {
            var value =
                window.localStorage["tabler-" + key] || themeConfig[key];
            if (!!value) {
                var radios = form.querySelectorAll(`[name="${key}"]`);
                if (!!radios) {
                    radios.forEach((radio) => {
                        radio.checked = radio.value === value;
                    });
                }
            }
        }
    };
    form.addEventListener("change", function (event) {
        var target = event.target,
            name = target.name,
            value = target.value;
        for (var key in themeConfig) {
            if (name === key) {
                document.documentElement.setAttribute("data-bs-" + key, value);
                window.localStorage.setItem("tabler-" + key, value);
                url.searchParams.set(key, value);
            }
        }
        syncSidebarComponentTheme();
        window.history.pushState({}, "", url);
    });
    resetButton.addEventListener("click", function () {
        for (var key in themeConfig) {
            var value = themeConfig[key];
            document.documentElement.removeAttribute("data-bs-" + key);
            window.localStorage.removeItem("tabler-" + key);
            url.searchParams.delete(key);
        }
        checkItems();
        syncSidebarComponentTheme();
        window.history.pushState({}, "", url);
    });
    checkItems();
});
