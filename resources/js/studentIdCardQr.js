document.addEventListener("DOMContentLoaded", () => {
    const escapeHtml = (value) =>
        String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");

    const closeAll = (except = null) => {
        document.querySelectorAll(".student-id-searchable-select.is-open").forEach((combo) => {
            if (combo !== except) combo.classList.remove("is-open");
        });
    };

    document.querySelectorAll("[data-student-id-searchable]").forEach((select) => {
        if (select.dataset.studentIdSearchableReady) return;
        select.dataset.studentIdSearchableReady = "1";

        const combo = document.createElement("div");
        combo.className = "student-id-searchable-select";
        if (select.disabled) combo.classList.add("is-disabled");

        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "student-id-searchable-toggle";
        toggle.disabled = select.disabled;
        toggle.innerHTML = `
            <span class="student-id-searchable-text"></span>
            <i class="ti ti-chevron-down"></i>
        `;

        const menu = document.createElement("div");
        menu.className = "student-id-searchable-menu";
        menu.innerHTML = `
            <label class="student-id-searchable-search-wrap">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control student-id-searchable-search" placeholder="Search">
            </label>
            <div class="student-id-searchable-results"></div>
        `;

        select.classList.add("d-none");
        select.insertAdjacentElement("afterend", combo);
        combo.append(toggle, menu);

        const text = toggle.querySelector(".student-id-searchable-text");
        const search = menu.querySelector(".student-id-searchable-search");
        const results = menu.querySelector(".student-id-searchable-results");
        const options = Array.from(select.options).map((option) => ({
            value: option.value,
            label: option.textContent.trim(),
        }));

        const syncText = () => {
            text.textContent = select.selectedOptions[0]?.textContent?.trim() || "";
        };

        const render = () => {
            const term = search.value.trim().toLowerCase();
            const matches = options.filter((option) => option.label.toLowerCase().includes(term));
            results.innerHTML = matches.length
                ? matches
                      .map(
                          (option) => `
                            <button type="button"
                                class="student-id-searchable-option ${option.value === select.value ? "is-selected" : ""}"
                                data-value="${escapeHtml(option.value)}">
                                ${escapeHtml(option.label)}
                            </button>
                        `,
                      )
                      .join("")
                : '<div class="student-id-searchable-empty">No results found</div>';
        };

        toggle.addEventListener("click", () => {
            if (select.disabled) return;
            const willOpen = !combo.classList.contains("is-open");
            closeAll(combo);
            combo.classList.toggle("is-open", willOpen);
            if (willOpen) {
                search.value = "";
                render();
                setTimeout(() => search.focus(), 20);
            }
        });

        search.addEventListener("input", render);

        results.addEventListener("click", (event) => {
            const option = event.target.closest("[data-value]");
            if (!option) return;
            select.value = option.dataset.value;
            syncText();
            combo.classList.remove("is-open");
            select.dispatchEvent(new Event("change", { bubbles: true }));
        });

        syncText();
        render();
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest(".student-id-searchable-select")) closeAll();
    });

    document.querySelectorAll("[data-student-id-auto-submit]").forEach((select) => {
        if (select.dataset.studentIdAutoSubmitReady) return;
        select.dataset.studentIdAutoSubmitReady = "1";
        select.addEventListener("change", () => select.form?.requestSubmit());
    });

});
