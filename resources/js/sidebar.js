// Handle navbar toggler and menu closing
document.addEventListener("DOMContentLoaded", function () {
    const toggler = document.querySelector(".navbar-toggler");
    const sidebarMenu = document.getElementById("sidebar-menu");
    const navLinks = document.querySelectorAll(".navbar-nav .nav-link");

    if (!toggler || !sidebarMenu) return;

    // Function to toggle sidebar
    function toggleSidebar() {
        const isExpanded = toggler.getAttribute("aria-expanded") === "true";
        if (isExpanded) {
            // Close the menu
            const collapseInstance =
                bootstrap.Collapse.getInstance(sidebarMenu) ||
                new bootstrap.Collapse(sidebarMenu);
            collapseInstance.hide();
        } else {
            // Open the menu
            const collapseInstance =
                bootstrap.Collapse.getInstance(sidebarMenu) ||
                new bootstrap.Collapse(sidebarMenu);
            collapseInstance.show();
        }
    }

    // Handle toggle button click
    toggler.addEventListener("click", function (e) {
        e.preventDefault();
        toggleSidebar();
    });

    // Update aria-expanded when collapse state changes
    sidebarMenu.addEventListener("show.bs.collapse", function () {
        toggler.setAttribute("aria-expanded", "true");
    });

    sidebarMenu.addEventListener("hide.bs.collapse", function () {
        toggler.setAttribute("aria-expanded", "false");
    });

    // Close sidebar menu when clicking on navigation links (mobile only)
    navLinks.forEach((link) => {
        link.addEventListener("click", function () {
            if (window.innerWidth < 992) {
                const collapseInstance =
                    bootstrap.Collapse.getInstance(sidebarMenu) ||
                    new bootstrap.Collapse(sidebarMenu);
                collapseInstance.hide();
            }
        });
    });

    const sidebarSearchInput = document.getElementById("sidebar-search");
    const sidebarNavItems = sidebarMenu.querySelectorAll(
        ".navbar-nav > .nav-item",
    );
    const sidebarFavoritesList = document.getElementById(
        "sidebar-favorites-list",
    );
    const sidebarNoFavorites = document.getElementById("sidebar-no-favorites");
    const FAVORITES_STORAGE_KEY = "sidebarFavorites";

    const normalizeText = (text) => text.trim().toLowerCase();
    const matchesQuery = (text, query) =>
        normalizeText(text).includes(normalizeText(query));

    const getMenuText = (element) => {
        if (!element) return "";
        if (element.dataset.sidebarLabel) {
            return element.dataset.sidebarLabel.trim();
        }

        const textFragments = [];
        element.childNodes.forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                textFragments.push(node.textContent);
            } else if (
                node.nodeType === Node.ELEMENT_NODE &&
                !node.matches("button, .sidebar-favorite-toggle, .ti")
            ) {
                textFragments.push(node.textContent);
            }
        });

        return textFragments.join(" ").replace(/\s+/g, " ").trim();
    };

    const loadFavorites = () => {
        try {
            return (
                JSON.parse(localStorage.getItem(FAVORITES_STORAGE_KEY)) || []
            );
        } catch (error) {
            return [];
        }
    };

    const saveFavorites = (favorites) => {
        localStorage.setItem(FAVORITES_STORAGE_KEY, JSON.stringify(favorites));
    };

    let sidebarFavorites = loadFavorites();

    const isFavorite = (href) =>
        sidebarFavorites.some((favorite) => favorite.href === href);

    const updateFavoriteButtons = () => {
        const buttons = sidebarMenu.querySelectorAll(
            ".sidebar-favorite-toggle",
        );
        buttons.forEach((button) => {
            const href = button.dataset.href;
            const icon = button.querySelector("i");
            if (!href || !icon) return;
            const favorite = isFavorite(href);
            icon.classList.toggle("text-warning", favorite);
            icon.classList.toggle("text-muted", !favorite);
            button.setAttribute("aria-pressed", favorite ? "true" : "false");
        });
    };

    const renderSidebarFavorites = () => {
        if (!sidebarFavoritesList || !sidebarNoFavorites) return;

        sidebarFavoritesList.innerHTML = "";

        if (sidebarFavorites.length === 0) {
            sidebarNoFavorites.style.display = "block";
            return;
        }

        sidebarNoFavorites.style.display = "none";

        sidebarFavorites.forEach((favorite) => {
            const listItem = document.createElement("li");
            listItem.className =
                "d-flex align-items-center justify-content-between mb-1";

            const link = document.createElement("a");
            link.href = favorite.href;
            link.className = "text-body text-decoration-none flex-grow-1 me-2";
            link.textContent = favorite.label;

            // Move up button
            const moveUpBtn = document.createElement("button");
            moveUpBtn.type = "button";
            moveUpBtn.className = "btn btn-sm btn-icon p-0 text-secondary me-2";
            moveUpBtn.setAttribute("aria-label", "Move up");
            moveUpBtn.innerHTML = "<i class='ti ti-arrow-narrow-up'></i>";

            // Move down button
            const moveDownBtn = document.createElement("button");
            moveDownBtn.type = "button";
            moveDownBtn.className =
                "btn btn-sm btn-icon p-0 text-secondary me-2";
            moveDownBtn.setAttribute("aria-label", "Move down");
            moveDownBtn.innerHTML = "<i class='ti ti-arrow-narrow-down'></i>";

            const removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "btn btn-sm btn-icon p-0 text-danger";
            removeBtn.setAttribute("aria-label", "Remove favorite");
            removeBtn.innerHTML = "<i class='ti ti-x'></i>";

            removeBtn.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                sidebarFavorites = sidebarFavorites.filter(
                    (item) => item.href !== favorite.href,
                );
                saveFavorites(sidebarFavorites);
                renderSidebarFavorites();
                updateFavoriteButtons();
            });

            const index = sidebarFavorites.findIndex(
                (item) => item.href === favorite.href,
            );

            moveUpBtn.disabled = index <= 0;
            moveDownBtn.disabled =
                index === -1 || index >= sidebarFavorites.length - 1;

            moveUpBtn.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                const i = sidebarFavorites.findIndex(
                    (it) => it.href === favorite.href,
                );
                if (i > 0) {
                    const tmp = sidebarFavorites[i - 1];
                    sidebarFavorites[i - 1] = sidebarFavorites[i];
                    sidebarFavorites[i] = tmp;
                    saveFavorites(sidebarFavorites);
                    renderSidebarFavorites();
                    updateFavoriteButtons();
                }
            });

            moveDownBtn.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                const i = sidebarFavorites.findIndex(
                    (it) => it.href === favorite.href,
                );
                if (i > -1 && i < sidebarFavorites.length - 1) {
                    const tmp = sidebarFavorites[i + 1];
                    sidebarFavorites[i + 1] = sidebarFavorites[i];
                    sidebarFavorites[i] = tmp;
                    saveFavorites(sidebarFavorites);
                    renderSidebarFavorites();
                    updateFavoriteButtons();
                }
            });

            listItem.appendChild(link);
            // group controls to the right
            const controls = document.createElement("div");
            controls.className = "d-flex align-items-center";
            controls.appendChild(moveUpBtn);
            controls.appendChild(moveDownBtn);
            controls.appendChild(removeBtn);
            listItem.appendChild(controls);
            sidebarFavoritesList.appendChild(listItem);
            // Enable drag-and-drop reordering
            listItem.draggable = true;
            listItem.dataset.href = favorite.href;

            listItem.addEventListener("dragstart", (e) => {
                e.dataTransfer.effectAllowed = "move";
                e.dataTransfer.setData("text/plain", favorite.href);
                listItem.classList.add("dragging");
            });

            listItem.addEventListener("dragend", (e) => {
                listItem.classList.remove("dragging");
                const items = sidebarFavoritesList.querySelectorAll("li");
                items.forEach((it) => it.classList.remove("drag-over"));
            });

            listItem.addEventListener("dragover", (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = "move";
                listItem.classList.add("drag-over");
            });

            listItem.addEventListener("dragleave", (e) => {
                listItem.classList.remove("drag-over");
            });

            listItem.addEventListener("drop", (e) => {
                e.preventDefault();
                const href = e.dataTransfer.getData("text/plain");
                if (!href) return;
                const fromIndex = sidebarFavorites.findIndex(
                    (it) => it.href === href,
                );
                const toIndex = sidebarFavorites.findIndex(
                    (it) => it.href === favorite.href,
                );
                if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex)
                    return;
                const item = sidebarFavorites.splice(fromIndex, 1)[0];
                sidebarFavorites.splice(toIndex, 0, item);
                saveFavorites(sidebarFavorites);
                renderSidebarFavorites();
                updateFavoriteButtons();
            });
        });
    };

    const toggleFavorite = (href, label) => {
        if (!href) return;

        const alreadyFavorite = isFavorite(href);

        if (alreadyFavorite) {
            sidebarFavorites = sidebarFavorites.filter(
                (item) => item.href !== href,
            );
        } else {
            sidebarFavorites.push({ href, label });
        }

        saveFavorites(sidebarFavorites);
        renderSidebarFavorites();
        updateFavoriteButtons();
    };

    const createFavoriteToggle = (link) => {
        if (!link || link.dataset.sidebarFavoriteAttached || !link.href) return;
        const href = link.href;
        if (href === "#" || href === "javascript:void(0)") return;

        const label = getMenuText(link);
        link.dataset.sidebarLabel = label;

        const button = document.createElement("button");
        button.type = "button";
        button.className =
            "btn btn-icon btn-sm p-0 sidebar-favorite-toggle ms-auto";
        button.dataset.href = href;
        button.setAttribute("aria-label", "Toggle favorite");
        button.innerHTML = "<i class='ti ti-heart text-muted'></i>";

        button.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            toggleFavorite(href, label);
        });

        link.classList.add("d-flex", "align-items-center");
        link.appendChild(button);
        link.dataset.sidebarFavoriteAttached = "true";
    };

    const prepareFavoriteToggles = () => {
        const targetLinks = sidebarMenu.querySelectorAll(
            ".nav-link[href]:not([href='#']), .dropdown-item[href]:not([href='#'])",
        );
        targetLinks.forEach(createFavoriteToggle);
        updateFavoriteButtons();
    };

    const filterSidebarMenu = (query) => {
        const normalizedQuery = query.trim().toLowerCase();

        sidebarNavItems.forEach((navItem) => {
            const navLink = navItem.querySelector(".nav-link");
            const mainText = getMenuText(navLink);
            let itemMatches =
                normalizedQuery === "" ||
                matchesQuery(mainText, normalizedQuery);

            const dropdownMenu = navItem.querySelector(".dropdown-menu");
            if (dropdownMenu) {
                const dropdownItems =
                    dropdownMenu.querySelectorAll(".dropdown-item");
                const dropdownColumns = dropdownMenu.querySelectorAll(
                    ".dropdown-menu-column",
                );
                let anyChildMatches = false;

                dropdownItems.forEach((dropdownItem) => {
                    const itemText = getMenuText(dropdownItem);
                    const matched =
                        normalizedQuery === "" ||
                        matchesQuery(itemText, normalizedQuery);
                    if (matched) {
                        // remove any forced-hide
                        dropdownItem.style.removeProperty("display");
                        anyChildMatches = true;
                    } else {
                        // force hide with !important to override CSS
                        dropdownItem.style.setProperty(
                            "display",
                            "none",
                            "important",
                        );
                    }
                });

                dropdownColumns.forEach((column) => {
                    const visibleChild = column.querySelector(
                        ".dropdown-item:not([style*='display: none'])",
                    );
                    column.style.display = visibleChild ? "" : "none";
                });

                if (normalizedQuery !== "") {
                    dropdownMenu.classList.toggle("show", anyChildMatches);
                } else {
                    dropdownMenu.classList.remove("show");
                    dropdownItems.forEach((dropdownItem) => {
                        dropdownItem.style.removeProperty("display");
                    });
                    dropdownColumns.forEach((column) => {
                        column.style.removeProperty("display");
                    });
                }

                if (anyChildMatches) {
                    itemMatches = true;
                }
            }

            if (itemMatches) {
                navItem.style.removeProperty("display");
            } else {
                navItem.style.setProperty("display", "none", "important");
            }
        });
    };

    if (sidebarSearchInput) {
        sidebarSearchInput.addEventListener("input", (event) => {
            filterSidebarMenu(event.target.value);
        });
    }

    renderSidebarFavorites();
    prepareFavoriteToggles();
});
