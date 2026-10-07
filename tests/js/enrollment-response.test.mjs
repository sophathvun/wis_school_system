import test from 'node:test';
import assert from 'node:assert/strict';
import { isDuplicateStudentIdResponse, readEnrollmentSaveResponse } from '../../resources/js/helpers/enrollment-response.js';

test('reads every required-field error from the server without confusing a blank ID with a duplicate', async () => {
    const errors = {
        student_id: ['Student ID is required.'],
        full_name_en: ['The full name en field is required.'],
        mother_phone: ['The mother phone field is required.'],
        father_phone: ['The father phone field is required.'],
    };
    const response = new Response(JSON.stringify({ message: 'Please complete the required fields.', errors }), { status: 422 });
    const result = await readEnrollmentSaveResponse(response);
    assert.deepEqual(result.errors, errors);
    assert.equal(isDuplicateStudentIdResponse(result), false);
});

test('recognizes duplicate ID messages even when the general message contains other validation errors', () => {
    assert.equal(isDuplicateStudentIdResponse({
        message: 'The full name en field is required.',
        errors: { student_id: ['Student ID 2612345 already exists. Please enter a different Student ID.'] },
    }), true);
    assert.equal(isDuplicateStudentIdResponse({ errors: { student_id: ['The student id has already been taken.'] } }), true);
    assert.equal(isDuplicateStudentIdResponse({ message: 'The email field must be a valid email address.' }), false);
    assert.equal(isDuplicateStudentIdResponse({ errors: { student_id: ['The student id may not be greater than 30 characters.'] } }), false);
});

test('preserves server field errors for optional fields and photo validation', async () => {
    const data = { errors: { email: ['Invalid email.'], photo: ['Invalid dimensions.'] } };
    assert.deepEqual(await readEnrollmentSaveResponse(new Response(JSON.stringify(data), { status: 422 })), data);
});

test('returns saved enrollment data for a successful submission', async () => {
    const data = { status: 'success', data: { student: { id: 1, student_id: '2612345' } } };
    assert.deepEqual(await readEnrollmentSaveResponse(new Response(JSON.stringify(data), { status: 201 })), data);
});

test('gives a useful session error for an HTML login redirect or expired session', async () => {
    for (const status of [401, 419]) {
        await assert.rejects(readEnrollmentSaveResponse(new Response('<html>Session expired</html>', { status })), /session expired/i);
    }
    await assert.rejects(readEnrollmentSaveResponse({
        status: 200, redirected: true, url: 'https://school.test/login', text: async () => '<html>Sign in</html>',
    }), /session expired/i);
});

test('handles HTML server errors and invalid JSON without displaying raw HTML or a misleading duplicate alert', async () => {
    for (const content of ['<html>Server Error</html>', '', 'null', '[]']) {
        await assert.rejects(readEnrollmentSaveResponse(new Response(content, { status: 500 })), /Unable to save the enrollment/);
    }
    await assert.rejects(readEnrollmentSaveResponse(new Response('<html>Too large</html>', { status: 413 })), /files are too large/);
    await assert.rejects(readEnrollmentSaveResponse(new Response('<html>Forbidden</html>', { status: 403 })), /permissions/);
});
