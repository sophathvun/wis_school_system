const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[char]));

export function renderGroupActions(conversation) {
    const view = '<button type="button" class="school-chat-group-action" data-group-action="view-members"><i class="ti ti-users"></i><span>Group Members</span></button>';
    const management = conversation.can_manage_group ? `
        <button type="button" class="school-chat-group-action" data-group-action="rename"><i class="ti ti-edit"></i><span>Rename</span></button>
        <button type="button" class="school-chat-group-action" data-group-action="members"><i class="ti ti-user-plus"></i><span>Add Member</span></button>
        <button type="button" class="school-chat-group-action" data-group-action="photo"><i class="ti ti-photo"></i><span>Photo</span></button>
        ${conversation.can_assign_group_admins ? '<button type="button" class="school-chat-group-action" data-group-action="admins"><i class="ti ti-shield-star"></i><span>Admin</span></button>' : ''}` : '';
    return `<div class="school-chat-group-action-grid">${view}${management}</div>`;
}

export function renderGroupMembers(conversation) {
    const members = [...(conversation.users || [])].sort((a, b) => Number(Boolean(b.online)) - Number(Boolean(a.online)) || String(a.name || '').localeCompare(String(b.name || '')));
    const online = members.filter((member) => member.online).length;
    const role = (member) => member.is_owner || member.role === 'owner' ? 'Owner'
        : member.is_admin || member.role === 'admin' ? 'Admin' : 'Member';
    return `<div class="chat-group-member-heading"><div class="chat-group-member-group-profile">
        <span class="chat-group-member-photo">${conversation.photo ? `<img src="${escapeHtml(conversation.photo)}" alt="${escapeHtml(conversation.title || 'Group')}">` : '<i class="ti ti-users" aria-hidden="true"></i>'}</span>
        <strong>${escapeHtml(conversation.title || 'Group')}</strong>
        </div><div>${members.length} members &middot; ${online} online &middot; ${members.length - online} offline</div></div>
        <div class="chat-group-member-list"><table class="chat-group-member-table" aria-label="Group members">
            <colgroup><col><col class="chat-group-member-role-column"><col class="chat-group-member-status-column"></colgroup>
            <thead><tr><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Status</th></tr></thead>
            <tbody>${members.map((member) => `
                <tr class="chat-group-member-row">
                    <td><div class="chat-group-member-identity">
                        <span class="chat-group-member-photo">${member.photo ? `<img src="${escapeHtml(member.photo)}" alt="${escapeHtml(member.name || 'Staff')}">` : '<i class="ti ti-user"></i>'}</span>
                        <span class="chat-group-member-name">${escapeHtml(member.name || 'Staff')}</span>
                    </div></td>
                    <td><span class="chat-group-member-role ${role(member).toLowerCase()}">${role(member)}</span></td>
                    <td><span class="chat-group-member-status ${member.online ? 'online' : 'offline'}"><span aria-hidden="true"></span>${member.online ? 'Online' : 'Offline'}</span></td>
                </tr>`).join('') || '<tr><td colspan="3" class="text-secondary">No members found.</td></tr>'}</tbody>
        </table></div>`;
}

export async function showGroupMembers(conversation) {
    if (window.Swal) {
        await window.Swal.fire({
            title: 'Group Members',
            html: renderGroupMembers(conversation),
            showConfirmButton: false,
            showCloseButton: true,
            customClass: { popup: 'school-chat-group-members-swal' },
        });
        return;
    }
    const dialog = document.createElement('dialog');
    dialog.className = 'chat-group-members-dialog';
    dialog.innerHTML = `<h3>Group Members</h3>${renderGroupMembers(conversation)}<button type="button" class="btn btn-outline-secondary mt-3">Close</button>`;
    dialog.querySelector('button').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    document.body.append(dialog);
    dialog.showModal();
}
