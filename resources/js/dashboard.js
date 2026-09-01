document.addEventListener("DOMContentLoaded", () => {
    const escapeHtml = (value) =>
        String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");

    const closeAllDashboardSelects = (except = null) => {
        document.querySelectorAll(".dashboard-searchable-select.is-open").forEach((combo) => {
            if (combo !== except) combo.classList.remove("is-open");
        });
    };

    document.querySelectorAll("[data-dashboard-searchable-select]").forEach((select) => {
        if (select.dataset.dashboardSearchableReady) return;
        select.dataset.dashboardSearchableReady = "1";

        const combo = document.createElement("div");
        combo.className = "dashboard-searchable-select location-combobox";

        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "dashboard-searchable-select-toggle location-combobox-toggle";
        toggle.innerHTML = `
            <span class="dashboard-searchable-select-text location-combobox-selected"></span>
            <i class="ti ti-chevron-down"></i>
        `;

        const menu = document.createElement("div");
        menu.className = "dashboard-searchable-select-menu location-combobox-menu";
        menu.innerHTML = `
            <label class="dashboard-searchable-select-search-wrap">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control dashboard-searchable-select-search location-combobox-search" placeholder="Search">
            </label>
            <div class="dashboard-searchable-select-results location-combobox-results"></div>
        `;

        select.classList.add("d-none");
        select.insertAdjacentElement("afterend", combo);
        combo.append(toggle, menu);

        const text = toggle.querySelector(".dashboard-searchable-select-text");
        const search = menu.querySelector(".dashboard-searchable-select-search");
        const results = menu.querySelector(".dashboard-searchable-select-results");
        const options = Array.from(select.options).map((option) => ({
            value: option.value,
            label: option.textContent.trim(),
        }));

        const syncText = () => {
            text.textContent = select.selectedOptions[0]?.textContent
                ?.trim()
                .replace(/\s+[—–].*/, "") || "Select";
        };

        const renderOptions = () => {
            const term = search.value.trim().toLowerCase();
            const matches = options.filter((option) => option.label.toLowerCase().includes(term));

            results.innerHTML = matches.length
                ? matches
                      .map(
                          (option) => `
                        <button type="button"
                            class="dashboard-searchable-select-option location-combobox-option ${option.value === select.value ? "is-selected" : ""}"
                            data-value="${escapeHtml(option.value)}">
                            ${escapeHtml(option.label)}
                        </button>
                    `,
                      )
                      .join("")
                : '<div class="dashboard-searchable-select-empty">No results found</div>';
        };

        toggle.addEventListener("click", () => {
            const willOpen = !combo.classList.contains("is-open");
            closeAllDashboardSelects(combo);
            combo.classList.toggle("is-open", willOpen);
            if (willOpen) {
                search.value = "";
                renderOptions();
                setTimeout(() => search.focus(), 20);
            }
        });

        search.addEventListener("input", renderOptions);

        results.addEventListener("click", (event) => {
            const option = event.target.closest("[data-value]");
            if (!option) return;
            select.value = option.dataset.value;
            syncText();
            combo.classList.remove("is-open");
            select.dispatchEvent(new Event("change", { bubbles: true }));
        });

        syncText();
        renderOptions();
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest(".dashboard-searchable-select")) closeAllDashboardSelects();
    });

    document.querySelectorAll("[data-dashboard-auto-submit]").forEach((select) => {
        if (select.dataset.dashboardAutoSubmitReady) return;
        select.dataset.dashboardAutoSubmitReady = "1";
        select.addEventListener("change", () => select.form?.requestSubmit());
    });

    const layoutDashboardMasonry = () => {
        document.querySelectorAll(".premium-dashboard-grid").forEach((grid) => {
            const items = Array.from(grid.children);
            if (window.innerWidth < 992 || !items.length) {
                grid.classList.remove("dashboard-masonry-active");
                items.forEach((item) => {
                    item.style.position = "";
                    item.style.left = "";
                    item.style.top = "";
                    item.style.width = "";
                });
                grid.style.height = "";
                return;
            }

            const styles = getComputedStyle(grid);
            const gap = parseFloat(styles.columnGap) || 0;
            const track = (grid.clientWidth - gap * 11) / 12;
            const bottoms = Array(12).fill(0);
            grid.classList.add("dashboard-masonry-active");

            items.forEach((item) => {
                const declaration = item.style.gridColumn || "1 / span 1";
                const match = declaration.match(/(\d+)\s*\/\s*span\s*(\d+)/);
                const start = Math.max(1, Math.min(12, Number(match?.[1] || 1)));
                const span = Math.max(1, Math.min(12 - start + 1, Number(match?.[2] || 1)));
                const end = start - 1 + span;
                const top = Math.max(...bottoms.slice(start - 1, end));
                const left = (start - 1) * (track + gap);
                const width = span * track + (span - 1) * gap;

                item.style.left = `${left}px`;
                item.style.top = `${top}px`;
                item.style.width = `${width}px`;
                item.style.position = "absolute";

                const height = item.offsetHeight;
                for (let column = start - 1; column < end; column += 1) bottoms[column] = top + height + gap;
            });

            grid.style.height = `${Math.max(0, Math.max(...bottoms) - gap)}px`;
        });
    };

    layoutDashboardMasonry();
    window.addEventListener("resize", layoutDashboardMasonry);
    window.setTimeout(layoutDashboardMasonry, 150);

    const pad = (value) => String(value).padStart(2, "0");
    const storageKey = "dashboardHeroStartedAt";
    let startedAt;

    try {
        startedAt = Number(sessionStorage.getItem(storageKey));
        if (!Number.isFinite(startedAt) || startedAt <= 0) {
            startedAt = Date.now();
            sessionStorage.setItem(storageKey, String(startedAt));
        }
    } catch {
        startedAt = Date.now();
    }

    const updateDashboardHeroClock = () => {
        const elapsedSeconds = Math.floor((Date.now() - startedAt) / 1000);
        const hours = Math.floor(elapsedSeconds / 3600);
        const minutes = Math.floor((elapsedSeconds % 3600) / 60);
        const seconds = elapsedSeconds % 60;
        const display = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
        document.querySelectorAll("[data-dashboard-hero-clock]").forEach((clock) => {
            clock.textContent = display;
        });
    };

    updateDashboardHeroClock();
    window.setInterval(updateDashboardHeroClock, 1000);
});
