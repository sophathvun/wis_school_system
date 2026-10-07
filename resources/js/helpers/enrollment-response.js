export const isDuplicateStudentIdMessage = (message = "") => {
    const text = String(message || "").toLowerCase();
    return (
        text.includes("duplicate") ||
        text.includes("already exists") ||
        text.includes("already been taken") ||
        text.includes("integrity constraint") ||
        text.includes("1062") ||
        text.includes("unique")
    ) && (text.includes("student_id") || text.includes("student id") || text.includes("tb_student"));
};

export const isDuplicateStudentIdResponse = (result = {}) => {
    const messages = result.errors?.student_id || result.errors?.["student.student_id"] || [];
    return [result.message, ...[].concat(messages)].some(isDuplicateStudentIdMessage);
};

export const readEnrollmentSaveResponse = async (response) => {
    const text = await response.text();
    try {
        const result = JSON.parse(text);
        if (result && typeof result === "object" && !Array.isArray(result)) return result;
    } catch {
        // Never show an HTML error page as an enrollment validation message.
    }
    if (response.status === 401 || response.status === 419 ||
        (response.redirected && /\/login(?:[/?#]|$)/.test(response.url || ""))) {
        throw new Error("Your session expired. Please refresh and sign in again before saving the enrollment.");
    }
    if (response.status === 413) {
        throw new Error("The uploaded files are too large. Please reduce their size and try again.");
    }
    if (response.status === 403) {
        throw new Error("Your permissions do not allow saving this enrollment.");
    }
    throw new Error("Unable to save the enrollment. Please try again. If the problem continues, contact the system administrator.");
};
