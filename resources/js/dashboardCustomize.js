document.addEventListener("DOMContentLoaded", () => {
    const escapeHtml = (value) =>
        String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");

    document.querySelectorAll("[data-dashboard-builder]").forEach((builder) => {
        const widgets = JSON.parse(builder.dataset.widgets || "[]");
        let selected = JSON.parse(builder.dataset.selected || "[]");
        let sections = JSON.parse(builder.dataset.sections || "[]");
        if (!sections.length) sections = [{ id: "section-1", title: "Section 1", columns: "4" }];

        const palette = builder.querySelector("[data-widget-palette]");
        const widgetSearch = builder.querySelector("[data-widget-search]");
        const sectionsCanvas = builder.querySelector("[data-dashboard-sections]");
        const hiddenFields = builder.querySelector("[data-dashboard-hidden-fields]");

        const widthOptions = {
            "col-1": "1 Column",
            "col-2": "Merge 2 Columns",
            "col-3": "Merge 3 Columns",
            "col-4": "Merge 4 Columns",
            "col-5": "Merge 5 Columns",
            "col-6": "Merge 6 Columns",
            full: "Full Width",
        };
        const chartTypeOptions = {
            standard: "Standard Chart",
            donut: "Donut Chart",
            vertical_bar: "Vertical Bar Chart",
            grouped_bar: "Grouped Bar Chart",
            horizontal_bar: "Horizontal Bar",
            compact_list: "Compact List",
        };
        const sectionColumnOptions = {
            "1": "1 Column",
            "6": "6 Columns",
            "5": "5 Columns",
            "4": "4 Columns",
            "3": "3 Columns",
            "2": "2 Columns",
            "8-4": "2 Columns (8 / 4)",
            "4-8": "2 Columns (4 / 8)",
            "7-5": "2 Columns (7 / 5)",
            "5-7": "2 Columns (5 / 7)",
        };

        const widgetById = (id) => widgets.find((widget) => Number(widget.id) === Number(id));
        const isSelected = (id) => selected.some((widget) => Number(widget.id) === Number(id));
        const sectionById = (id) => sections.find((section) => section.id === id);
        const normalizeWidth = (width) =>
            ({ small: "col-1", medium: "col-2", large: "col-3" }[width] || width || "col-2");
        const defaultWidgetWidth = (widget) => {
            if (["filter", "table", "hero"].includes(widget.type)) return "full";
            if (["chart", "profile"].includes(widget.type) || ["list", "timeline"].includes(widget.type)) return "col-3";
            return "col-2";
        };

        const previewBody = (widget) => {
            if (widget.type === "filter") return `<div class="dashboard-preview-filter-grid"><span>Academic Year</span><span>Campus</span></div>`;
            if (widget.type === "chart") {
                const chartType = widget.chart_type || "standard";
                if (chartType === "donut") return `<div class="dashboard-preview-donut"><span></span><div><i></i><i></i><i></i></div></div>`;
                if (chartType === "horizontal_bar") return `<div class="dashboard-preview-horizontal-bars"><span style="width: 76%"></span><span style="width: 52%"></span><span style="width: 88%"></span><span style="width: 64%"></span></div>`;
                if (chartType === "grouped_bar") return `<div class="dashboard-preview-grouped-bars"><span><i style="height: 70%"></i><b style="height: 42%"></b></span><span><i style="height: 52%"></i><b style="height: 34%"></b></span><span><i style="height: 88%"></i><b style="height: 48%"></b></span><span><i style="height: 63%"></i><b style="height: 37%"></b></span></div>`;
                if (chartType === "compact_list") return `<div class="dashboard-preview-compact-list"><span></span><span></span><span></span></div>`;
                return `<div class="dashboard-preview-chart ${chartType === "vertical_bar" ? "is-vertical" : ""}"><span style="height: 44%"></span><span style="height: 72%"></span><span style="height: 55%"></span><span style="height: 88%"></span><span style="height: 64%"></span></div>`;
            }
            if (widget.type === "chat") return `<div class="dashboard-preview-chat"><span></span><span></span><span></span></div>`;
            if (widget.type === "table") return `<div class="dashboard-preview-table"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>`;
            if (widget.type === "profile") return `<div class="dashboard-preview-profile"><span></span><div><b></b><i></i></div><span></span><div><b></b><i></i></div></div>`;
            return `<div class="dashboard-preview-number">Preview</div>`;
        };

        const syncHiddenFields = () => {
            hiddenFields.innerHTML = "";
            selected.forEach((widget) => {
                const sectionId = sectionById(widget.section_id) ? widget.section_id : sections[0].id;
                const width = normalizeWidth(widget.width);
                widget.section_id = sectionId;
                widget.width = width;
                hiddenFields.insertAdjacentHTML(
                    "beforeend",
                    `<input type="hidden" name="widget_ids[]" value="${widget.id}"><input type="hidden" name="widget_widths[${widget.id}]" value="${width}"><input type="hidden" name="widget_sections[${widget.id}]" value="${sectionId}"><input type="hidden" name="widget_columns[${widget.id}]" value="${widget.column || 1}"><input type="hidden" name="widget_chart_types[${widget.id}]" value="${widget.chart_type || "standard"}">`,
                );
            });
            hiddenFields.insertAdjacentHTML(
                "beforeend",
                `<input type="hidden" name="sections_payload" value="${escapeHtml(JSON.stringify(sections))}">`,
            );
        };

        const renderPalette = () => {
            const orderedGroups = ["Filters", "Students", "Staff", "Chats", "Communication", "System"];
            const searchTerm = (widgetSearch?.value || "").trim().toLowerCase();
            const filteredWidgets = widgets.filter(
                (widget) =>
                    !searchTerm ||
                    [widget.name, widget.description, widget.category, widget.code].some((value) =>
                        String(value || "").toLowerCase().includes(searchTerm),
                    ),
            );
            const grouped = filteredWidgets.reduce((groups, widget) => {
                const category = widget.category || "System";
                groups[category] = groups[category] || [];
                groups[category].push(widget);
                return groups;
            }, {});

            palette.innerHTML =
                orderedGroups
                    .filter((group) => grouped[group]?.length)
                    .map(
                        (group) => `
                        <div class="dashboard-widget-category">
                            <div class="dashboard-widget-category-title">${group}</div>
                            <div class="dashboard-widget-category-list">
                                ${grouped[group]
                                    .map((widget) => {
                                        const disabled = isSelected(widget.id) ? "is-disabled" : "";
                                        return `<button type="button" class="dashboard-palette-widget ${disabled}" draggable="${disabled ? "false" : "true"}" data-widget-id="${widget.id}">
                                            <span class="dashboard-widget-icon bg-${widget.color}-lt"><i class="ti ${widget.icon}"></i></span>
                                            <span class="dashboard-widget-copy">
                                                <span class="dashboard-widget-name">${escapeHtml(widget.name)}</span>
                                                <span class="dashboard-widget-description">${escapeHtml(widget.description)}</span>
                                            </span>
                                            <i class="ti ti-grip-vertical"></i>
                                        </button>`;
                                    })
                                    .join("")}
                            </div>
                        </div>`,
                    )
                    .join("") ||
                `<div class="dashboard-widget-empty"><i class="ti ti-search"></i><span>No widgets found.</span></div>`;
        };

        const renderSelected = () => {
            sectionsCanvas.innerHTML = sections
                .map(
                    (section, index) => `
                        <div class="dashboard-section-preview" data-section-id="${section.id}">
                            <div class="dashboard-section-toolbar">
                                <input type="text" class="form-control form-control-sm" value="${escapeHtml(section.title)}" data-section-title>
                                <select class="form-select form-select-sm" data-section-columns>
                                    ${Object.entries(sectionColumnOptions)
                                        .map(([value, label]) => `<option value="${value}" ${value === section.columns ? "selected" : ""}>${label}</option>`)
                                        .join("")}
                                </select>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-remove-section ${sections.length <= 1 ? "disabled" : ""}><i class="ti ti-x"></i></button>
                            </div>
                            <div class="dashboard-section-drop-zone dashboard-section-columns-${section.columns}" data-section-drop-zone>
                                ${(() => {
                                    const sectionWidgets = selected.filter((widget) => widget.section_id === section.id);
                                    const onlyFullWidth = sectionWidgets.length > 0 && sectionWidgets.every((widget) => normalizeWidth(widget.width) === "full");
                                    if (/^\d+$/.test(section.columns) && Number(section.columns) > 1 && !onlyFullWidth) {
                                        const columnCount = Number(section.columns);
                                        const occupied = new Set();
                                        const zones = [];
                                        sectionWidgets.forEach((widget) => {
                                            const start = Math.max(1, Number(widget.column || 1));
                                            const match = String(normalizeWidth(widget.width)).match(/^col-(\d+)$/);
                                            const span = Math.min(columnCount - start + 1, Math.max(1, Number(match?.[1] || 1)));
                                            if (normalizeWidth(widget.width) !== "full") {
                                                for (let column = start; column < start + span; column += 1) occupied.add(column);
                                            }
                                        });
                                        for (let column = 1; column <= columnCount; column += 1) {
                                            if (occupied.has(column)) {
                                                const widget = sectionWidgets.find((item) => Number(item.column || 1) === column && normalizeWidth(item.width) !== "full");
                                                const match = String(normalizeWidth(widget?.width)).match(/^col-(\d+)$/);
                                                const span = Math.min(columnCount - column + 1, Math.max(1, Number(match?.[1] || 1)));
                                                if (column === Number(widget?.column || 1)) zones.push(`<div class="dashboard-column-drop-zone dashboard-column-span-${span}" data-column="${column}" style="grid-column: ${column} / span ${span};"><span>Column${span > 1 ? `s ${column}–${column + span - 1}` : ` ${column}`}</span></div>`);
                                                continue;
                                            }
                                            zones.push(`<div class="dashboard-column-drop-zone" data-column="${column}" style="grid-column: ${column};"><span>Column ${column}</span></div>`);
                                        }
                                        return zones.join("");
                                    }
                                    return sectionWidgets.length ? "" : `<div class="dashboard-drop-empty"><i class="ti ti-hand-click"></i><span>Drop widgets into ${escapeHtml(section.title || `Section ${index + 1}`)}</span></div>`;
                                })()}
                            </div>
                        </div>`,
                )
                .join("");

            selected.forEach((widget) => {
                widget.section_id = sectionById(widget.section_id) ? widget.section_id : sections[0].id;
                widget.width = normalizeWidth(widget.width);
                widget.column = Math.max(1, Number(widget.column || 1));
                if (sectionById(widget.section_id)?.columns === "1") widget.width = "full";
                const sectionElement = sectionsCanvas.querySelector(`[data-section-id="${widget.section_id}"]`);
                const dropZone = sectionElement?.querySelector(`[data-column="${widget.column || 1}"]`) || sectionElement?.querySelector("[data-section-drop-zone]");
                if (!dropZone) return;

                const card = document.createElement("div");
                card.className = `dashboard-selected-widget dashboard-preview-widget dashboard-width-${widget.width}`;
                card.draggable = true;
                card.dataset.widgetId = widget.id;
                card.innerHTML = `
                    <div class="dashboard-selected-handle"><i class="ti ti-grip-vertical"></i></div>
                    <div class="dashboard-preview-card-top">
                        <span class="dashboard-widget-icon bg-${widget.color}-lt"><i class="ti ${widget.icon}"></i></span>
                        <span class="dashboard-widget-copy">
                            <span class="dashboard-widget-name">${escapeHtml(widget.name)}</span>
                            <span class="dashboard-widget-description">${escapeHtml(widget.description)}</span>
                        </span>
                    </div>
                    ${previewBody(widget)}
                    <div class="dashboard-preview-card-actions">
                        <div class="dashboard-mobile-widget-movers" aria-label="Move widget">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-move-widget="up" title="Move up"><i class="ti ti-chevron-up"></i><span>Up</span></button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-move-widget="down" title="Move down"><i class="ti ti-chevron-down"></i><span>Down</span></button>
                        </div>
                        ${widget.type === "chart" ? `<select class="form-select form-select-sm" data-widget-chart-type>${Object.entries(chartTypeOptions).map(([value, label]) => `<option value="${value}" ${value === (widget.chart_type || "standard") ? "selected" : ""}>${label}</option>`).join("")}</select>` : ""}
                        <select class="form-select form-select-sm" data-widget-width>${Object.entries(widthOptions).map(([value, label]) => `<option value="${value}" ${value === widget.width ? "selected" : ""}>${label}</option>`).join("")}</select>
                        <button class="btn btn-sm btn-outline-danger" type="button" data-remove-widget><i class="ti ti-x"></i></button>
                    </div>
                `;
                dropZone.appendChild(card);
            });

            syncHiddenFields();
            renderPalette();
        };

        const addWidget = (id, sectionId = null, column = 1) => {
            const widget = widgetById(id);
            if (!widget || isSelected(id)) return;
                selected.push({
                ...widget,
                width: defaultWidgetWidth(widget),
                section_id: sectionId || sections[0].id,
                column,
            });
            renderSelected();
        };

        const moveWidget = (draggedId, targetId, sectionId = null, column = 1) => {
            const fromIndex = selected.findIndex((widget) => Number(widget.id) === Number(draggedId));
            if (fromIndex < 0) return;
            const [moved] = selected.splice(fromIndex, 1);
            moved.section_id = sectionId || moved.section_id;
            moved.column = column || moved.column || 1;
            const toIndex = targetId ? selected.findIndex((widget) => Number(widget.id) === Number(targetId)) : -1;
            if (toIndex < 0) selected.push(moved);
            else selected.splice(toIndex, 0, moved);
            renderSelected();
        };

        palette?.addEventListener("dragstart", (event) => {
            const widgetButton = event.target.closest("[data-widget-id]");
            if (!widgetButton || widgetButton.classList.contains("is-disabled")) return;
            event.dataTransfer.setData("text/plain", widgetButton.dataset.widgetId);
            event.dataTransfer.effectAllowed = "copy";
        });
        widgetSearch?.addEventListener("input", renderPalette);

        sectionsCanvas?.addEventListener("dragstart", (event) => {
            const card = event.target.closest(".dashboard-selected-widget");
            if (!card) return;
            event.dataTransfer.setData("text/plain", `selected:${card.dataset.widgetId}`);
            event.dataTransfer.effectAllowed = "move";
        });
        sectionsCanvas?.addEventListener("dragover", (event) => {
            const dropZone = event.target.closest("[data-column], [data-section-drop-zone]");
            if (!dropZone) return;
            event.preventDefault();
            dropZone.classList.add("is-drag-over");
        });
        sectionsCanvas?.addEventListener("dragleave", (event) => {
            const dropZone = event.target.closest("[data-column], [data-section-drop-zone]");
            if (dropZone && !dropZone.contains(event.relatedTarget)) dropZone.classList.remove("is-drag-over");
        });
        sectionsCanvas?.addEventListener("drop", (event) => {
            const dropZone = event.target.closest("[data-column], [data-section-drop-zone]");
            if (!dropZone) return;
            event.preventDefault();
            dropZone.classList.remove("is-drag-over");
            const sectionId = dropZone.closest("[data-section-id]")?.dataset.sectionId;
            const column = Number(dropZone.dataset.column || 1);
            const dragged = event.dataTransfer.getData("text/plain");
            const targetCard = event.target.closest(".dashboard-selected-widget");
            dragged.startsWith("selected:")
                ? moveWidget(dragged.replace("selected:", ""), targetCard?.dataset.widgetId, sectionId, column)
                : addWidget(dragged, sectionId, column);
        });

        builder.addEventListener("click", (event) => {
            const paletteWidget = event.target.closest(".dashboard-palette-widget:not(.is-disabled)");
            if (paletteWidget) addWidget(paletteWidget.dataset.widgetId);

            const removeButton = event.target.closest("[data-remove-widget]");
            if (removeButton) {
                const card = removeButton.closest(".dashboard-selected-widget");
                selected = selected.filter((widget) => Number(widget.id) !== Number(card.dataset.widgetId));
                renderSelected();
            }

            const moveButton = event.target.closest("[data-move-widget]");
            if (moveButton) {
                const card = moveButton.closest(".dashboard-selected-widget");
                const id = Number(card?.dataset.widgetId);
                const currentIndex = selected.findIndex((widget) => Number(widget.id) === id);
                if (currentIndex >= 0) {
                    const direction = moveButton.dataset.moveWidget === "up" ? -1 : 1;
                    const targetIndex = currentIndex + direction;
                    if (targetIndex >= 0 && targetIndex < selected.length) {
                        [selected[currentIndex], selected[targetIndex]] = [selected[targetIndex], selected[currentIndex]];
                        renderSelected();
                    }
                }
            }

            if (event.target.closest("[data-add-dashboard-section]")) {
                const next = sections.length + 1;
                sections.push({ id: `section-${Date.now()}`, title: `Section ${next}`, columns: "4" });
                renderSelected();
            }

            if (event.target.closest("[data-remove-section]")) {
                const sectionId = event.target.closest("[data-section-id]")?.dataset.sectionId;
                if (sectionId && sections.length > 1) {
                    const fallbackSection = sections.find((section) => section.id !== sectionId)?.id;
                    selected = selected.map((widget) =>
                        widget.section_id === sectionId ? { ...widget, section_id: fallbackSection } : widget,
                    );
                    sections = sections.filter((section) => section.id !== sectionId);
                    renderSelected();
                }
            }

            if (event.target.closest("[data-clear-dashboard-widgets]")) {
                selected = [];
                renderSelected();
            }
        });

        builder.addEventListener("change", (event) => {
            const widthSelect = event.target.closest("[data-widget-width]");
            if (widthSelect) {
                const id = widthSelect.closest(".dashboard-selected-widget").dataset.widgetId;
                selected = selected.map((widget) =>
                    Number(widget.id) === Number(id) ? { ...widget, width: widthSelect.value } : widget,
                );
                renderSelected();
            }

            const chartTypeSelect = event.target.closest("[data-widget-chart-type]");
            if (chartTypeSelect) {
                const id = chartTypeSelect.closest(".dashboard-selected-widget").dataset.widgetId;
                selected = selected.map((widget) =>
                    Number(widget.id) === Number(id) ? { ...widget, chart_type: chartTypeSelect.value } : widget,
                );
                renderSelected();
            }

            const sectionColumns = event.target.closest("[data-section-columns]");
            if (sectionColumns) {
                const sectionId = sectionColumns.closest("[data-section-id]").dataset.sectionId;
                sections = sections.map((section) =>
                    section.id === sectionId ? { ...section, columns: sectionColumns.value } : section,
                );
                if (sectionColumns.value === "1") {
                    selected = selected.map((widget) =>
                        widget.section_id === sectionId ? { ...widget, width: "full" } : widget,
                    );
                } else if (["2", "3", "4", "5", "6"].includes(sectionColumns.value)) {
                    // A multi-column section uses one grid cell per widget by
                    // default; additional widgets in the same column become
                    // rows and can be reordered with drag-and-drop.
                    selected = selected.map((widget) =>
                        widget.section_id === sectionId ? { ...widget, width: "col-1" } : widget,
                    );
                }
                renderSelected();
            }
        });

        builder.addEventListener("input", (event) => {
            const titleInput = event.target.closest("[data-section-title]");
            if (!titleInput) return;
            const sectionId = titleInput.closest("[data-section-id]").dataset.sectionId;
            sections = sections.map((section) =>
                section.id === sectionId ? { ...section, title: titleInput.value || "Section" } : section,
            );
            syncHiddenFields();
        });

        renderSelected();
    });

    document.querySelectorAll("[data-confirm-message]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (form.dataset.confirmed === "true") return;
            if (!window.confirm(form.dataset.confirmMessage || "Are you sure?")) {
                event.preventDefault();
                return;
            }
            form.dataset.confirmed = "true";
        });
    });
});
