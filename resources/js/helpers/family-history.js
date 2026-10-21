export function sharedFamilyMessage(info = {}) {
    const count = Number(info.children || 0);
    const campuses = (info.campuses || []).join(", ");
    return `${count > 1 ? "Shared family" : "Family"}: ${count} ${count === 1 ? "child" : "children"}${campuses ? ` across ${campuses}` : ""}. Changes apply to the whole family.`;
}

function fields(snapshot) {
    const result = {};
    const label = (key) => key.replaceAll("_", " ");
    for (const [key, value] of Object.entries(snapshot?.family || {})) {
        if (key !== "id") result[`Family · ${label(key)}`] = value;
    }
    for (const member of snapshot?.members || []) {
        const prefix = `${member.relationship_type || "Contact"} #${member.id}`;
        for (const [key, value] of Object.entries(member)) {
            if (!["id", "family_id", "user_id"].includes(key)) result[`${prefix} · ${label(key)}`] = value;
        }
    }
    for (const student of snapshot?.students || []) result[`Linked student #${student.id}`] = `${student.student_id || ""} ${student.full_name_en || ""}`.trim();
    return result;
}

export function familyHistoryChanges(entry) {
    const before = fields(entry.before_values);
    const after = fields(entry.after_values);
    return [...new Set([...Object.keys(before), ...Object.keys(after)])]
        .filter((key) => String(before[key] ?? "") !== String(after[key] ?? ""))
        .map((key) => ({ field: key, before: before[key] ?? "", after: after[key] ?? "" }));
}
