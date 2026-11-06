import {showSuccess, showError} from './sweet-alert2.js';

/** Exam-level attendance belongs to the G9 candidate, independently of daily attendance. */
export function createG9CandidateAttendance(baseUrl, {canEditNow = () => true, onBusyChange = () => {}, onDirtyChange = () => {}} = {}) {
    const pending = new Set(), dirty = new Set(), controllers = new Set();
    const render = (cell, record) => {
        const absent = record.attendance_status === 'absent';
        if (!record.can_edit) {
            const badge = document.createElement('span');
            badge.className = absent ? 'badge g9-exam-attendance-badge bg-red-lt' : 'text-secondary';
            badge.textContent = absent ? 'Absent' : '—'; cell.append(badge);
            if (absent && record.attendance_reason) {
                const reason = document.createElement('div'); reason.className = 'g9-exam-attendance-reason-text';
                reason.textContent = record.attendance_reason; cell.append(reason);
            }
            return;
        }
        const wrapper = document.createElement('div'), check = document.createElement('input'), label = document.createElement('label');
        const reason = document.createElement('textarea'), note = document.createElement('small'), retry = document.createElement('button');
        wrapper.className = 'g9-exam-attendance'; check.type = 'checkbox'; check.className = 'form-check-input';
        check.id = 'g9ExamAbsent-' + record.id; check.setAttribute('aria-label', 'Mark absent: ' + (record.name_en || record.student_code));
        label.htmlFor = check.id; label.textContent = 'Absent';
        reason.id = 'g9ExamReason-' + record.id; reason.className = 'form-control g9-exam-attendance-reason';
        reason.rows = 3; reason.maxLength = 1000; reason.placeholder = 'Reason for absence…';
        reason.setAttribute('aria-label', 'Absence reason for ' + (record.name_en || record.student_code));
        check.setAttribute('aria-controls', reason.id);
        note.setAttribute('role', 'status'); note.setAttribute('aria-live', 'polite');
        retry.type = 'button'; retry.className = 'btn btn-sm btn-outline-danger'; retry.textContent = 'Retry'; retry.hidden = true;
        check.checked = absent; reason.value = record.attendance_reason || '';
        let saving = false, timer = null, failed = null;
        const cancel = () => { clearTimeout(timer); timer = null; };
        const sync = () => {
            wrapper.classList.toggle('is-absent', check.checked); reason.hidden = !check.checked;
            check.setAttribute('aria-expanded', String(check.checked));
        };
        const refresh = () => { const locked = saving || !canEditNow(); check.disabled = locked; reason.disabled = locked; retry.disabled = locked; };
        const changed = () => check.checked !== (record.attendance_status === 'absent') ||
            check.checked && reason.value.trim() !== (record.attendance_reason || '');
        const markDirty = () => { changed() ? dirty.add(record.id) : dirty.delete(record.id); onDirtyChange(); };
        const save = () => {
            cancel();
            if (saving || !canEditNow() || !changed()) return;
            const selected = check.checked ? 'absent' : null, text = selected ? reason.value.trim() : null;
            saving = true; failed = null; retry.hidden = true; refresh(); onBusyChange(true); sync();
            note.textContent = 'Saving…'; note.className = 'text-secondary';
            const task = (async () => {
                try {
                    const response = await fetch(baseUrl + '/' + record.id + '/attendance', {
                        method: 'POST', credentials: 'same-origin',
                        headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                        body: JSON.stringify({version: record.version, attendance_status: selected, attendance_reason: text}),
                    });
                    const data = await response.json();
                    if (!response.ok) throw Error(Object.values(data.errors || {}).flat().join('\n') || data.message || 'Attendance could not be saved.');
                    record.attendance_status = data.attendance_status; record.attendance_reason = data.attendance_reason || null; record.version = data.version;
                    check.checked = data.attendance_status === 'absent'; reason.value = data.attendance_reason || '';
                    dirty.delete(record.id); note.textContent = 'Saved'; note.className = 'text-success'; onDirtyChange();
                    showSuccess('Exam attendance saved');
                } catch (error) {
                    failed = {selected, text}; check.checked = record.attendance_status === 'absent';
                    // Preserve a reason draft so a failed autosave can be retried.
                    markDirty(); retry.hidden = false; note.textContent = 'Not saved'; note.className = 'text-danger';
                    showError('Attendance was not saved', error.message);
                } finally { saving = false; onBusyChange(false); refresh(); sync(); }
            })();
            pending.add(task); task.finally(() => pending.delete(task));
        };
        check.addEventListener('change', () => {
            if (!canEditNow() || saving) { check.checked = record.attendance_status === 'absent'; sync(); return; }
            sync(); save();
        });
        reason.addEventListener('input', () => {
            if (saving || !canEditNow() || !check.checked) return;
            cancel(); failed = null; retry.hidden = true; markDirty();
            note.textContent = changed() ? 'Unsaved reason…' : 'Saved'; note.className = 'text-secondary';
            if (changed()) timer = setTimeout(save, 600);
        });
        reason.addEventListener('blur', save);
        retry.addEventListener('click', () => {
            if (!failed || saving || !canEditNow()) return;
            check.checked = failed.selected === 'absent'; reason.value = failed.text || ''; sync(); save();
        });
        sync(); refresh(); wrapper.append(check, label, reason, note, retry); cell.append(wrapper);
        const controller = {refresh, cancel}; controllers.add(controller); return controller;
    };
    window.addEventListener('beforeunload', event => { if (pending.size || dirty.size) { event.preventDefault(); event.returnValue = ''; } });
    return {
        render, hasUnsaved: () => dirty.size > 0,
        discard() { controllers.forEach(controller => controller.cancel()); controllers.clear(); dirty.clear(); onDirtyChange(); },
        async whenIdle() { while (pending.size) await Promise.allSettled([...pending]); },
    };
}
