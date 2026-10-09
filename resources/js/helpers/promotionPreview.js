const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[char]));

export function promotionPreviewRow(item, label, selected) {
    const preview = item.promotion_preview || { status: 'eligible' };
    const skipping = preview.status === 'grade_skipping';
    const review = preview.status === 'review';
    const checkbox = selected
        ? `<input class="form-check-input selected-class-student" type="checkbox" value="${escapeHtml(item.id)}"${skipping ? ' checked disabled' : review ? ' disabled' : ''}>`
        : '';
    const badge = skipping
        ? '<span class="badge bg-blue-lt">Already enrolled — Grade Skipping</span>'
        : review ? '<span class="badge bg-yellow-lt">Existing enrollment — Review required</span>' : '';
    const placement = skipping
        ? `<small class="text-secondary d-block">${escapeHtml(preview.grade)}${escapeHtml(preview.class)} / ${escapeHtml(preview.academic_year)} · ${escapeHtml(preview.reference_number)}</small>`
        : '';
    return `<label class="${selected ? 'form-check' : 'd-block'} mb-2">${checkbox}<span class="form-check-label">${escapeHtml(label)} ${badge}${placement}</span></label>`;
}
