import Swal from "sweetalert2";
import * as bootstrap from "bootstrap";

const root = document.querySelector("#placementTestApp");
if (root) {
    const section = root.dataset.section,
        dataUrl = root.dataset.dataUrl,
        csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
    const permissions = JSON.parse(
        document.querySelector("#ptPermissions")?.content.textContent || "{}",
    );
    const content = document.querySelector("#ptContent"),
        actions = document.querySelector("#ptPrimaryActions");
    const editorEl = document.querySelector("#ptEditorModal"),
        actionEl = document.querySelector("#ptActionModal");
    const editor = bootstrap.Modal.getOrCreateInstance(editorEl),
        actionModal = bootstrap.Modal.getOrCreateInstance(actionEl);
    let payload = { meta: {} },
        editorHandler = null,
        actionHandler = null,
        searchTimer;
    const tabsCard = root.querySelector(".pt-tabs-card"),
        tabsToggle = tabsCard?.querySelector(".pt-tabs-toggle"),
        tabsStorageKey = "placementTestTabsCollapsed";
    if (tabsCard && tabsToggle) {
        const tabsIcon = tabsToggle.querySelector("i"),
            tabsNav = tabsCard.querySelector(".pt-section-nav"),
            isMobile = () => window.innerWidth < 992;
        const tooltipTriggers = [
            tabsToggle,
            ...tabsCard.querySelectorAll(
                '.pt-section-nav [data-bs-toggle="tooltip"]',
            ),
        ];
        const tooltips = tooltipTriggers.map((element) => {
            const tooltip = bootstrap.Tooltip.getOrCreateInstance(element, {
                container: "body",
                placement: "right",
                customClass: "pt-tabs-tooltip",
                trigger: "hover",
                delay: { show: 200, hide: 0 },
                title: () =>
                    element === tabsToggle
                        ? element.getAttribute("aria-label")
                        : element.dataset.tabLabel,
            });

            element.addEventListener("click", () => {
                tooltip.hide();
                element.blur();
            });

            return tooltip;
        });
        const setTooltip = (element, label) => {
            element.setAttribute("data-bs-title", label);
            element.setAttribute("aria-label", label);
            bootstrap.Tooltip.getInstance(element)?.setContent({
                ".tooltip-inner": label,
            });
        };
        const syncTabs = () => {
            const collapsed = isMobile()
                ? !tabsCard.classList.contains("is-open")
                : root.classList.contains("pt-tabs-collapsed");
            const toggleLabel = collapsed
                ? "Expand placement test sections"
                : "Collapse placement test sections";
            tabsToggle.setAttribute("aria-expanded", String(!collapsed));
            setTooltip(tabsToggle, toggleLabel);
            tabsIcon.className = `ti ti-layout-sidebar-left-${collapsed ? "expand" : "collapse"}`;
            tooltips.forEach((tooltip, index) => {
                tooltip.hide();
                if (index === 0 || collapsed) tooltip.enable();
                else {
                    tooltip.disable();
                }
            });
        };
        try {
            if (localStorage.getItem(tabsStorageKey) === "0")
                root.classList.remove("pt-tabs-collapsed");
            else root.classList.add("pt-tabs-collapsed");
        } catch {
            root.classList.add("pt-tabs-collapsed");
        }
        tabsToggle.addEventListener("click", (event) => {
            event.stopPropagation();
            if (isMobile()) tabsCard.classList.toggle("is-open");
            else {
                const collapsed = root.classList.toggle("pt-tabs-collapsed");
                try {
                    localStorage.setItem(tabsStorageKey, collapsed ? "1" : "0");
                } catch {}
            }
            syncTabs();
        });
        tabsNav?.addEventListener("click", (event) => {
            event.stopPropagation();
            const trigger = event.target.closest('[data-bs-toggle="tooltip"]');
            if (trigger) bootstrap.Tooltip.getInstance(trigger)?.hide();
        });
        document.addEventListener("click", () => {
            if (isMobile() && tabsCard.classList.contains("is-open")) {
                tabsCard.classList.remove("is-open");
                syncTabs();
            }
        });
        window.addEventListener("resize", () => {
            tabsCard.classList.remove("is-open");
            syncTabs();
        });
        syncTabs();
    }
    const filterCard = root.querySelector(".pt-filter-card"),
        filterToggle = filterCard?.querySelector("[data-filter-toggle]");
    const syncFilterCard = () => {
        if (!filterCard || !filterToggle) return;
        const collapsed = filterCard.classList.contains("is-collapsed");
        filterToggle.setAttribute("aria-expanded", String(!collapsed));
        filterToggle.setAttribute(
            "aria-label",
            collapsed ? "Expand data filters" : "Collapse data filters",
        );
    };
    if (filterCard && filterToggle) {
        if (window.innerWidth < 768) filterCard.classList.add("is-collapsed");
        syncFilterCard();
    }
    const $ = (s) => document.querySelector(s),
        esc = (v) =>
            String(v ?? "").replace(
                /[&<>'"]/g,
                (c) =>
                    ({
                        "&": "&amp;",
                        "<": "&lt;",
                        ">": "&gt;",
                        "'": "&#39;",
                        '"': "&quot;",
                    })[c],
            );
    const val = (object, path, fallback = "—") =>
        path.split(".").reduce((v, k) => v?.[k], object) ?? fallback;
    const fmt = (v) =>
        v
            ? new Intl.DateTimeFormat("en-GB", {
                  day: "2-digit",
                  month: "short",
                  year: "numeric",
              }).format(new Date(v))
            : "—";
    const badge = (v) =>
        `<span class="pt-badge ${esc(String(v || "pending").toLowerCase())}">${esc(String(v || "pending").replaceAll("_", " "))}</span>`;
    const initials = (n) =>
        String(n || "PT")
            .split(/\s+/)
            .slice(0, 2)
            .map((x) => x[0])
            .join("")
            .toUpperCase();
    const optionLabels = (q) =>
        (q.options || []).map((_, index) =>
            (q.option_label_style || "letters") === "numbers"
                ? String(index + 1)
                : String.fromCharCode(65 + index),
        );
    const optionRow = (
        value = "",
        index = 0,
        correct = "",
        style = "letters",
    ) => {
        const label =
            style === "numbers"
                ? String(index + 1)
                : String.fromCharCode(65 + index);
        return `<div class="pt-option-row"><strong data-option-label>${label}</strong><input class="form-control" name="option" value="${esc(value)}" required><input class="form-check-input" type="radio" name="correct_answer" value="${label}" ${correct === label ? "checked" : ""} required title="Correct answer"><button class="btn btn-icon btn-outline-danger" type="button" data-remove-option title="Remove option"><i class="ti ti-x"></i></button></div>`;
    };
    const questionTypes = [
        ["multiple_choice", "Multiple choice"],
        ["true_false", "True / False"],
        ["fill_blank", "Fill in the Blank"],
        ["short_answer", "Short Answer"],
        ["essay", "Essay / Writing"],
    ];
    const isChoiceQuestion = (type) =>
        ["multiple_choice", "true_false"].includes(type || "multiple_choice");
    const button = (label, icon, attrs = "", style = "outline-primary") =>
        `<button type="button" class="btn btn-${style} btn-sm" ${attrs}><i class="ti ti-${icon}"></i> ${label}</button>`;
    const notify = (icon, title, text = "") =>
        Swal.fire({ icon, title, text, confirmButtonColor: "#316fd6" });
    const api = async (url, { method = "GET", body } = {}) => {
        const options = {
            method,
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": csrf,
                "X-Requested-With": "XMLHttpRequest",
            },
        };
        if (body instanceof FormData) options.body = body;
        else if (body !== undefined) {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(body);
        }
        const response = await fetch(url, options);
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = result.errors
                ? Object.values(result.errors).flat().join("\n")
                : result.message;
            throw new Error(errors || "The request could not be completed.");
        }
        return result;
    };
    const query = () => {
        const q = new URLSearchParams({ section });
        [
            ["academic_year_id", "#ptAcademicYear"],
            ["campus_id", "#ptCampus"],
            ["grade_id", "#ptGrade"],
            ["search", "#ptSearch"],
        ].forEach(([key, id]) => {
            if ($(id)?.value) q.set(key, $(id).value);
        });
        return q;
    };
    const empty = (title, message, icon = "clipboard-text") =>
        `<div class="pt-empty"><i class="ti ti-${icon}"></i><h3>${title}</h3><p>${message}</p></div>`;
    const table = (headers, rows) =>
        rows.length
            ? `<div class="pt-table-wrap"><table class="pt-table"><thead><tr>${headers.map((x) => `<th>${x}</th>`).join("")}</tr></thead><tbody>${rows.join("")}</tbody></table></div>`
            : empty(
                  "No records found",
                  "Add the first record or change the active filters.",
              );
    const person = (a) =>
        `<div class="pt-person"><span class="pt-avatar">${esc(initials(a.full_name_en || a.full_name_kh))}</span><div><strong>${esc(a.full_name_kh || a.full_name_en)}</strong><small>${esc(a.full_name_en)} · ${esc(a.application_code || "")}</small></div></div>`;
    const attemptName = (a) => person(a.applicant || {});
    const optionList = (items, id, label) => {
        const select = $(id);
        if (!select) return;
        const current = select.value;
        select.innerHTML =
            `<option value="">All ${label}</option>` +
            items
                .map(
                    (x) =>
                        `<option value="${x.id}">${esc(x.academic_year || x.campus_name_en || x.grade_short_name || x.grade)}</option>`,
                )
                .join("");
        select.value = current;
    };
    const fillMeta = () => {
        optionList(
            payload.meta.academic_years || [],
            "#ptAcademicYear",
            "academic years",
        );
        optionList(payload.meta.campuses || [], "#ptCampus", "campuses");
        optionList(payload.meta.grades || [], "#ptGrade", "grades");
    };
    const setSummary = () =>
        Object.entries(payload.summary || {}).forEach(([key, value]) => {
            const el = document.querySelector(`[data-summary="${key}"]`);
            if (el) el.textContent = value;
        });
    const descriptions = {
        dashboard:
            "Live admissions assessment overview and recent candidate activity.",
        applicants:
            "Register applicants, assign the correct grade and launch an assessment.",
        questions:
            "Build an approved, reusable question bank with balanced curriculum coverage.",
        forms: "Create immutable A, B and C test revisions with duration and subject pass rules.",
        sessions: "Manage online, printed and manually marked test sessions.",
        marking:
            "Upload printed answer sheets, review uncertain marks and verify the final score.",
        results:
            "Review verified outcomes, subject performance and placement recommendations.",
        enrollment:
            "Move successful applicants into Student Enrollment without retyping their details.",
        settings:
            "Operational standards for secure, fair and auditable placement testing.",
    };
    const load = async () => {
        content.innerHTML =
            '<div class="pt-loading"><span class="spinner-border"></span><p>Loading placement test workspace…</p></div>';
        try {
            payload = await api(`${dataUrl}?${query()}`);
            fillMeta();
            setSummary();
            render();
        } catch (e) {
            content.innerHTML = empty(
                "Unable to load",
                esc(e.message),
                "alert-triangle",
            );
        }
    };

    const render = () => {
        $("#ptSectionDescription").textContent = descriptions[section] || "";
        actions.innerHTML = "";
        (
            ({
                dashboard: renderDashboard,
                applicants: renderApplicants,
                questions: renderQuestions,
                forms: renderForms,
                sessions: renderSessions,
                marking: renderMarking,
                results: renderResults,
                enrollment: renderEnrollment,
                settings: renderSettingsPage,
            })[section] || renderDashboard
        )();
    };
    const renderDashboard = () => {
        if (permissions.applicants)
            actions.innerHTML = button(
                "Add Applicant",
                "user-plus",
                "data-add-applicant",
                "primary",
            );
        const applicants = payload.applicants || [],
            recent = payload.recent_attempts || [];
        content.innerHTML = `<div class="pt-grade-grid"><article class="pt-card"><div class="pt-card-head"><div><h3>Admissions Pipeline</h3><p>Applicants by current stage</p></div><i class="ti ti-chart-donut"></i></div><div class="pt-card-body"><div class="pt-card-stat"><span>Registered</span><strong>${payload.summary?.applicants || 0}</strong></div><div class="pt-card-stat"><span>Testing</span><strong>${payload.summary?.testing || 0}</strong></div><div class="pt-card-stat"><span>Passed</span><strong>${payload.summary?.passed || 0}</strong></div></div></article><article class="pt-card"><div class="pt-card-head"><div><h3>Assessment Channels</h3><p>Flexible delivery with one result standard</p></div><i class="ti ti-route"></i></div><div class="pt-card-body"><div class="pt-card-stat"><span>Online test</span><strong>Timed</strong></div><div class="pt-card-stat"><span>Printed paper</span><strong>QR scan</strong></div><div class="pt-card-stat"><span>Manual entry</span><strong>Verified</strong></div></div></article><article class="pt-card"><div class="pt-card-head"><div><h3>Quality Controls</h3><p>Professional assessment governance</p></div><i class="ti ti-shield-check"></i></div><div class="pt-card-body"><div class="pt-card-stat"><span>Published revisions</span><strong>Locked</strong></div><div class="pt-card-stat"><span>Subject thresholds</span><strong>Enabled</strong></div><div class="pt-card-stat"><span>Audit trail</span><strong>Active</strong></div></div></article></div><div class="px-3 pb-3"><h3 class="h4 mb-2">Recent Sessions</h3>${attemptTable(recent)}</div>`;
    };
    const renderApplicants = () => {
        actions.innerHTML = permissions.applicants
            ? button(
                  "Add Applicant",
                  "user-plus",
                  "data-add-applicant",
                  "primary",
              )
            : "";
        const rows = (payload.applicants || []).map(
            (a) =>
                `<tr><td class="pt-main" data-label="Applicant">${person(a)}</td><td data-label="Target grade"><span class="pt-stack"><strong>${esc(val(a, "target_grade.grade_short_name", val(a, "target_grade.grade")))}</strong><small>${esc(val(a, "academic_year.academic_year"))}</small></span></td><td data-label="Campus">${esc(val(a, "campus.campus_name_en"))}</td><td data-label="Contact"><span class="pt-stack"><strong>${esc(a.phone || "—")}</strong><small>${esc(a.parent_name || a.email || "")}</small></span></td><td data-label="Status">${badge(a.status)}</td><td data-label="Actions"><div class="pt-row-actions">${button("View", "eye", `data-view-applicant="${a.id}"`)}${permissions.applicants && a.status !== "archived" ? button("Edit", "edit", `data-edit-applicant="${a.id}"`) : ""}${permissions.assign && a.status !== "archived" ? button("Assign Test", "clipboard-check", `data-assign="${a.id}"`, "primary") : ""}${permissions.applicants && a.status !== "archived" ? button("Archive", "archive", `data-archive-applicant="${a.id}"`, "outline-danger") : ""}</div></td></tr>`,
        );
        content.innerHTML = table(
            [
                "Applicant",
                "Target Grade",
                "Campus",
                "Contact",
                "Status",
                "Actions",
            ],
            rows,
        );
    };
    const renderQuestions = () => {
        actions.innerHTML = permissions.questions
            ? button(
                  "Add Question",
                  "circle-plus",
                  "data-add-question",
                  "primary",
              )
            : "";
        const rows = (payload.questions || []).map(
            (q) =>
                `<tr><td data-label="No."><span class="pt-code">Q${q.id}</span></td><td class="pt-main" data-label="Question"><span class="pt-stack"><strong>${esc(q.prompt)}</strong><small>${esc(q.topic || "General")}</small></span></td><td data-label="Grade">${esc(val(q, "grade.grade_short_name", val(q, "grade.grade")))}</td><td data-label="Subject">${esc(q.subject)}</td><td data-label="Difficulty">${badge(q.difficulty)}</td><td data-label="Points">${esc(q.default_points)}</td><td data-label="Status">${badge(q.status)}</td><td data-label="Actions"><div class="pt-row-actions">${button("View", "eye", `data-view-question="${q.id}"`)}${permissions.questions && q.status !== "archived" ? button("Edit", "edit", `data-edit-question="${q.id}"`) : ""}${permissions.questions ? button("Duplicate", "copy", `data-duplicate-question="${q.id}"`) : ""}${permissions.questions && q.status !== "archived" ? button("Archive", "archive", `data-archive-question="${q.id}"`, "outline-danger") : ""}</div></td></tr>`,
        );
        content.innerHTML = table(
            [
                "No.",
                "Question",
                "Grade",
                "Subject",
                "Difficulty",
                "Points",
                "Status",
                "Actions",
            ],
            rows,
        );
    };
    const renderForms = () => {
        actions.innerHTML = permissions.forms
            ? button("Create Test Form", "forms", "data-add-form", "primary")
            : "";
        const forms = payload.forms || [];
        content.innerHTML = forms.length
            ? `<div class="pt-grade-grid">${forms.map((f) => `<article class="pt-card"><div class="pt-card-head"><div><span class="pt-content-kicker">GRADE ${esc(val(f, "grade.grade_short_name", val(f, "grade.grade")))}</span><h3>${esc(f.name)} · Form ${esc(f.form_code)}</h3><p>Revision ${f.revision} · ${f.questions?.length || 0} questions</p></div>${badge(f.status)}</div><div class="pt-card-body"><div class="pt-card-stat"><span>Duration</span><strong>${f.duration_minutes} min</strong></div><div class="pt-card-stat"><span>Maximum / Pass</span><strong>${f.max_score} / ${f.pass_score}</strong></div><div class="pt-card-stat"><span>Assigned attempts</span><strong>${f.attempts_count || 0}</strong></div><div class="pt-row-actions">${button("View", "eye", `data-view-form="${f.id}"`)}${permissions.forms && f.status === "draft" ? button("Edit", "edit", `data-edit-form="${f.id}"`) + button("Questions", "list-check", `data-form-questions="${f.id}"`) : ""}${permissions.forms ? button("New Revision", "copy-plus", `data-duplicate-form="${f.id}"`) : ""}${statusButtons(f)}</div></div></article>`).join("")}</div>`
            : empty(
                  "No test forms",
                  "Create Grade 1–12 forms A, B and C, then assign approved questions.",
                  "forms",
              );
    };
    const statusButtons = (f) => {
        if (!permissions.publish) return "";
        const next = {
            draft: "review",
            review: "approved",
            approved: "published",
            published: "archived",
        }[f.status];
        return next
            ? button(
                  next[0].toUpperCase() + next.slice(1),
                  next === "published" ? "send" : "arrow-right",
                  `data-form-status="${f.id}" data-status="${next}"`,
                  next === "archived" ? "outline-secondary" : "outline-success",
              )
            : "";
    };
    const attemptTable = (list) => {
        const rows = (list || []).map(
            (a) =>
                `<tr><td class="pt-main" data-label="Applicant">${attemptName(a)}</td><td data-label="Test"><span class="pt-stack"><strong>Form ${esc(val(a, "form.form_code"))} · ${esc(val(a, "form.grade.grade_short_name", val(a, "form.grade.grade")))}</strong><small>Attempt ${a.attempt_no} · ${esc(a.method)}</small></span></td><td data-label="Status">${badge(a.status)}</td><td data-label="Score">${a.score == null ? "—" : `<strong>${a.score} / ${a.max_score}</strong><small>${a.percentage}%</small>`}</td><td data-label="Result">${badge(a.result)}</td><td data-label="Actions"><div class="pt-row-actions">${sessionButtons(a)}</div></td></tr>`,
        );
        return table(
            ["Applicant", "Test", "Status", "Score", "Result", "Actions"],
            rows,
        );
    };
    const sessionButtons = (a) => {
        let out = button("View", "eye", `data-view-attempt="${a.id}"`);
        const pendingScan = (a.scans || []).find(
            (scan) => scan.status === "pending",
        );
        if (
            a.method === "online" &&
            ["assigned", "in_progress"].includes(a.status)
        )
            out += button(
                "Copy Link",
                "copy",
                `data-copy-link="${a.access_token}"`,
            );
        if (a.method === "paper")
            out += `<a class="btn btn-outline-primary btn-sm" target="_blank" href="${root.dataset.base || "/students/placement-tests"}/attempts/${a.id}/print?document=package"><i class="ti ti-printer"></i> Print</a>`;
        if (a.method === "manual")
            out += `<a class="btn btn-outline-primary btn-sm" target="_blank" href="${root.dataset.base || "/students/placement-tests"}/attempts/${a.id}/print?document=questions"><i class="ti ti-printer"></i> Print Questions</a>`;
        if (
            permissions.score &&
            ["assigned", "in_progress", "submitted"].includes(a.status) &&
            !pendingScan
        )
            out += button("Enter Answers", "checks", `data-score="${a.id}"`);
        if (
            permissions.scan &&
            payload.meta?.defaults?.scan_enabled !== false &&
            pendingScan
        )
            out += button(
                "Review Scan",
                "scan-eye",
                `data-review-scan="${pendingScan.id}" data-attempt="${a.id}"`,
                "warning",
            );
        else if (
            permissions.scan &&
            payload.meta?.defaults?.scan_enabled !== false &&
            a.method === "paper" &&
            a.status !== "verified"
        )
            out += button("Scan", "scan", `data-scan="${a.id}"`, "primary");
        if (permissions.score && a.status !== "invalidated")
            out += button(
                "Invalidate",
                "ban",
                `data-invalidate-attempt="${a.id}"`,
                "outline-danger",
            );
        return out;
    };
    const renderSessions = () => {
        content.innerHTML = attemptTable(payload.attempts || []);
    };
    const renderMarking = () => {
        const pending = (payload.scans || []).filter(
            (s) => s.status === "pending",
        );
        content.innerHTML = `${pending.length ? `<div class="p-3"><div class="pt-scan-banner"><strong>${pending.length} scanned sheet${pending.length === 1 ? "" : "s"} waiting for verification.</strong> Review every flagged bubble before confirming the result.</div></div>` : ""}${attemptTable(payload.attempts || [])}`;
    };
    const renderResults = () => {
        const rows = (payload.attempts || []).map(
            (a) =>
                `<tr><td class="pt-main" data-label="Applicant">${attemptName(a)}</td><td data-label="Placement"><span class="pt-stack"><strong>${esc(val(a, "form.grade.grade_short_name", val(a, "form.grade.grade")))}</strong><small>Form ${esc(val(a, "form.form_code"))}</small></span></td><td data-label="Score"><strong>${a.score} / ${a.max_score}</strong><small>${a.percentage}%</small></td><td data-label="Subject results">${
                    Object.entries(a.subject_scores || {})
                        .map(
                            ([s, x]) =>
                                `<span class="pt-badge ${x.passed ? "passed" : "failed"}">${esc(s)} ${esc(x.score)}/${esc(x.max_score)}</span>`,
                        )
                        .join(" ") || "—"
                }</td><td data-label="Result">${badge(a.result)}</td><td data-label="Verified">${fmt(a.verified_at)}</td><td data-label="Actions"><div class="pt-row-actions">${button("View", "eye", `data-view-attempt="${a.id}"`)}</div></td></tr>`,
        );
        content.innerHTML = table(
            [
                "Applicant",
                "Placement",
                "Score",
                "Subject Results",
                "Result",
                "Verified",
                "Actions",
            ],
            rows,
        );
    };
    const renderEnrollment = () => {
        const rows = (payload.applicants || []).map((a) => {
            const passed = (a.attempts || []).find(
                (x) => x.status === "verified" && x.result === "passed",
            );
            return `<tr><td class="pt-main" data-label="Applicant">${person(a)}</td><td data-label="Recommended grade">${esc(val(a, "recommended_grade.grade_short_name", val(a, "target_grade.grade_short_name")))}</td><td data-label="Campus">${esc(val(a, "campus.campus_name_en"))}</td><td data-label="Result">${passed ? `<strong>${passed.score}/${passed.max_score}</strong> ${badge("passed")}` : badge(a.status)}</td><td data-label="Actions"><div class="pt-row-actions">${a.enrolled_student_id ? badge("enrolled") : payload.meta?.defaults?.enrollment_handoff_enabled === false ? badge("disabled") : button("Prepare Enrollment", "school", `data-enroll="${a.id}"`, "primary")}</div></td></tr>`;
        });
        content.innerHTML = table(
            ["Applicant", "Recommended Grade", "Campus", "Result", "Actions"],
            rows,
        );
    };
    const renderSettings = () => {
        const s = payload.settings || payload.meta?.defaults || {};
        actions.innerHTML = button(
            "Save Settings",
            "device-floppy",
            "data-save-settings",
            "primary",
        );
        const toggle = (name, label, description, icon) =>
            `<label class="pt-setting-toggle"><span class="pt-setting-toggle-icon"><i class="ti ti-${icon}"></i></span><span><strong>${label}</strong><small>${description}</small></span><input class="form-check-input" type="checkbox" name="${name}" ${s[name] ? "checked" : ""}></label>`;
        const activity = (payload.activity || [])
            .map(
                (item) =>
                    `<li><span class="pt-activity-icon"><i class="ti ti-history"></i></span><span><strong>${esc(String(item.action || "").replaceAll("-", " "))}</strong><small>${esc(item.user?.name || "System")} · ${fmt(item.created_at)}</small></span></li>`,
            )
            .join("");
        content.innerHTML = `<form id="ptSettingsForm" data-campus-id="${esc(s.campus_id || "")}"><div class="pt-settings-grid"><section class="pt-settings-panel"><header><span><i class="ti ti-route"></i></span><div><h3>Delivery Channels</h3><p>Choose how applicants may take placement tests.</p></div></header><div class="pt-settings-body">${toggle("online_enabled", "Online Test", "Timed test through a secure applicant link.", "device-laptop")}${toggle("paper_enabled", "Printed Test", "Printable questions and answer sheet.", "printer")}${toggle("manual_enabled", "Manual Entry", "Examiner enters paper responses manually.", "keyboard")}${toggle("scan_enabled", "Answer-Sheet Scanning", "Read printed multiple-choice sheets locally.", "scan")}</div></section><section class="pt-settings-panel"><header><span><i class="ti ti-adjustments"></i></span><div><h3>Assessment Defaults</h3><p>Starting values for newly created test forms.</p></div></header><div class="pt-settings-body pt-settings-fields"><label>Maximum Attempts<input class="form-control" name="max_attempts" type="number" min="1" max="10" value="${esc(s.max_attempts || 3)}" required></label><label>Default Duration (minutes)<input class="form-control" name="default_duration_minutes" type="number" min="5" max="360" value="${esc(s.default_duration_minutes || 60)}" required></label><label>Default Maximum Score<input class="form-control" name="default_max_score" type="number" min="1" max="1000" step=".01" value="${esc(s.default_max_score || 100)}" required></label><label>Default Pass Percentage<input class="form-control" name="default_pass_percentage" type="number" min="0" max="100" step=".01" value="${esc(s.default_pass_percentage || 60)}" required></label><label>Online Grace Period (minutes)<input class="form-control" name="online_grace_minutes" type="number" min="0" max="30" value="${esc(s.online_grace_minutes ?? 2)}" required></label></div></section><section class="pt-settings-panel"><header><span><i class="ti ti-shield-check"></i></span><div><h3>Result & Enrollment Controls</h3><p>Control what applicants see and when enrollment can begin.</p></div></header><div class="pt-settings-body">${toggle("show_result_to_candidate", "Show Result to Candidate", "Display score and pass/fail after online submission.", "report-analytics")}${toggle("enrollment_handoff_enabled", "Enrollment Handoff", "Allow verified passed applicants into Student Enrollment.", "school")}<label class="pt-settings-textarea">Candidate Instructions<textarea class="form-control" name="candidate_instructions" rows="4" placeholder="General instructions shown to placement-test candidates">${esc(s.candidate_instructions || "")}</textarea></label></div></section><section class="pt-settings-panel"><header><span><i class="ti ti-history"></i></span><div><h3>Recent Activity</h3><p>Latest audited Placement Test actions.</p></div></header><div class="pt-settings-body"><ul class="pt-activity-list">${activity || `<li><span class="pt-activity-icon"><i class="ti ti-info-circle"></i></span><span><strong>No activity yet</strong><small>Saved changes and test actions will appear here.</small></span></li>`}</ul></div></section></div></form>`;
    };

    const masterGrades = (item) => (item.grades || []).map((grade) => grade.id);
    const masterFilters = (kind) =>
        `<div class="pt-master-filters"><label><i class="ti ti-search"></i><input class="form-control" data-master-search data-master-kind="${kind}" placeholder="Search code or name"></label><select class="form-select" data-master-filter data-master-kind="${kind}"><option value="">All grades</option>${options(payload.meta.grades || [], "", (grade) => grade.grade_short_name || grade.grade)}</select><select class="form-select" data-master-status data-master-filter data-master-kind="${kind}"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>`;
    const masterDataHtml = () => {
        const subjects = payload.subjects || [],
            topics = payload.topics || [];
        const subjectRows = subjects.map(
            (subject) =>
                `<tr data-master-row data-kind="subject" data-search="${esc(`${subject.code} ${subject.name_en} ${subject.name_kh}`.toLowerCase())}" data-grades="${masterGrades(subject).join(",")}" data-status="${subject.status}"><td data-label="Subject"><span class="pt-stack"><strong>${esc(subject.code)} · ${esc(subject.name_en)}</strong><small>${esc(subject.name_kh)}</small></span></td><td data-label="Grades">${(subject.grades || []).map((grade) => `<span class="pt-badge active">${esc(grade.grade_short_name || grade.grade)}</span>`).join(" ")}</td><td data-label="Order">${esc(subject.display_order)}</td><td data-label="Topics">${esc(subject.topics_count || 0)}</td><td data-label="Status">${badge(subject.status)}</td><td data-label="Actions"><div class="pt-row-actions">${button("Edit", "edit", `data-edit-subject="${subject.id}"`)}${button(subject.status === "active" ? "Deactivate" : "Activate", subject.status === "active" ? "toggle-left" : "toggle-right", `data-toggle-subject="${subject.id}" data-status="${subject.status === "active" ? "inactive" : "active"}`, subject.status === "active" ? "outline-warning" : "outline-success")}</div></td></tr>`,
        );
        const topicRows = topics.map(
            (topic) =>
                `<tr data-master-row data-kind="topic" data-search="${esc(`${topic.code} ${topic.name_en} ${topic.name_kh} ${topic.subject?.name_en || ""}`.toLowerCase())}" data-grades="${masterGrades(topic).join(",")}" data-status="${topic.status}"><td data-label="Topic"><span class="pt-stack"><strong>${esc(topic.code)} · ${esc(topic.name_en)}</strong><small>${esc(topic.name_kh)}</small></span></td><td data-label="Subject">${esc(topic.subject?.name_en || "—")}</td><td data-label="Grades">${(topic.grades || []).map((grade) => `<span class="pt-badge active">${esc(grade.grade_short_name || grade.grade)}</span>`).join(" ")}</td><td data-label="Difficulty">${topic.difficulty ? badge(topic.difficulty) : "—"}</td><td data-label="Order">${esc(topic.display_order)}</td><td data-label="Status">${badge(topic.status)}</td><td data-label="Actions"><div class="pt-row-actions">${button("Edit", "edit", `data-edit-topic="${topic.id}"`)}${button(topic.status === "active" ? "Deactivate" : "Activate", topic.status === "active" ? "toggle-left" : "toggle-right", `data-toggle-topic="${topic.id}" data-status="${topic.status === "active" ? "inactive" : "active"}`, topic.status === "active" ? "outline-warning" : "outline-success")}</div></td></tr>`,
        );
        return `<div class="pt-master-management"><section class="pt-master-section"><header><div><span class="pt-content-kicker">QUESTION BANK MASTER DATA</span><h3>Subject Management</h3><p>Add subjects, assign grades, control order, and manage availability.</p></div>${button("Add Subject", "plus", "data-add-subject", "primary")}</header>${masterFilters("subject")}<div class="pt-table-wrap"><table class="pt-table"><thead><tr><th>Subject</th><th>Applicable Grades</th><th>Order</th><th>Topics</th><th>Status</th><th>Actions</th></tr></thead><tbody>${subjectRows.join("") || `<tr><td colspan="6">${empty("No subjects configured", "Add the first placement-test subject.", "books")}</td></tr>`}</tbody></table></div></section><section class="pt-master-section"><header><div><span class="pt-content-kicker">CURRICULUM STRUCTURE</span><h3>Curriculum Topics</h3><p>Connect topics to a subject and one or more applicable grades.</p></div>${button("Add Topic", "plus", "data-add-topic", "primary")}</header>${masterFilters("topic")}<div class="pt-table-wrap"><table class="pt-table"><thead><tr><th>Topic</th><th>Subject</th><th>Applicable Grades</th><th>Difficulty</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>${topicRows.join("") || `<tr><td colspan="7">${empty("No curriculum topics configured", "Add a subject first, then create curriculum topics.", "list-details")}</td></tr>`}</tbody></table></div></section></div>`;
    };
    const applyMasterFilters = (kind) => {
        const search =
                document
                    .querySelector(
                        `[data-master-search][data-master-kind="${kind}"]`,
                    )
                    ?.value.trim()
                    .toLowerCase() || "",
            grade =
                document.querySelector(
                    `[data-master-filter][data-master-kind="${kind}"]:not([data-master-status])`,
                )?.value || "",
            status =
                document.querySelector(
                    `[data-master-status][data-master-kind="${kind}"]`,
                )?.value || "";
        document
            .querySelectorAll(`[data-master-row][data-kind="${kind}"]`)
            .forEach((row) => {
                const grades = (row.dataset.grades || "").split(",");
                row.hidden = !!(
                    (search && !row.dataset.search.includes(search)) ||
                    (grade && !grades.includes(grade)) ||
                    (status && row.dataset.status !== status)
                );
            });
    };
    const renderSettingsPage = () => {
        renderSettings();
        actions.innerHTML =
            button(
                "Add Subject",
                "books",
                "data-add-subject",
                "outline-primary",
            ) +
            button(
                "Add Topic",
                "list-details",
                "data-add-topic",
                "outline-primary",
            ) +
            button(
                "Save Settings",
                "device-floppy",
                "data-save-settings",
                "primary",
            );
        content.insertAdjacentHTML("afterbegin", masterDataHtml());
    };
    const gradeChecklist = (selected = []) =>
        `<div class="pt-grade-checks">${(payload.meta.grades || []).map((grade) => `<label data-topic-grade="${grade.id}"><input class="form-check-input" type="checkbox" name="grade_ids" value="${grade.id}" ${selected.map(String).includes(String(grade.id)) ? "checked" : ""}><span>${esc(grade.grade_short_name || grade.grade)}</span></label>`).join("")}</div>`;
    const openEditor = (
        title,
        html,
        handler,
        kicker = "PLACEMENT TEST",
        variant = "",
    ) => {
        editorEl.classList.toggle(
            "pt-applicant-editor",
            variant === "applicant",
        );
        editorEl.classList.toggle("pt-question-editor", variant === "question");
        $("#ptModalTitle").textContent = title;
        $("#ptModalKicker").textContent = kicker;
        $("#ptModalBody").innerHTML = html;
        editorHandler = handler;
        editor.show();
    };
    const options = (items, selected = "", text = (x) => x.name) =>
        items
            .map(
                (x) =>
                    `<option value="${x.id}" ${String(x.id) === String(selected) ? "selected" : ""}>${esc(text(x))}</option>`,
            )
            .join("");
    const field = (name, label, type = "text", value = "", extra = "") =>
        `<label>${label}<input class="form-control" type="${type}" name="${name}" value="${esc(value)}" ${extra}></label>`;
    const subjectEditor = (id) => {
        const subject = (payload.subjects || []).find(
            (item) => item.id === id,
        ) || {
            status: "active",
            display_order: (payload.subjects || []).length + 1,
            grades: [],
        };
        openEditor(
            id ? "Edit Subject" : "Add Subject",
            `<div class="pt-form-grid">${field("code", "Subject Code", "text", subject.code || "", "required maxlength=40")}${field("display_order", "Display Order", "number", subject.display_order ?? 0, 'required min="0" max="9999"')}${field("name_en", "Subject Name (English)", "text", subject.name_en || "", "required maxlength=160")}${field("name_kh", "Subject Name (Khmer)", "text", subject.name_kh || "", "required maxlength=160")}<label>Status<select class="form-select" name="status"><option value="active" ${subject.status === "active" ? "selected" : ""}>Active</option><option value="inactive" ${subject.status === "inactive" ? "selected" : ""}>Inactive</option></select></label><label class="full">Applicable Grade Levels${gradeChecklist(masterGrades(subject))}</label><label class="full">Description <span class="text-secondary">(optional)</span><textarea class="form-control" name="description" rows="3">${esc(subject.description || "")}</textarea></label></div>`,
            (data) => {
                data.grade_ids = [
                    ...document.querySelectorAll(
                        '#ptEditorForm [name="grade_ids"]:checked',
                    ),
                ].map((input) => Number(input.value));
                return api(
                    id
                        ? `/students/placement-tests/settings/subjects/${id}`
                        : "/students/placement-tests/settings/subjects",
                    { method: "POST", body: data },
                );
            },
            "SUBJECT MANAGEMENT",
        );
    };
    const syncTopicGradeChoices = (reset = true) => {
        const subjectId = document.querySelector("[data-topic-subject]")?.value,
            subject = (payload.subjects || []).find(
                (item) => String(item.id) === String(subjectId),
            ),
            allowed = new Set(masterGrades(subject || {}).map(String));
        document.querySelectorAll("[data-topic-grade]").forEach((label) => {
            const input = label.querySelector("input"),
                available = allowed.has(String(input.value));
            label.hidden = !available;
            input.disabled = !available;
            if (!available || reset) input.checked = false;
        });
    };
    const topicEditor = (id) => {
        const topic = (payload.topics || []).find((item) => item.id === id) || {
            status: "active",
            display_order: (payload.topics || []).length + 1,
            grades: [],
        };
        const subjects = payload.subjects || [];
        openEditor(
            id ? "Edit Curriculum Topic" : "Add Curriculum Topic",
            `<div class="pt-form-grid">${field("code", "Topic Code", "text", topic.code || "", "required maxlength=50")}${field("display_order", "Display Order", "number", topic.display_order ?? 0, 'required min="0" max="9999"')}<label>Subject<select class="form-select" name="subject_id" data-topic-subject required><option value="">Select subject</option>${options(subjects, topic.subject_id, (subject) => `${subject.code} · ${subject.name_en}`)}</select></label><label>Difficulty Level <span class="text-secondary">(optional)</span><select class="form-select" name="difficulty"><option value="">Not specified</option><option value="easy" ${topic.difficulty === "easy" ? "selected" : ""}>Easy</option><option value="medium" ${topic.difficulty === "medium" ? "selected" : ""}>Medium</option><option value="difficult" ${topic.difficulty === "difficult" ? "selected" : ""}>Difficult</option></select></label>${field("name_en", "Topic Name (English)", "text", topic.name_en || "", "required maxlength=180")}${field("name_kh", "Topic Name (Khmer)", "text", topic.name_kh || "", "required maxlength=180")}<label>Status<select class="form-select" name="status"><option value="active" ${topic.status === "active" ? "selected" : ""}>Active</option><option value="inactive" ${topic.status === "inactive" ? "selected" : ""}>Inactive</option></select></label><label class="full">Applicable Grade Levels${gradeChecklist(masterGrades(topic))}</label><label class="full">Description <span class="text-secondary">(optional)</span><textarea class="form-control" name="description" rows="3">${esc(topic.description || "")}</textarea></label></div>`,
            (data) => {
                data.grade_ids = [
                    ...document.querySelectorAll(
                        '#ptEditorForm [name="grade_ids"]:checked',
                    ),
                ].map((input) => Number(input.value));
                return api(
                    id
                        ? `/students/placement-tests/settings/topics/${id}`
                        : "/students/placement-tests/settings/topics",
                    { method: "POST", body: data },
                );
            },
            "CURRICULUM TOPIC MANAGEMENT",
        );
        setTimeout(() => syncTopicGradeChoices(false));
    };
    const assignedToGrade = (item, gradeId) =>
        (item.grades || []).some(
            (grade) => String(grade.id) === String(gradeId),
        );
    const syncQuestionTopics = (reset = false) => {
        const gradeId = document.querySelector("[data-question-grade]")?.value,
            subjectId = document.querySelector(
                "[data-question-subject]",
            )?.value,
            select = document.querySelector("[data-question-topic]");
        if (!select) return;
        const selected = reset
            ? ""
            : select.value || select.dataset.selected || "";
        const topics = (payload.meta.topics || []).filter(
            (topic) =>
                String(topic.subject_id) === String(subjectId) &&
                assignedToGrade(topic, gradeId),
        );
        select.innerHTML =
            '<option value="">Select curriculum topic</option>' +
            options(
                topics,
                selected,
                (topic) =>
                    `${topic.code} · ${topic.name_en}${topic.name_kh ? ` · ${topic.name_kh}` : ""}`,
            );
        select.value = topics.some(
            (topic) => String(topic.id) === String(selected),
        )
            ? String(selected)
            : "";
        delete select.dataset.selected;
    };
    const syncQuestionSubjects = (reset = false) => {
        const gradeId = document.querySelector("[data-question-grade]")?.value,
            select = document.querySelector("[data-question-subject]");
        if (!select) return;
        const selected = reset
            ? ""
            : select.value || select.dataset.selected || "";
        const subjects = (payload.meta.subjects || []).filter((subject) =>
            assignedToGrade(subject, gradeId),
        );
        select.innerHTML =
            '<option value="">Select subject</option>' +
            options(
                subjects,
                selected,
                (subject) =>
                    `${subject.code} · ${subject.name_en}${subject.name_kh ? ` · ${subject.name_kh}` : ""}`,
            );
        select.value = subjects.some(
            (subject) => String(subject.id) === String(selected),
        )
            ? String(selected)
            : "";
        delete select.dataset.selected;
        syncQuestionTopics(reset);
    };
    const applicantEditor = (id) => {
        const a = (payload.applicants || []).find((x) => x.id === id) || {};
        openEditor(
            id ? "Edit Applicant" : "Register Applicant",
            `<div class="pt-form-grid"><label>Academic Year<select class="form-select" name="academic_year_id" required><option value="">Select academic year</option>${options(payload.meta.academic_years || [], a.academic_year_id, (x) => x.academic_year)}</select></label><label>Campus<select class="form-select" name="campus_id" required><option value="">Select campus</option>${options(payload.meta.campuses || [], a.campus_id, (x) => x.campus_name_en)}</select></label><label>Target Grade<select class="form-select" name="target_grade_id" required><option value="">Select grade</option>${options(payload.meta.grades || [], a.target_grade_id, (x) => x.grade_short_name || x.grade)}</select></label><label>Gender<select class="form-select" name="gender"><option value="">Select gender</option><option ${["Male", "male", "ប", "ប្រុស"].includes(a.gender) ? "selected" : ""}>Male</option><option ${["Female", "female", "ស", "ស្រី"].includes(a.gender) ? "selected" : ""}>Female</option></select></label>${field("full_name_kh", "Student Name (Khmer)", "text", a.full_name_kh, "required")}${field("full_name_en", "Student Name (English)", "text", a.full_name_en, "required")}${field("date_of_birth", "Date of Birth", "date", a.date_of_birth || "")}${field("phone", "Phone Number", "tel", a.phone || "")}${field("email", "Email", "email", a.email || "")}${field("previous_school", "Previous School", "text", a.previous_school || "")}${field("parent_name", "Parent / Guardian", "text", a.parent_name || "")}${field("parent_phone", "Parent Phone", "tel", a.parent_phone || "")}<label class="full">Notes<textarea class="form-control" name="notes" rows="3">${esc(a.notes || "")}</textarea></label></div>`,
            async (data) =>
                api(
                    id
                        ? `/students/placement-tests/applicants/${id}`
                        : "/students/placement-tests/applicants",
                    { method: "POST", body: data },
                ),
            "ADMISSIONS APPLICANT",
            "applicant",
        );
    };
    const questionEditor = (id) => {
        const q = (payload.questions || []).find((x) => x.id === id) || {
            difficulty: "medium",
            type: "multiple_choice",
            status: "draft",
            default_points: 1,
            option_label_style: "letters",
            options: ["", "", "", ""],
        };
        q.option_label_style ||= "letters";
        const choices =
            q.type === "true_false"
                ? ["True", "False"]
                : q.options?.length
                  ? q.options
                  : ["", ""];
        openEditor(
            id ? "Edit Question" : "Add Question",
            `<div class="pt-form-grid"><label>Grade<select class="form-select" name="grade_id" data-question-grade required><option value="">Select grade</option>${options(payload.meta.grades || [], q.grade_id, (x) => x.grade_short_name || x.grade)}</select></label><label>Subject<select class="form-select" name="subject_id" data-question-subject data-selected="${esc(q.subject_id || "")}" required><option value="">Select grade first</option></select></label><label>Curriculum Topic<select class="form-select" name="topic_id" data-question-topic data-selected="${esc(q.topic_id || "")}" required><option value="">Select subject first</option></select></label><label>Difficulty<select class="form-select" name="difficulty"><option ${q.difficulty === "easy" ? "selected" : ""}>easy</option><option ${q.difficulty === "medium" ? "selected" : ""}>medium</option><option ${q.difficulty === "difficult" ? "selected" : ""}>difficult</option></select></label><label>Question Type<select class="form-select" name="type" data-question-type>${questionTypes.map(([value, label]) => `<option value="${value}" ${q.type === value ? "selected" : ""}>${label}</option>`).join("")}</select></label><label data-option-style-field>Option Labels<select class="form-select" name="option_label_style" data-option-style><option value="letters" ${q.option_label_style === "letters" ? "selected" : ""}>Letters (A, B, C…)</option><option value="numbers" ${q.option_label_style === "numbers" ? "selected" : ""}>Numbers (1, 2, 3…)</option></select></label>${field("default_points", "Maximum Points", "number", q.default_points || 1, 'step="0.01" min="0.01" required')}<label class="full">Question Prompt<textarea class="form-control" name="prompt" rows="4" required>${esc(q.prompt || "")}</textarea></label><div class="full" data-choice-panel><div class="d-flex align-items-center justify-content-between gap-2"><label>Answer Options</label><button class="btn btn-sm btn-outline-primary" type="button" data-add-option><i class="ti ti-plus"></i> Add Option</button></div><div id="ptOptions">${choices.map((value, index) => optionRow(value, index, q.correct_answer, q.option_label_style)).join("")}</div><small class="text-secondary">Add 2–26 choices and select the radio button for the correct answer. Printed scan sheets support lettered A–D choices.</small></div><label class="full" data-written-panel><span data-written-answer-label>Model Answer</span><textarea class="form-control" name="correct_answer" data-written-answer rows="4">${esc(isChoiceQuestion(q.type) ? "" : q.correct_answer || "")}</textarea><small class="text-secondary" data-written-help>Examiners use this model answer while marking the response.</small></label><label>Status<select class="form-select" name="status"><option ${q.status === "draft" ? "selected" : ""}>draft</option><option ${q.status === "review" ? "selected" : ""}>review</option><option ${q.status === "approved" ? "selected" : ""}>approved</option><option ${q.status === "archived" ? "selected" : ""}>archived</option></select></label><label class="full">Explanation / Marking Guidance<textarea class="form-control" name="explanation" rows="3">${esc(q.explanation || "")}</textarea></label></div>`,
            async (data) => {
                data.options = [
                    ...document.querySelectorAll(
                        '#ptOptions [name="option"]:not(:disabled)',
                    ),
                ].map((x) => x.value);
                return api(
                    id
                        ? `/students/placement-tests/questions/${id}`
                        : "/students/placement-tests/questions",
                    { method: "POST", body: data },
                );
            },
            "QUESTION BANK",
            "question",
        );
        setTimeout(() => {
            syncQuestionType();
            syncQuestionSubjects(false);
        });
    };
    const formEditor = (id) => {
        const f =
            (payload.forms || []).find((x) => x.id === id) ||
            (() => {
                const d = payload.meta?.defaults || {};
                const max = Number(d.default_max_score || 100);
                return {
                    form_code: "A",
                    revision: 1,
                    duration_minutes: Number(d.default_duration_minutes || 60),
                    max_score: max,
                    pass_score: Number(
                        (
                            (max * Number(d.default_pass_percentage || 60)) /
                            100
                        ).toFixed(2),
                    ),
                    subject_rules: [],
                    instructions: d.candidate_instructions || "",
                };
            })();
        openEditor(
            id ? "Edit Test Form" : "Create Test Form",
            `<div class="pt-form-grid"><label>Grade<select class="form-select" name="grade_id" required>${options(payload.meta.grades || [], f.grade_id, (x) => x.grade_short_name || x.grade)}</select></label>${field("name", "Test Name", "text", f.name || "Placement Test", "required")}<label>Version<select class="form-select" name="form_code"><option ${f.form_code === "A" ? "selected" : ""}>A</option><option ${f.form_code === "B" ? "selected" : ""}>B</option><option ${f.form_code === "C" ? "selected" : ""}>C</option></select></label>${field("revision", "Revision", "number", f.revision || 1, 'min="1" required')}${field("duration_minutes", "Duration (minutes)", "number", f.duration_minutes || 60, 'min="5" required')}${field("max_score", "Maximum Score", "number", f.max_score || 100, 'step="0.01" min="1" required')}${field("pass_score", "Overall Pass Score", "number", f.pass_score || 60, 'step="0.01" min="0" required')}<label class="full">Candidate Instructions<textarea class="form-control" name="instructions" rows="3">${esc(f.instructions || "")}</textarea></label><div class="full"><div class="d-flex justify-content-between align-items-center"><label>Subject Passing Requirements</label><button class="btn btn-sm btn-outline-primary" type="button" data-add-rule><i class="ti ti-plus"></i> Subject</button></div><div id="ptRules">${(f.subject_rules || []).map(ruleRow).join("")}</div><small class="text-secondary">An applicant must meet the overall score and every required subject minimum.</small></div></div>`,
            async (data) => {
                data.subject_rules = [
                    ...document.querySelectorAll("#ptRules .pt-rule-row"),
                ]
                    .map((r) => ({
                        subject: r.querySelector('[name="rule_subject"]').value,
                        max_score: r.querySelector('[name="rule_max"]').value,
                        pass_score: r.querySelector('[name="rule_pass"]').value,
                        required: true,
                    }))
                    .filter((x) => x.subject);
                return api(
                    id
                        ? `/students/placement-tests/forms/${id}`
                        : "/students/placement-tests/forms",
                    { method: "POST", body: data },
                );
            },
            "TEST FORM DESIGN",
        );
    };
    const ruleRow = (r = { subject: "", max_score: "", pass_score: "" }) =>
        `<div class="pt-rule-row"><input class="form-control" name="rule_subject" placeholder="Subject" value="${esc(r.subject)}"><input class="form-control" name="rule_max" type="number" step=".01" placeholder="Max" value="${esc(r.max_score)}"><input class="form-control" name="rule_pass" type="number" step=".01" placeholder="Pass" value="${esc(r.pass_score)}"><button class="btn btn-icon btn-outline-danger" type="button" data-remove-rule><i class="ti ti-x"></i></button></div>`;
    const questionPicker = (id) => {
        const f = (payload.forms || []).find((x) => x.id === id),
            selected = new Map(
                (f.questions || []).map((q) => [
                    q.id,
                    q.pivot?.points || q.default_points,
                ]),
            );
        const list = (payload.questions || []).filter(
            (q) => q.grade_id === f.grade_id,
        );
        openAction(
            `Assign Questions · Form ${f.form_code}`,
            `<div class="pt-question-picker">${list.map((q) => `<label class="pt-question-option"><input class="form-check-input" type="checkbox" name="question" value="${q.id}" ${selected.has(q.id) ? "checked" : ""}><span><strong>${esc(q.subject)} · ${esc(q.prompt)}</strong><small>${esc(q.difficulty)} · ${esc(q.topic || "General")}</small></span><input class="form-control" name="points_${q.id}" type="number" step=".01" min=".01" value="${selected.get(q.id) || q.default_points}"></label>`).join("") || empty("No approved questions", "Approve questions for this grade before assigning them.")}</div>`,
            async () => {
                const questions = [
                    ...document.querySelectorAll('[name="question"]:checked'),
                ].map((x, i) => ({
                    question_id: +x.value,
                    points: +document.querySelector(
                        `[name="points_${x.value}"]`,
                    ).value,
                    order_no: i + 1,
                }));
                return api(`/students/placement-tests/forms/${id}/questions`, {
                    method: "POST",
                    body: { questions },
                });
            },
            "Save Question Set",
        );
    };
    const assignTest = (id) => {
        const a = (payload.applicants || []).find((x) => x.id === id),
            forms = (payload.meta.published_forms || []).filter(
                (f) => f.grade_id === a.target_grade_id,
            ),
            settings = payload.meta?.defaults || {};
        const methods = [
            ["online", "Online timed test", "online_enabled"],
            [
                "paper",
                "Printed question and scannable answer sheet",
                "paper_enabled",
            ],
            [
                "manual",
                "Printed test with manual answer entry",
                "manual_enabled",
            ],
        ].filter(([, , flag]) => settings[flag] !== false);
        if (!methods.length)
            return notify(
                "warning",
                "No delivery method available",
                "Enable at least one delivery channel in Placement Test Settings.",
            );
        openAction(
            `Assign Test · ${a.full_name_en}`,
            `<div class="pt-form-grid"><label class="full">Published Test Form<select class="form-select" name="form_id" required><option value="">Select a published form</option>${options(forms, "", (x) => `${x.grade?.grade_short_name || x.grade?.grade} · Form ${x.form_code} · ${x.name}`)}</select></label><label class="full">Delivery Method<select class="form-select" name="method" required>${methods.map(([value, label]) => `<option value="${value}">${label}</option>`).join("")}</select></label><div class="full pt-scan-banner"><strong>Professional workflow:</strong> Published questions are copied into an immutable attempt snapshot, so later question-bank edits cannot change this applicant's examination.</div></div>`,
            (data) =>
                api(`/students/placement-tests/applicants/${id}/assign`, {
                    method: "POST",
                    body: data,
                }),
            "Assign Placement Test",
        );
    };
    const openAction = (title, html, handler, label = "Confirm") => {
        $("#ptActionTitle").textContent = title;
        $("#ptActionBody").innerHTML = html;
        $("#ptActionSubmit").innerHTML = `<i class="ti ti-check"></i> ${label}`;
        actionHandler = handler;
        actionModal.show();
    };
    const findAttempt = (id) =>
        (payload.attempts || payload.recent_attempts || []).find(
            (x) => x.id === id,
        );
    const scoreAttempt = (id) => {
        const a = findAttempt(id),
            questions = a?.snapshot?.questions || [],
            answers = new Map(
                (a?.answers || []).map((answer) => [
                    answer.question_order,
                    answer,
                ]),
            );
        const control = (q) => {
            const answer = answers.get(q.order_no)?.answer || "",
                type = q.type || "multiple_choice";
            if (isChoiceQuestion(type))
                return `<select class="form-select" name="answer_${q.order_no}"><option value="">Blank</option>${optionLabels(
                    q,
                )
                    .map(
                        (label) =>
                            `<option ${answer === label ? "selected" : ""}>${label}</option>`,
                    )
                    .join("")}</select>`;
            if (type === "fill_blank")
                return `<input class="form-control" name="answer_${q.order_no}" value="${esc(answer)}" placeholder="Student answer">`;
            return `<textarea class="form-control" name="answer_${q.order_no}" rows="${type === "essay" ? 5 : 3}" placeholder="Student's written response">${esc(answer)}</textarea><div class="pt-manual-score"><span>Model answer: ${esc(q.correct_answer || "Not provided")}</span><label>Points awarded <input class="form-control" name="score_${q.order_no}" type="number" min="0" max="${esc(q.points)}" step="0.01" value="${esc(answers.get(q.order_no)?.flag === "manual_reviewed" ? answers.get(q.order_no)?.awarded_points : "")}" required></label></div>`;
        };
        openAction(
            `Mark Responses · ${a.applicant.full_name_en}`,
            `<div class="pt-scan-banner mb-3">Choice and fill-in answers are marked automatically. Enter awarded points for Short Answer and Essay questions.</div><div class="pt-score-grid">${questions.map((q) => `<div class="pt-score-cell ${["short_answer", "essay"].includes(q.type) ? "pt-score-written" : ""}"><label><span>Q${q.order_no} · ${esc(String(q.type || "multiple_choice").replaceAll("_", " "))}</span><span>${esc(q.subject)} · ${q.points} pt</span></label><p>${esc(q.prompt)}</p>${control(q)}</div>`).join("")}</div>`,
            () => {
                const responses = {},
                    scores = {};
                questions.forEach((q) => {
                    responses[q.order_no] = document.querySelector(
                        `[name="answer_${q.order_no}"]`,
                    ).value;
                    const score = document.querySelector(
                        `[name="score_${q.order_no}"]`,
                    );
                    if (score) scores[q.order_no] = score.value;
                });
                return api(
                    `/students/placement-tests/attempts/${id}/manual-score`,
                    { method: "POST", body: { responses, scores } },
                );
            },
            "Save Marks & Verify",
        );
    };
    const scanAttempt = (id) => {
        openAction(
            "Scan Printed Answer Sheet",
            `<div class="pt-form-grid"><label class="full">Answer Sheet Image<input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label><div class="full pt-scan-banner">Upload a clear, straight photo or scan of the complete A4 answer sheet. Keep all four black corner markers visible.</div></div>`,
            async (data) => {
                const form = new FormData();
                form.append(
                    "image",
                    document.querySelector('#ptActionBody [name="image"]')
                        .files[0],
                );
                const result = await api(
                    `/students/placement-tests/attempts/${id}/scan`,
                    { method: "POST", body: form },
                );
                setTimeout(() => reviewScan(result.scan, id), 350);
                return result;
            },
            "Analyze Answer Sheet",
        );
    };
    const reviewScan = (scan, attemptId) => {
        const a = findAttempt(attemptId),
            questions = a?.snapshot?.questions || [],
            detected = scan.detected || [],
            flags = scan.flags || {};
        openAction(
            "Review Detected Answers",
            `<div class="pt-scan-banner mb-3">${Object.keys(flags).length ? `<strong>${Object.keys(flags).length} answer(s) need attention.</strong> Check blanks, multiple marks and unclear bubbles.` : "All marks were detected clearly. Confirm the responses to calculate the result."}</div><div class="pt-score-grid">${questions
                .map(
                    (q) =>
                        `<div class="pt-score-cell ${flags[q.order_no] ? "border-danger" : ""}"><label><span>Q${q.order_no}</span><span class="text-${flags[q.order_no] ? "danger" : "secondary"}">${esc(flags[q.order_no] || "clear")}</span></label><select class="form-select" name="scan_${q.order_no}"><option value="">Blank</option>${optionLabels(
                            q,
                        )
                            .map(
                                (x) =>
                                    `<option ${detected[q.order_no - 1] === x ? "selected" : ""}>${x}</option>`,
                            )
                            .join("")}</select></div>`,
                )
                .join(
                    "",
                )}</div><label class="d-block mt-3">Correction reason (required when changing detected answers)<textarea class="form-control" name="reason" rows="2"></textarea></label>`,
            () => {
                const responses = {};
                questions.forEach(
                    (q) =>
                        (responses[q.order_no] = document.querySelector(
                            `[name="scan_${q.order_no}"]`,
                        ).value),
                );
                return api(
                    `/students/placement-tests/scans/${scan.id}/verify`,
                    {
                        method: "POST",
                        body: {
                            responses,
                            reason: document.querySelector('[name="reason"]')
                                .value,
                        },
                    },
                );
            },
            "Verify & Calculate Result",
        );
    };
    const detailRow = (label, value) =>
        `<div class="pt-detail-row"><span>${esc(label)}</span><strong>${value ?? "—"}</strong></div>`;
    const viewDialog = (title, html) =>
        Swal.fire({
            title,
            html,
            width: 760,
            confirmButtonText: "Close",
            confirmButtonColor: "#316fd6",
            customClass: { popup: "pt-detail-dialog" },
        });
    const viewApplicant = (id) => {
        const a = (payload.applicants || []).find((item) => item.id === id);
        if (!a) return;
        viewDialog(
            "Applicant Record",
            `<div class="pt-detail-person">${person(a)}</div><div class="pt-detail-grid">${detailRow("Application Code", esc(a.application_code))}${detailRow("Academic Year", esc(val(a, "academic_year.academic_year")))}${detailRow("Campus", esc(val(a, "campus.campus_name_en")))}${detailRow("Target Grade", esc(val(a, "target_grade.grade_short_name", val(a, "target_grade.grade"))))}${detailRow("Gender", esc(a.gender || "—"))}${detailRow("Date of Birth", esc(a.date_of_birth || "—"))}${detailRow("Phone", esc(a.phone || "—"))}${detailRow("Email", esc(a.email || "—"))}${detailRow("Parent / Guardian", esc(a.parent_name || "—"))}${detailRow("Parent Phone", esc(a.parent_phone || "—"))}${detailRow("Previous School", esc(a.previous_school || "—"))}${detailRow("Status", badge(a.status))}</div>${a.notes ? `<div class="pt-detail-note"><strong>Notes</strong><p>${esc(a.notes)}</p></div>` : ""}`,
        );
    };
    const viewQuestion = (id) => {
        const q = (payload.questions || []).find((item) => item.id === id);
        if (!q) return;
        const labels = optionLabels(q),
            answers = isChoiceQuestion(q.type)
                ? (q.options || [])
                      .map(
                          (option, index) =>
                              `<div class="pt-answer-option ${q.correct_answer === labels[index] ? "correct" : ""}"><b>${labels[index]}</b><span>${esc(option)}</span>${q.correct_answer === labels[index] ? '<i class="ti ti-check"></i>' : ""}</div>`,
                      )
                      .join("")
                : `<div class="pt-detail-note"><strong>${q.type === "fill_blank" ? "Accepted Answer" : "Model Answer"}</strong><p>${esc(q.correct_answer || "No model answer provided. Use the marking guidance and examiner judgment.")}</p></div>`;
        viewDialog(
            `Question Q${q.id}`,
            `<div class="pt-detail-question"><span>${esc(q.subject)} · ${esc(q.topic || "General")}</span><h4>${esc(q.prompt)}</h4>${answers}</div><div class="pt-detail-grid mt-3">${detailRow("Grade", esc(val(q, "grade.grade_short_name", val(q, "grade.grade"))))}${detailRow("Question Type", esc(questionTypes.find(([value]) => value === q.type)?.[1] || q.type))}${detailRow("Difficulty", badge(q.difficulty))}${detailRow("Points", esc(q.default_points))}${detailRow("Status", badge(q.status))}</div>${q.explanation ? `<div class="pt-detail-note"><strong>Explanation / Marking Guidance</strong><p>${esc(q.explanation)}</p></div>` : ""}`,
        );
    };
    const viewForm = (id) => {
        const f = (payload.forms || []).find((item) => item.id === id);
        if (!f) return;
        viewDialog(
            `${esc(f.name)} · Form ${esc(f.form_code)}`,
            `<div class="pt-detail-grid">${detailRow("Grade", esc(val(f, "grade.grade_short_name", val(f, "grade.grade"))))}${detailRow("Revision", esc(f.revision))}${detailRow("Duration", `${esc(f.duration_minutes)} minutes`)}${detailRow("Maximum / Pass", `${esc(f.max_score)} / ${esc(f.pass_score)}`)}${detailRow("Questions", esc(f.questions?.length || 0))}${detailRow("Status", badge(f.status))}</div><div class="pt-detail-note"><strong>Subject Requirements</strong>${(f.subject_rules || []).map((rule) => `<p>${esc(rule.subject)}: ${esc(rule.pass_score)} / ${esc(rule.max_score)}</p>`).join("") || "<p>No subject-level requirements.</p>"}</div><div class="pt-detail-note"><strong>Instructions</strong><p>${esc(f.instructions || "No instructions entered.")}</p></div>`,
        );
    };
    const viewAttempt = (id) => {
        const a = findAttempt(id);
        if (!a) return;
        const answers = new Map(
            (a.answers || []).map((answer) => [answer.question_order, answer]),
        );
        viewDialog(
            `Test Result · ${esc(a.applicant?.full_name_en || "")}`,
            `<div class="pt-detail-grid">${detailRow("Test Form", `Form ${esc(val(a, "form.form_code"))} · ${esc(val(a, "form.grade.grade_short_name", val(a, "form.grade.grade")))}`)}${detailRow("Attempt", esc(a.attempt_no))}${detailRow("Method", esc(a.method))}${detailRow("Status", badge(a.status))}${detailRow("Score", a.score == null ? "—" : `${esc(a.score)} / ${esc(a.max_score)} (${esc(a.percentage)}%)`)}${detailRow("Result", badge(a.result))}</div><div class="pt-result-review">${
                (a.snapshot?.questions || [])
                    .map((q) => {
                        const answer = answers.get(q.order_no);
                        return `<div class="pt-result-answer ${answer?.is_correct ? "correct" : "incorrect"}"><span>Q${q.order_no}</span><div><strong>${esc(q.subject)} · ${esc(q.prompt)}</strong><small>Answer: ${esc(answer?.answer || "Blank")} · Correct: ${esc(q.correct_answer)} · ${esc(answer?.awarded_points || 0)}/${esc(q.points)} points</small></div><i class="ti ti-${answer?.is_correct ? "check" : "x"}"></i></div>`;
                    })
                    .join("") ||
                '<p class="text-secondary">No submitted answers yet.</p>'
            }</div>`,
        );
    };
    const confirmPost = async ({ title, text, url, body = {}, success }) => {
        const confirmation = await Swal.fire({
            icon: "question",
            title,
            text,
            showCancelButton: true,
            confirmButtonColor: "#316fd6",
            confirmButtonText: "Continue",
        });
        if (!confirmation.isConfirmed) return;
        try {
            const result = await api(url, { method: "POST", body });
            await notify("success", success, result.message);
            await load();
        } catch (error) {
            notify("error", "Action not completed", error.message);
        }
    };
    const saveSettings = async () => {
        const form = $("#ptSettingsForm");
        if (!form || !form.reportValidity()) return;
        const data = serialize(form);
        data.campus_id = Number(form.dataset.campusId);
        [
            "online_enabled",
            "paper_enabled",
            "manual_enabled",
            "scan_enabled",
            "show_result_to_candidate",
            "enrollment_handoff_enabled",
        ].forEach((name) => (data[name] = form.elements[name].checked));
        if (!data.campus_id)
            return notify(
                "error",
                "Select a campus",
                "Choose a campus in Data Filters before saving settings.",
            );
        try {
            const result = await api("/students/placement-tests/settings", {
                method: "POST",
                body: data,
            });
            await notify("success", "Settings saved", result.message);
            await load();
        } catch (error) {
            notify("error", "Settings not saved", error.message);
        }
    };
    const serialize = (form) =>
        Object.fromEntries(
            [...new FormData(form).entries()].filter(
                ([k]) => k !== "option" && !k.startsWith("rule_"),
            ),
        );
    const refreshOptionRows = () => {
        const container = $("#ptOptions");
        if (!container) return;
        const style =
            document.querySelector("[data-option-style]")?.value || "letters";
        [...container.querySelectorAll(".pt-option-row")].forEach(
            (row, index) => {
                const label =
                    style === "numbers"
                        ? String(index + 1)
                        : String.fromCharCode(65 + index);
                row.querySelector("[data-option-label]").textContent = label;
                row.querySelector('[name="correct_answer"]').value = label;
            },
        );
    };
    const syncQuestionType = () => {
        const type = document.querySelector("[data-question-type]")?.value;
        if (!type) return;
        const choice = isChoiceQuestion(type),
            panel = document.querySelector("[data-choice-panel]"),
            written = document.querySelector("[data-written-panel]"),
            styleField = document.querySelector("[data-option-style-field]"),
            style = document.querySelector("[data-option-style]"),
            add = document.querySelector("[data-add-option]");
        panel.hidden = !choice;
        written.hidden = choice;
        panel
            .querySelectorAll("input,select,textarea,button")
            .forEach((control) => (control.disabled = !choice));
        written
            .querySelectorAll("input,select,textarea")
            .forEach((control) => (control.disabled = choice));
        styleField.hidden = !choice || type === "true_false";
        style.disabled = !choice || type === "true_false";
        add.hidden = type === "true_false";
        if (type === "true_false") {
            style.value = "letters";
            const current =
                    panel.querySelector('[name="correct_answer"]:checked')
                        ?.value || "A",
                values = [...panel.querySelectorAll('[name="option"]')].map(
                    (input) => input.value,
                );
            if (values.join("|") !== "True|False")
                $("#ptOptions").innerHTML = ["True", "False"]
                    .map((value, index) =>
                        optionRow(
                            value,
                            index,
                            ["A", "B"].includes(current) ? current : "A",
                            "letters",
                        ),
                    )
                    .join("");
            panel
                .querySelectorAll('[name="option"]')
                .forEach((input) => (input.readOnly = true));
            panel
                .querySelectorAll("[data-remove-option]")
                .forEach((button) => (button.hidden = true));
        } else if (choice) {
            panel
                .querySelectorAll('[name="option"]')
                .forEach((input) => (input.readOnly = false));
            panel
                .querySelectorAll("[data-remove-option]")
                .forEach((button) => (button.hidden = false));
        }
        const answer = document.querySelector("[data-written-answer]"),
            label = document.querySelector("[data-written-answer-label]"),
            help = document.querySelector("[data-written-help]");
        answer.required = type === "fill_blank";
        label.textContent =
            type === "fill_blank"
                ? "Accepted Answer"
                : "Model Answer (optional)";
        help.textContent =
            type === "fill_blank"
                ? "The system compares this answer without case sensitivity."
                : "The examiner uses this model answer and marking guidance during manual scoring.";
        refreshOptionRows();
    };
    document.addEventListener("click", (event) => {
        const add = event.target.closest("[data-add-option]"),
            remove = event.target.closest("[data-remove-option]");
        if (add) {
            const container = $("#ptOptions"),
                count = container.querySelectorAll(".pt-option-row").length;
            if (count >= 26)
                return notify(
                    "warning",
                    "Maximum reached",
                    "A question can contain up to 26 answer choices.",
                );
            const style =
                document.querySelector("[data-option-style]")?.value ||
                "letters";
            container.insertAdjacentHTML(
                "beforeend",
                optionRow("", count, "", style),
            );
            refreshOptionRows();
        }
        if (remove) {
            const container = $("#ptOptions"),
                rows = container.querySelectorAll(".pt-option-row");
            if (rows.length <= 2)
                return notify(
                    "warning",
                    "Two choices required",
                    "Keep at least two answer choices for every question.",
                );
            remove.closest(".pt-option-row").remove();
            refreshOptionRows();
        }
    });
    document.addEventListener("change", (event) => {
        if (event.target.matches("[data-option-style]")) refreshOptionRows();
        if (event.target.matches("[data-question-type]")) syncQuestionType();
        if (event.target.matches("[data-question-grade]"))
            syncQuestionSubjects(true);
        if (event.target.matches("[data-question-subject]"))
            syncQuestionTopics(true);
        if (event.target.matches("[data-topic-subject]"))
            syncTopicGradeChoices();
        if (event.target.matches("[data-master-filter]"))
            applyMasterFilters(event.target.dataset.masterKind);
    });
    $("#ptEditorForm").addEventListener("submit", async (e) => {
        e.preventDefault();
        const submit = e.submitter;
        submit.disabled = true;
        try {
            const result = await editorHandler(serialize(e.currentTarget));
            editor.hide();
            await notify("success", "Saved", result.message);
            await load();
        } catch (error) {
            notify("error", "Please check the form", error.message);
        } finally {
            submit.disabled = false;
        }
    });
    $("#ptActionForm").addEventListener("submit", async (e) => {
        e.preventDefault();
        const submit = e.submitter;
        submit.disabled = true;
        try {
            const result = await actionHandler(serialize(e.currentTarget));
            actionModal.hide();
            await notify("success", "Completed", result.message);
            await load();
        } catch (error) {
            notify("error", "Action not completed", error.message);
        } finally {
            submit.disabled = false;
        }
    });
    document.addEventListener("click", async (event) => {
        const target = event.target.closest(
            "[data-view-applicant],[data-view-question],[data-view-form],[data-view-attempt],[data-archive-applicant],[data-duplicate-question],[data-archive-question],[data-duplicate-form],[data-invalidate-attempt],[data-save-settings]",
        );
        if (!target) return;
        if (target.dataset.viewApplicant)
            viewApplicant(+target.dataset.viewApplicant);
        if (target.dataset.viewQuestion)
            viewQuestion(+target.dataset.viewQuestion);
        if (target.dataset.viewForm) viewForm(+target.dataset.viewForm);
        if (target.dataset.viewAttempt)
            viewAttempt(+target.dataset.viewAttempt);
        if (target.dataset.archiveApplicant)
            confirmPost({
                title: "Archive this applicant?",
                text: "The applicant will remain in the audit history and cannot receive a new test while archived.",
                url: `/students/placement-tests/applicants/${target.dataset.archiveApplicant}/archive`,
                success: "Applicant archived",
            });
        if (target.dataset.duplicateQuestion)
            confirmPost({
                title: "Duplicate this question?",
                text: "A new editable draft copy will be created.",
                url: `/students/placement-tests/questions/${target.dataset.duplicateQuestion}/duplicate`,
                success: "Question duplicated",
            });
        if (target.dataset.archiveQuestion)
            confirmPost({
                title: "Archive this question?",
                text: "Existing test snapshots remain unchanged.",
                url: `/students/placement-tests/questions/${target.dataset.archiveQuestion}/archive`,
                success: "Question archived",
            });
        if (target.dataset.duplicateForm)
            confirmPost({
                title: "Create a new revision?",
                text: "Questions and settings will be copied into a new editable draft revision.",
                url: `/students/placement-tests/forms/${target.dataset.duplicateForm}/duplicate`,
                success: "New revision created",
            });
        if (target.dataset.invalidateAttempt) {
            const result = await Swal.fire({
                icon: "warning",
                title: "Invalidate this test session?",
                input: "textarea",
                inputLabel: "Reason",
                inputPlaceholder:
                    "Enter the reason for invalidating this session",
                inputValidator: (value) =>
                    String(value || "").trim().length < 5
                        ? "Enter at least 5 characters."
                        : undefined,
                showCancelButton: true,
                confirmButtonColor: "#d63939",
                confirmButtonText: "Invalidate",
            });
            if (result.isConfirmed)
                try {
                    const response = await api(
                        `/students/placement-tests/attempts/${target.dataset.invalidateAttempt}/invalidate`,
                        { method: "POST", body: { reason: result.value } },
                    );
                    await notify(
                        "success",
                        "Session invalidated",
                        response.message,
                    );
                    await load();
                } catch (error) {
                    notify("error", "Session not invalidated", error.message);
                }
        }
        if (target.matches("[data-save-settings]")) saveSettings();
    });
    document.addEventListener("click", async (e) => {
        const t = e.target.closest("button,a");
        if (!t) return;
        if (t.matches("[data-filter-toggle]")) {
            t.closest(".pt-filter-card").classList.toggle("is-collapsed");
            syncFilterCard();
        }
        if (t.matches("[data-add-applicant]")) applicantEditor();
        if (t.dataset.editApplicant) applicantEditor(+t.dataset.editApplicant);
        if (t.matches("[data-add-question]")) questionEditor();
        if (t.dataset.editQuestion) questionEditor(+t.dataset.editQuestion);
        if (t.matches("[data-add-form]")) formEditor();
        if (t.dataset.editForm) formEditor(+t.dataset.editForm);
        if (t.dataset.formQuestions) questionPicker(+t.dataset.formQuestions);
        if (t.dataset.assign) assignTest(+t.dataset.assign);
        if (t.dataset.score) scoreAttempt(+t.dataset.score);
        if (t.dataset.scan) scanAttempt(+t.dataset.scan);
        if (t.dataset.reviewScan) {
            const scan =
                (payload.scans || []).find(
                    (x) => x.id === +t.dataset.reviewScan,
                ) ||
                (payload.attempts || [])
                    .flatMap((x) => x.scans || [])
                    .find((x) => x.id === +t.dataset.reviewScan);
            if (scan) reviewScan(scan, +t.dataset.attempt);
        }
        if (t.matches("[data-add-rule]"))
            $("#ptRules").insertAdjacentHTML("beforeend", ruleRow());
        if (t.matches("[data-remove-rule]")) t.closest(".pt-rule-row").remove();
        if (t.dataset.copyLink) {
            await navigator.clipboard.writeText(
                `${location.origin}/placement-test/${t.dataset.copyLink}`,
            );
            notify(
                "success",
                "Link copied",
                "The secure online test link is ready to share.",
            );
        }
        if (t.dataset.formStatus) {
            const ok = await Swal.fire({
                icon: "question",
                title: `Move this form to ${t.dataset.status}?`,
                text:
                    t.dataset.status === "published"
                        ? "Publishing locks this revision for fair and auditable testing."
                        : "This updates the controlled form lifecycle.",
                showCancelButton: true,
                confirmButtonColor: "#316fd6",
                confirmButtonText: "Continue",
            });
            if (ok.isConfirmed)
                try {
                    const r = await api(
                        `/students/placement-tests/forms/${t.dataset.formStatus}/status`,
                        { method: "POST", body: { status: t.dataset.status } },
                    );
                    notify("success", "Status updated", r.message);
                    load();
                } catch (error) {
                    notify("error", "Cannot update status", error.message);
                }
        }
        if (t.dataset.enroll) {
            const ok = await Swal.fire({
                icon: "question",
                title: "Prepare student enrollment?",
                text: "The passed applicant details will prefill the Student Enrollment form.",
                showCancelButton: true,
                confirmButtonColor: "#316fd6",
            });
            if (ok.isConfirmed)
                try {
                    const r = await api(
                        `/students/placement-tests/applicants/${t.dataset.enroll}/prepare-enrollment`,
                        { method: "POST", body: {} },
                    );
                    location.href = r.url;
                } catch (error) {
                    notify("error", "Cannot prepare enrollment", error.message);
                }
        }
    });
    document.addEventListener("click", (event) => {
        const target = event.target.closest(
            "[data-add-subject],[data-edit-subject],[data-toggle-subject],[data-add-topic],[data-edit-topic],[data-toggle-topic]",
        );
        if (!target) return;
        if (target.matches("[data-add-subject]")) subjectEditor();
        if (target.dataset.editSubject)
            subjectEditor(+target.dataset.editSubject);
        if (target.matches("[data-add-topic]")) topicEditor();
        if (target.dataset.editTopic) topicEditor(+target.dataset.editTopic);
        if (target.dataset.toggleSubject)
            confirmPost({
                title: `${target.dataset.status === "active" ? "Activate" : "Deactivate"} this subject?`,
                text: "Question Bank dropdowns update immediately while existing question snapshots remain unchanged.",
                url: `/students/placement-tests/settings/subjects/${target.dataset.toggleSubject}/status`,
                body: { status: target.dataset.status },
                success: "Subject status updated",
            });
        if (target.dataset.toggleTopic)
            confirmPost({
                title: `${target.dataset.status === "active" ? "Activate" : "Deactivate"} this curriculum topic?`,
                text: "Inactive topics are hidden from new questions while existing records remain available.",
                url: `/students/placement-tests/settings/topics/${target.dataset.toggleTopic}/status`,
                body: { status: target.dataset.status },
                success: "Curriculum topic status updated",
            });
    });
    document.addEventListener("input", (event) => {
        if (event.target.matches("[data-master-search]"))
            applyMasterFilters(event.target.dataset.masterKind);
    });
    ["#ptAcademicYear", "#ptCampus", "#ptGrade"].forEach((id) =>
        $(id)?.addEventListener("change", load),
    );
    $("#ptSearch")?.addEventListener("input", () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 350);
    });
    load();
}
