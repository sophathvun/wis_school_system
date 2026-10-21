import test from "node:test";
import assert from "node:assert/strict";
import { sharedFamilyMessage, familyHistoryChanges } from "../../resources/js/helpers/family-history.js";

test("shared-family notice explains the children, campuses, and effect of edits", () => {
    assert.equal(sharedFamilyMessage({children: 3, campuses: ["BCH", "BKK"]}), "Shared family: 3 children across BCH, BKK. Changes apply to the whole family.");
});

test("history identifies the actual contact and student changes without array-index confusion", () => {
    const before_values = { family: { id: 1, family_number: "F1", status: 1 }, members: [{id: 9, relationship_type: "mother", phone: "old"}], students: [{id: 4, student_id: "004", full_name_en: "STUDENT"}] };
    const after_values = { ...before_values, members: [{id: 9, relationship_type: "mother", phone: "new"}], students: [] };
    assert.deepEqual(familyHistoryChanges({before_values, after_values}), [
        {field: "mother #9 · phone", before: "old", after: "new"},
        {field: "Linked student #4", before: "004 STUDENT", after: ""},
    ]);
});

test("history displays family creation, deletion and false status values", () => {
    const values = { family: { id: 1, family_number: "F1", status: 0 } };
    assert.deepEqual(familyHistoryChanges({before_values: null, after_values: values}), [
        {field: "Family · family number", before: "", after: "F1"}, {field: "Family · status", before: "", after: 0},
    ]);
    assert.equal(familyHistoryChanges({before_values: values, after_values: null})[0].after, "");
});
