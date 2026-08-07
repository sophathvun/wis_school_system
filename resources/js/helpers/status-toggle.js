const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const statusPermissions = {
    "academic-year": "academic-years.status", "education-level": "education-levels.status", "grade": "grades.status",
    "program": "programs.status", "class": "classes.status", "session": "sessions.status", "school-profile": "school-info.status",
    "student-enrollment": "students.enrollment.status", "family": "families.status", "user": "users.status", "department": "departments.status",
    "role": "roles.status", "occupation": "occupations.status", "withdrawal-reason": "withdrawal-reasons.status",
    "student-document-type": "student-document-types.status", "country": "locations.status", "province": "locations.status",
    "district": "locations.status", "commune": "locations.status", "village": "locations.status", "nationality": "locations.status",
};

window.statusToggleMarkup = (entity, id, active) => {
    const permission = statusPermissions[entity];
    if (permission && Array.isArray(window.userPermissions) && !window.userPermissions.includes('*') && !window.userPermissions.includes(permission)) return '';
    return `
    <button type="button" class="status-toggle ${active ? "is-active" : ""}"
        data-status-toggle data-status-entity="${entity}" data-status-id="${id}" data-status="${active ? 1 : 0}"
        aria-label="Set status ${active ? "inactive" : "active"}" aria-pressed="${active}">
        <span class="status-toggle-label">${active ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span>
    </button>`;
};

document.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-status-toggle]");
    if (!button || button.disabled) return;

    const nextStatus = button.dataset.status === "1" ? 0 : 1;
    button.disabled = true;

    try {
        const response = await fetch("/status/toggle", {
            method: "POST",
            headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken },
            body: JSON.stringify({ entity: button.dataset.statusEntity, id: button.dataset.statusId, status: nextStatus }),
        });
        const result = await response.json();
        if (!response.ok || result.status !== "success") throw new Error(result.message || "Unable to update status.");

        const active = result.active;
        button.dataset.status = active ? "1" : "0";
        button.className = `status-toggle ${active ? "is-active" : ""}`;
        button.innerHTML = `<span class="status-toggle-label">${active ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span>`;
        button.setAttribute("aria-label", `Set status ${active ? "inactive" : "active"}`);
        button.setAttribute("aria-pressed", String(active));
    } catch (error) {
        console.error(error);
        window.alert(error.message || "Unable to update status.");
    } finally {
        button.disabled = false;
    }
});

const statusEntities = {
    academicYearsTable: "academic-year",
    gradesTable: "grade",
    classesTable: "class",
    sessionsTable: "session",
    educationLevelsTable: "education-level",
    programsTable: "program",
    groupsTable: "group",
    schoolProfilesTable: "school-profile",
    studentEnrollmentsTable: "student-enrollment",
    familiesTable: "family",
    occupationsTable: "occupation",
    nationalitiesTable: "nationality",
    educationLevelsTable: "education-level",
};

const convertStatusBadges = () => {
    const inferTarget = (row) => {
        const source = row?.querySelector("a[href], form[action], button[onclick], button[data-edit], button[data-delete], button[data-members], button[data-reason]");
        const text = source?.getAttribute("href") || source?.getAttribute("action") || source?.getAttribute("onclick") || "";
        const match = text.match(/(?:edit=|\/)(\d+)(?:$|[?#])/i) || text.match(/\((\d+)\)/);
        const id = match?.[1] || row?.querySelector("[data-reason]")?.dataset.reason && (() => { try { return JSON.parse(row.querySelector("[data-reason]").dataset.reason).id; } catch { return null; } })();
        if (!id) return null;
        const path = text.toLowerCase();
        const entity = path.includes('department') ? 'department' : path.includes('role') ? 'role' : path.includes('occupation') ? 'occupation' : path.includes('nationalit') ? 'nationality' : path.includes('withdrawal-reasons') ? 'withdrawal-reason' : path.includes('student-document-types') ? 'student-document-type' : path.includes('/users') ? 'user' : null;
        return entity ? { entity, id } : null;
    };
    Object.entries(statusEntities).forEach(([tableId, entity]) => {
        document.querySelectorAll(`#${tableId} .badge`).forEach((badge) => {
            if (!/^(active|inactive)$/i.test(badge.textContent.trim())) return;
            const row = badge.closest("tr");
            const action = row?.querySelector("button[onclick]");
            const id = action?.getAttribute("onclick")?.match(/\((\d+)\)/)?.[1] || row?.querySelector("[data-edit]")?.dataset.edit || row?.querySelector("[data-delete]")?.dataset.delete;
            if (!id) return;
            badge.outerHTML = statusToggleMarkup(entity, id, /^active$/i.test(badge.textContent.trim()));
        });
    });
    document.querySelectorAll("table tr .badge").forEach((badge) => {
        if (!/^(active|inactive)$/i.test(badge.textContent.trim()) || badge.closest('[data-status-toggle]')) return;
        const target = inferTarget(badge.closest('tr'));
        if (target) badge.outerHTML = statusToggleMarkup(target.entity, target.id, /^active$/i.test(badge.textContent.trim()));
    });
};

new MutationObserver(convertStatusBadges).observe(document.body, { childList: true, subtree: true });
document.addEventListener("DOMContentLoaded", convertStatusBadges);
