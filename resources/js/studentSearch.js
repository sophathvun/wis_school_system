const page = document.getElementById("studentSearchPage");

if (page) {
    document.addEventListener("DOMContentLoaded", () => {
        const year = document.getElementById("search-year");
        const campus = document.getElementById("search-campus");
        const cls = document.getElementById("search-class");
        const group = document.getElementById("search-group");
        const text = document.getElementById("search-text");
        const results = document.getElementById("search-results");
        const summary = document.getElementById("search-summary");
        const pagination = document.getElementById("search-pagination");
        const optionsUrl = page.dataset.optionsUrl;
        const fetchUrl = page.dataset.fetchUrl;
        const esc = (value) =>
            String(value ?? "").replace(/[&<>"']/g, (ch) => ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;",
            }[ch]));
        const optionText = (item) =>
            item.campus_name_en
                ? item.campus_name_en
                : item.class_name || item.group_name || item.academic_year;
        const combos = {};

        const fill = (select, items, empty) => {
            select.innerHTML =
                `<option value="">${empty}</option>` +
                items
                    .map(
                        (item) =>
                            `<option value="${esc(item.id)}">${esc(optionText(item))}</option>`,
                    )
                    .join("");
        };

        const makeSearchable = (select, placeholder) => {
            select.classList.add("d-none");
            const wrapper = document.createElement("div");
            wrapper.className = "location-combobox";
            wrapper.innerHTML = `<button type="button" class="location-combobox-toggle"><span class="location-combobox-selected">${placeholder}</span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search"><div class="location-combobox-results"></div></div>`;
            select.after(wrapper);
            const combo = (combos[select.id] = {
                select,
                menu: wrapper.querySelector(".location-combobox-menu"),
                input: wrapper.querySelector(".location-combobox-search"),
                results: wrapper.querySelector(".location-combobox-results"),
                label: wrapper.querySelector(".location-combobox-selected"),
                placeholder,
            });
            const render = () => {
                const term = combo.input.value.toLowerCase();
                const opts = Array.from(select.options)
                    .slice(1)
                    .filter((option) => option.text.toLowerCase().includes(term));
                combo.results.innerHTML =
                    `<button type="button" class="location-combobox-option" data-value="">${combo.placeholder}</button>` +
                    (opts.length
                        ? opts
                              .map(
                                  (option) =>
                                      `<button type="button" class="location-combobox-option" data-value="${esc(option.value)}">${esc(option.text)}</button>`,
                              )
                              .join("")
                        : '<div class="text-secondary px-2 py-2">No results found</div>');
                combo.results.querySelectorAll("[data-value]").forEach((btn) => {
                    btn.onclick = () => {
                        select.value = btn.dataset.value;
                        select.dispatchEvent(new Event("change"));
                        combo.menu.classList.add("d-none");
                    };
                });
            };
            wrapper.querySelector(".location-combobox-toggle").onclick = () => {
                Object.values(combos).forEach((other) => {
                    if (other !== combo) other.menu.classList.add("d-none");
                });
                combo.menu.classList.toggle("d-none");
                combo.input.value = "";
                render();
                combo.input.focus();
            };
            combo.input.oninput = render;
            select.addEventListener("change", () => {
                const selected = select.options[select.selectedIndex];
                combo.label.textContent = selected?.value
                    ? selected.text
                    : combo.placeholder;
            });
        };

        const loadOptions = async () => {
            const params = new URLSearchParams();
            if (year.value) params.set("academic_year_id", year.value);
            if (campus.value) params.set("campus_id", campus.value);
            if (cls.value) params.set("class_id", cls.value);
            const data = await fetch(`${optionsUrl}?${params}`).then((r) => r.json());
            const selectedYear = year.value;
            const selectedClass = cls.value;
            const selectedGroup = group.value;
            fill(year, data.academicYears || [], "All Academic Years");
            fill(campus, data.campuses || [], "All Campuses");
            fill(cls, data.classes || [], "All Grades / Classes");
            year.value = selectedYear;
            cls.value = selectedClass;
            fill(group, data.groups || [], "All Groups");
            group.value = selectedGroup;
        };

        const queryUrl = (pageNumber) => {
            const params = new URLSearchParams({
                page: pageNumber || 1,
                perPage: 10,
            });
            if (year.value) params.set("academic_year_id", year.value);
            if (campus.value) params.set("campus_id", campus.value);
            if (cls.value) params.set("class_id", cls.value);
            if (group.value) params.set("group_id", group.value);
            if (text.value.trim()) params.set("search", text.value.trim());
            return `${fetchUrl}?${params}`;
        };

        const render = (data) => {
            results.innerHTML = data.data?.length
                ? data.data
                      .map((item) => {
                          const student = item.student || {};
                          const status = item.enrollment_status || "Active";
                          const statusClass =
                              status.toLowerCase() === "pending"
                                  ? "bg-warning-lt"
                                  : status.toLowerCase() === "active"
                                    ? "bg-green-lt"
                                    : "bg-secondary-lt";
                          return `<tr><td>${esc(student.student_no)}</td><td>${esc(student.student_id)}</td><td>${esc(student.full_name_en || "-")}</td><td>${esc(item.academic_year?.academic_year || "-")}</td><td>${esc(item.campus?.campus_name_en || "-")}</td><td>${esc(item.school_class?.class_name || "-")}</td><td>${esc(item.school_group?.group_name || "-")}</td><td><span class="badge ${statusClass}">${esc(status)}</span></td></tr>`;
                      })
                      .join("")
                : '<tr><td colspan="8" class="text-center text-secondary py-5">No students found.</td></tr>';
            summary.textContent = data.total
                ? `Showing ${data.from} to ${data.to} of ${data.total}`
                : "";
            pagination.innerHTML = "";
            for (let i = 1; i <= (data.last_page || 1); i += 1) {
                const li = document.createElement("li");
                li.className = `page-item${i === data.current_page ? " active" : ""}`;
                li.innerHTML = `<button type="button" class="page-link">${i}</button>`;
                li.querySelector("button").onclick = () => load(i);
                pagination.appendChild(li);
            }
        };

        const load = async (pageNumber) => {
            results.innerHTML =
                '<tr><td colspan="8" class="text-center text-secondary py-5">Loading students...</td></tr>';
            render(await fetch(queryUrl(pageNumber)).then((r) => r.json()));
        };

        [year, campus, cls, group].forEach((select) =>
            select.addEventListener("change", async () => {
                if (select === year) {
                    campus.value = "";
                    cls.value = "";
                    group.value = "";
                } else if (select === campus) {
                    cls.value = "";
                    group.value = "";
                } else if (select === cls) {
                    group.value = "";
                }
                if (select !== group) await loadOptions();
                [year, campus, cls, group].forEach((item) => {
                    const combo = combos[item.id];
                    if (combo) {
                        combo.label.textContent = item.value
                            ? item.selectedOptions[0]?.textContent || combo.placeholder
                            : combo.placeholder;
                    }
                });
                load(1);
            }),
        );

        let timer;
        text.addEventListener("input", () => {
            clearTimeout(timer);
            timer = setTimeout(() => load(1), 300);
        });

        document.getElementById("search-reset").onclick = async () => {
            year.value = "";
            campus.value = "";
            cls.value = "";
            group.value = "";
            text.value = "";
            await loadOptions();
            load(1);
        };

        makeSearchable(year, "All Academic Years");
        makeSearchable(campus, "All Campuses");
        makeSearchable(cls, "All Grades / Classes");
        makeSearchable(group, "All Groups");
        loadOptions().then(() => load(1));
    });
}
