export function initSkippingWorkflowEditor(root) {
    root.querySelector('[data-skipping-flow-campus]')?.addEventListener('change', (event) => event.target.form.requestSubmit());
    const syncField = (input) => input.closest('.premium-form-field')?.classList.toggle('has-value', Boolean(input.value));
    root.querySelectorAll('.premium-form-field input, .premium-form-field textarea, .premium-form-field select').forEach(syncField);
    root.addEventListener('input', (event) => syncField(event.target));
    root.addEventListener('change', (event) => {
        syncField(event.target);
        if (event.target.matches('[data-member-range]')) event.target.setCustomValidity('');
    });
    root.querySelectorAll('[data-flow-editor]').forEach((editor) => {
        const rows = editor.querySelector('[data-flow-rows]');
        const template = editor.querySelector('[data-flow-template]');
        const add = editor.querySelector('[data-flow-add]');
        const syncGrades = (member) => {
            const assigned = member.querySelector('[data-member-field="grade_scope"]').value === 'selected';
            member.querySelector('[data-member-grades]').hidden = !assigned;
            const checks = [...member.querySelectorAll('[data-member-field="grade_ids"]')];
            checks.forEach((input) => input.disabled = !assigned);
            member.querySelector('[data-member-grade-summary]').textContent = `(${checks.filter((input) => input.checked).length} selected)`;
        };
        const sync = () => {
            [...rows.children].forEach((row, index, list) => {
                row.querySelector('[data-flow-order]').textContent = index + 1;
                row.querySelector('[data-flow-up]').disabled = index === 0;
                row.querySelector('[data-flow-down]').disabled = index === list.length - 1;
                row.querySelector('[data-flow-field="title"]').value = row.querySelector('[data-member-field="title"]')?.value || '';
                row.querySelectorAll('[data-flow-field]').forEach((input) => {
                    input.name = `${editor.dataset.flowEditor}[${index}][${input.dataset.flowField}]`;
                    input.id = `${editor.dataset.flowEditor}-${index}-${input.dataset.flowField}`;
                    const label = input.closest('.premium-form-field')?.querySelector('label');
                    if (label) label.htmlFor = input.id;
                    syncField(input);
                });
                const members = row.querySelector('[data-flow-members]');
                row.querySelector('[data-flow-add-member]').disabled = members.children.length >= 20;
                [...members.children].forEach((member, memberIndex) => {
                    const id = `${editor.dataset.flowEditor}-${index}-member-${memberIndex}`;
                    member.querySelectorAll('[data-member-field]').forEach((input) => {
                        input.name = `${editor.dataset.flowEditor}[${index}][reviewers][${memberIndex}][${input.dataset.memberField}]${input.type === 'checkbox' ? '[]' : ''}`;
                        input.id = `${id}-${input.dataset.memberField}${input.type === 'checkbox' ? `-${input.value}` : ''}`;
                        if (input.type !== 'checkbox') {
                            input.closest('.premium-form-field').querySelector('label').htmlFor = input.id;
                            syncField(input);
                        }
                    });
                    member.querySelectorAll('[data-member-range]').forEach((input) => {
                        input.id = `${id}-${input.dataset.memberRange}`;
                        input.closest('.premium-form-field').querySelector('label').htmlFor = input.id;
                        syncField(input);
                    });
                    member.querySelector('[data-flow-remove-member]').disabled = members.children.length === 1;
                    syncGrades(member);
                });
            });
            add.disabled = rows.children.length >= 20;
        };
        editor.addEventListener('change', (event) => {
            const member = event.target.closest('[data-flow-member]');
            if (member && event.target.matches('[data-member-field="title"]')) sync();
            else if (member) syncGrades(member);
        });
        editor.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            const row = button?.closest('[data-flow-row]');
            const member = button?.closest('[data-flow-member]');
            if (button?.matches('[data-flow-add]') && rows.children.length < 20) {
                rows.append(template.content.cloneNode(true));
            } else if (row && button?.matches('[data-flow-add-member]') && row.querySelector('[data-flow-members]').children.length < 20) {
                row.querySelector('[data-flow-members]').append(row.querySelector('[data-flow-member-template]').content.cloneNode(true));
            } else if (member && button?.matches('[data-flow-remove-member]') && member.parentElement.children.length > 1) member.remove();
            else if (member && button?.matches('[data-member-apply-range]')) {
                const from = member.querySelector('[data-member-range="from"]').value;
                const to = member.querySelector('[data-member-range="to"]').value;
                const checks = [...member.querySelectorAll('[data-member-field="grade_ids"]')];
                const start = checks.findIndex((input) => input.value === from);
                const end = checks.findIndex((input) => input.value === to);
                if (start < 0 || end < start) {
                    const field = member.querySelector(`[data-member-range="${start < 0 ? 'from' : 'to'}"]`);
                    field.setCustomValidity('Choose a valid range with the starting grade before or equal to the ending grade.');
                    field.reportValidity();
                    return;
                }
                checks.forEach((input, index) => input.checked = index >= start && index <= end);
            } else if (row && button?.matches('[data-flow-remove]')) row.remove();
            else if (row && button?.matches('[data-flow-up]') && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
            else if (row && button?.matches('[data-flow-down]') && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
            else return;
            sync();
        });
        sync();
    });
}
