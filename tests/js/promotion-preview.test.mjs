import test from 'node:test';
import assert from 'node:assert/strict';
import { promotionPreviewRow } from '../../resources/js/helpers/promotionPreview.js';

const skipped = {
    id: 8,
    promotion_preview: { status: 'grade_skipping', grade: 'G5', class: 'A', academic_year: '2026-2027', reference_number: 'SG2627-003' },
};

test('approved skipping is clearly labelled, locked in selected promotion and read-only in class preview', () => {
    const selected = promotionPreviewRow(skipped, 'STUDENT - 2216236', true);
    assert.match(selected, /Already enrolled — Grade Skipping/);
    assert.match(selected, /G5A \/ 2026-2027 · SG2627-003/);
    assert.match(selected, /value="8" checked disabled/);
    assert.doesNotMatch(promotionPreviewRow(skipped, 'STUDENT', false), /<input/);
});

test('unrecognized existing enrollments require review and cannot be selected', () => {
    const html = promotionPreviewRow({ id: 9, promotion_preview: { status: 'review' } }, 'STUDENT', true);
    assert.match(html, /Existing enrollment — Review required/);
    assert.match(html, /value="9" disabled/);
    assert.doesNotMatch(html, /checked/);
    const eligible = promotionPreviewRow({ id: 10 }, 'REGULAR STUDENT', true);
    assert.match(eligible, /value="10"/);
    assert.doesNotMatch(eligible, /checked|disabled|Review required|Grade Skipping/);
});

test('student labels and approval placement details cannot inject HTML into the preview', () => {
    const html = promotionPreviewRow({ ...skipped, promotion_preview: { ...skipped.promotion_preview, grade: '<img src=x onerror=alert(1)>', reference_number: '"<script>alert(1)</script>' } }, '<script>student</script>', true);
    assert.doesNotMatch(html, /<script|<img/);
    assert.match(html, /&lt;script&gt;student&lt;\/script&gt;/);
    assert.match(html, /&quot;&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
});
