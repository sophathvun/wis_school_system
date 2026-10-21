import assert from "node:assert/strict";
import test from "node:test";
import { applySummerEditPayload } from "../../resources/js/helpers/summer-enrollment.js";

test("editing sends the enrollment identity even when locked controls are omitted", () => {
    const payload = new FormData();
    payload.set("student_record_id", "");
    payload.set("full_name_en", "UPDATED STUDENT");
    applySummerEditPayload(payload, { id: 12, student_id: 34, enrollment_origin: "external" });
    assert.equal(payload.get("enrollment_id"), "12");
    assert.equal(payload.get("student_record_id"), "34");
    assert.equal(payload.get("enrollment_origin"), "external");
    assert.equal(payload.get("full_name_en"), "UPDATED STUDENT");
    assert.equal(payload.has("campus_id"), false);
});

test("new registrations do not reuse an edited enrollment ID", () => {
    const payload = new FormData();
    payload.set("enrollment_origin", "internal");
    payload.set("student_record_id", "34");
    applySummerEditPayload(payload, null);
    assert.equal(payload.has("enrollment_id"), false);
    assert.equal(payload.get("enrollment_origin"), "internal");
    assert.equal(payload.get("student_record_id"), "34");
});
