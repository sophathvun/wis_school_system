export function applySummerEditPayload(payload, enrollment) {
    if (enrollment) {
        payload.set("enrollment_id", String(enrollment.id));
        payload.set("student_record_id", String(enrollment.student_id));
        payload.set("enrollment_origin", enrollment.enrollment_origin);
    }
    return payload;
}
