export function renderDailyAttendanceRow(student, index, disabled, statuses, escape, periods = []) {
    const parent = (label, person) => `<div class="att-parent"><small>${label}</small><span class="school-profile-khmer">${escape(person?.name || '—')}</span><span class="att-parent-phone"><i class="ti ti-phone" aria-hidden="true"></i> ${escape(person?.phone || '—')}</span></div>`;
    const author = student.added_by || {};
    const savedPeriods = student.mark?.absent_periods || [];
    const selected = new Set(savedPeriods.map(period => Number(period.id)));
    const applicable = periods.filter(period => student.allowed_period_ids?.includes(Number(period.id)));
    const available = [...new Map([...applicable, ...savedPeriods].map(period => [Number(period.id), period])).values()];
    const time = raw => {const [hour, minute] = raw.split(':');return `${Number(hour) % 12 || 12}:${minute} ${Number(hour) < 12 ? 'AM' : 'PM'}`;};
    const periodPicker = `<details class="att-period-picker">
        <summary><span data-daily-period-summary>${selected.size ? `${selected.size} periods selected` : 'Select session'}</span><i class="ti ti-chevron-down" aria-hidden="true"></i></summary>
        <div class="att-period-panel">
            <label class="att-period-select-all"><input type="checkbox" class="form-check-input" data-daily-period-all aria-label="Select all absent periods for ${escape(student.name)}" ${disabled || !available.length ? 'disabled' : ''}><span><strong>Select All</strong><small data-daily-period-count>${selected.size} of ${available.length} selected</small></span></label>
            <div class="att-period-options">${available.map(period => `<label class="att-period-option"><input type="checkbox" class="form-check-input" data-daily-period value="${period.id}" ${selected.has(Number(period.id)) ? 'checked' : ''} ${disabled ? 'disabled' : ''}><span><strong>${escape(period.name)}</strong><small>${time(period.starts_at)} – ${time(period.ends_at)}</small></span></label>`).join('') || `<small>${student.study_group ? 'No periods assigned to this study group.' : 'Please assign a study group.'}</small>`}</div>
        </div>
    </details>`;
    return `<tr data-daily-student="${student.id}" data-daily-name="${escape([student.name, student.name_kh, student.code].join(' ').toLowerCase())}">
        <td>${index + 1}</td>
        <td>${student.photo_url ? `<img class="att-student-photo" src="${escape(student.photo_url)}" alt="Photo of ${escape(student.name)}" loading="lazy">` : '<span class="att-photo-placeholder"><i class="ti ti-user" aria-hidden="true"></i></span>'}</td>
        <td><strong class="att-name school-profile-khmer">${escape(student.name_kh)}</strong><span class="att-name">${escape(student.name)}</span><small>${escape(student.code)}</small>${student.other_class ? '<div class="text-warning">Recorded in another class on this date.</div>' : ''}</td>
        <td data-label="Family"><div class="att-family">${parent('Mother', student.mother)}${parent('Father', student.father)}</div></td>
        <td><input type="hidden" data-daily-status value="${escape(student.mark?.status || '')}" ${disabled ? 'disabled' : ''}><div class="att-status-buttons" role="group" aria-label="Attendance for ${escape(student.name)}">${Object.entries(statuses).map(([value, label]) => `<button type="button" class="att-status-button" data-status-choice="${value}" data-att-status="${value}" aria-label="${label} for ${escape(student.name)}" aria-pressed="${student.mark?.status === value}" title="${label}" ${disabled ? 'disabled' : ''}>${{EX:'E',UX:'U'}[value]||value}</button>`).join('')}</div>${student.mark?.leave_id?'<small class="att-name text-primary mt-1">Approved leave</small>':''}${student.can_delete && student.mark ? `<button type="button" class="btn btn-sm btn-outline-danger mt-2" data-delete-attendance="${student.id}" aria-label="Delete attendance for ${escape(student.name)}"><i class="ti ti-trash me-1"></i>Delete</button>` : ''}<div class="att-status-periods" data-daily-exception ${['EX','UX','L'].includes(student.mark?.status) ? '' : 'hidden'}>${periodPicker}<small class="att-missed-note" data-daily-missed-note>Missed periods: ${student.mark?.missed_sessions || 0}</small><input type="hidden" data-daily-missed value="${student.mark?.missed_sessions || 0}"></div></td>
        <td data-label="Reason"><div data-daily-exception ${['EX','UX','L'].includes(student.mark?.status) ? '' : 'hidden'}><input type="text" class="form-control" maxlength="500" data-daily-remarks value="${escape(student.mark?.remarks || '')}" placeholder="Reason or session details" aria-label="Reason for ${escape(student.name)}" ${disabled ? 'disabled' : ''}></div></td>
        <td data-label="Added by" data-daily-author="${student.id}"><strong class="att-name">${escape(author.name || '—')}</strong><small class="att-name">${escape(author.position || '—')}</small><small class="att-name">${escape(author.date || '—')}</small></td>
    </tr>`;
}
