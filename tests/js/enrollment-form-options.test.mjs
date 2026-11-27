import test from 'node:test';
import assert from 'node:assert/strict';
import {fetchEnrollmentFormOptions, fetchEnrollmentDocumentOptions} from '../../resources/js/helpers/enrollment-form-options.js';

test('returns nationality and year data from the enrollment options request', async () => {
    const options = {academicYears: [{id: 1}], countries: [{id: 2}]};
    assert.deepEqual(await fetchEnrollmentFormOptions(async () => Response.json(options)), options);
});
test('rejects failed or malformed enrollment options instead of caching empty selects', async () => {
    await assert.rejects(fetchEnrollmentFormOptions(async () => Response.json({message:'Forbidden'}, {status:403})));
    await assert.rejects(fetchEnrollmentFormOptions(async () => Response.json({message:'Error'})));
});
test('optional document failures do not fail enrollment options', async () => {
    assert.deepEqual(await fetchEnrollmentDocumentOptions(async () => {throw new Error('Network error');}), {types:[]});
    assert.deepEqual(await fetchEnrollmentDocumentOptions(async () => Response.json({}, {status:403})), {types:[]});
    assert.deepEqual(await fetchEnrollmentDocumentOptions(async () => Response.json({types:[{id:1}]})), {types:[{id:1}]});
});
