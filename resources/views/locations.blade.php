@extends('layouts.app')
@section('title', 'Locations')
@section('page-header')<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Settings</div><h2 class="page-title">Locations</h2></div><div class="col-auto"><button class="btn btn-primary" id="newLocation"><i class="ti ti-plus icon"></i> New Location</button></div></div></div>@endsection
@section('content')
<div class="col-12">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Country and Location Lists</h3></div>
        <div class="card-body border-bottom py-3">
            <div class="row g-2 align-items-center justify-content-between">
                <div class="col-auto text-secondary">
                    Level
                    <div class="mx-2 d-inline-block">
                        <select id="location-level" class="form-control form-control-sm">
                            <option value="country">Country</option>
                            <option value="province">Province / City</option>
                            <option value="district">District / Khan</option>
                            <option value="commune">Commune</option>
                            <option value="village">Village</option>
                        </select>
                    </div>
                    <span class="ms-2">Show</span>
                    <div class="mx-2 d-inline-block">
                        <select id="locations-per-page" class="form-control form-control-sm">
                            <option value="10" selected>10 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>
                    </div>
                    entries
                </div>

                <div class="col-auto ms-auto">
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search icon"></i></span>
                        <input id="locations-search" class="form-control form-control-sm" placeholder="Search locations">
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive table-vcenter text-nowrap">
            <table class="table card-table">
                <thead id="locations-head"></thead>
                <tbody id="locationsTable"><tr><td colspan="6" class="text-center">Loading locations...</td></tr></tbody>
            </table>
        </div>
        <div class="card-footer">
            <div class="row g-2">
                <div class="col-12 d-flex justify-content-center" id="locations-pagination-container"></div>
            </div>
        </div>
    </div>
</div>
<div class="modal modal-blur fade" id="locationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="locationForm"><div class="modal-header"><h5 class="modal-title" id="locationModalTitle">Create Location</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-danger d-none" data-alert></div><input type="hidden" id="location_id"><div class="mb-3 d-none" id="location-parent-wrap"><label class="form-label" id="location-parent-label">Parent</label><select id="location-parent" class="form-select d-none"></select><div id="location-parent-combobox" class="location-combobox"><button type="button" id="location-parent-toggle" class="location-combobox-toggle"><span id="location-parent-selected">Select Parent</span><i class="ti ti-chevron-down"></i></button><div id="location-parent-menu" class="location-combobox-menu d-none"><input type="search" id="location-parent-search" class="form-control school-profile-khmer" placeholder="Search"><div id="location-parent-results" class="location-combobox-results"></div></div></div></div><div class="mb-3"><label class="form-label">Name (English) *</label><input id="location-name-en" class="form-control" required></div><div class="mb-3"><label class="form-label">Name (Khmer)</label><input id="location-name-kh" class="form-control"></div><div class="row g-3" id="country-extra"><div class="col-6"><label class="form-label">Country Code</label><input id="country-code" class="form-control" placeholder="kh"></div><div class="col-6"><label class="form-label">Flag Path</label><input id="flag-path" class="form-control" placeholder="flags/cambodia.svg"></div></div><div class="mt-3"><label class="form-label">Status</label><select id="location-status" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div></div><div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" id="locationSubmit">Save</button></div></form></div></div></div>
@vite('resources/js/locations.js')
<script>
(() => {
    const syncCountryNationality = async () => {
        const level = document.getElementById('location-level');
        const head = document.getElementById('locations-head');
        const table = document.getElementById('locationsTable');
        if (!level || level.value !== 'country' || !head || !table) return;

        const header = head.querySelector('tr');
        if (!header || header.querySelector('[data-nationality-column]')) return;
        if (table.textContent.includes('Loading locations...')) return;

        const response = await fetch('/locations/fetch?level=country&perPage=1000&sortBy=name&sortDir=asc');
        if (!response.ok) return;
        const result = await response.json();
        const countries = new Map((result.data || []).map((item) => [String(item.country_name_en), item]));

        const flagHeader = Array.from(header.children).find((cell) => cell.textContent.trim() === 'Flag');
        if (!flagHeader) return;
        const nationalityHeader = document.createElement('th');
        nationalityHeader.dataset.nationalityColumn = 'true';
        nationalityHeader.textContent = 'Nationality';
        flagHeader.before(nationalityHeader);

        table.querySelectorAll('tr').forEach((row) => {
            const nameCell = row.children[1];
            if (!nameCell) return;
            const country = countries.get(nameCell.textContent.trim().split('\n').filter(Boolean).pop().trim());
            const cell = document.createElement('td');
            cell.dataset.nationalityColumn = 'true';
            cell.innerHTML = `<div class="school-profile-khmer">${country?.nationality_name_kh || '-'}</div><div>${country?.nationality_name_en || '-'}</div>`;
            const flagCell = row.children[2];
            if (flagCell) flagCell.before(cell);
        });
    };

    window.addEventListener('load', () => {
        syncCountryNationality();
        setInterval(syncCountryNationality, 800);
    }, { once: true });
})();
</script>
<script>
(() => {
    const setupCountryFlagUpload = () => {
        const extra = document.getElementById('country-extra');
        if (!extra || document.getElementById('country-flag')) return;
        extra.insertAdjacentHTML('beforeend', '<div class="col-6"><label class="form-label">Nationality (English)</label><input id="nationality-name-en" class="form-control"></div><div class="col-6"><label class="form-label">Nationality (Khmer)</label><input id="nationality-name-kh" class="form-control school-profile-khmer"></div><div class="col-12"><label class="form-label">Flag Image</label><div class="logo-dropzone" id="countryFlagDropzone" tabindex="0"><i class="ti ti-cloud-upload logo-dropzone-icon"></i><div><strong>Drag and drop flag image here</strong></div><div class="text-secondary">or click to upload a file</div><input type="file" class="d-none" id="country-flag" accept="image/jpeg,image/png,image/webp"><img id="country-flag-preview" class="d-none mt-2" style="max-width:90px;max-height:60px;object-fit:contain" alt="Flag preview"></div></div>');
        const dropzone = document.getElementById('countryFlagDropzone');
        const input = document.getElementById('country-flag');
        const preview = document.getElementById('country-flag-preview');
        const showPreview = (file) => { if (!file) return; preview.src = URL.createObjectURL(file); preview.classList.remove('d-none'); };
        input.addEventListener('change', () => showPreview(input.files?.[0]));
        dropzone.addEventListener('click', () => input.click());
        dropzone.addEventListener('dragover', (event) => { event.preventDefault(); dropzone.classList.add('is-dragging'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-dragging'));
        dropzone.addEventListener('drop', (event) => { event.preventDefault(); dropzone.classList.remove('is-dragging'); const file = event.dataTransfer.files?.[0]; if (!file) return; const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; showPreview(file); });
    };

    window.addEventListener('load', () => {
        setupCountryFlagUpload();
        setInterval(setupCountryFlagUpload, 800);
        document.getElementById('locationForm')?.addEventListener('submit', async (event) => {
            const level = document.getElementById('location-level')?.value;
            if (level !== 'country') return;
            event.preventDefault();
            event.stopImmediatePropagation();
            const body = new FormData();
            [['level', level], ['id', document.getElementById('location_id')?.value || ''], ['name_en', document.getElementById('location-name-en')?.value || ''], ['name_kh', document.getElementById('location-name-kh')?.value || ''], ['country_code', document.getElementById('country-code')?.value || ''], ['flag_path', document.getElementById('flag-path')?.value || ''], ['nationality_name_en', document.getElementById('nationality-name-en')?.value || ''], ['nationality_name_kh', document.getElementById('nationality-name-kh')?.value || ''], ['status', document.getElementById('location-status')?.value || '1']].forEach(([key, value]) => body.append(key, value));
            const file = document.getElementById('country-flag')?.files?.[0];
            if (file) body.append('flag_image', file);
            const response = await fetch('/locations/save', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body });
            const result = await response.json();
            if (!response.ok) { const alert = document.querySelector('#locationForm [data-alert]'); if (alert) { alert.textContent = result.message || 'Unable to save location.'; alert.classList.remove('d-none'); } return; }
            window.location.reload();
        }, true);
    }, { once: true });
})();
</script>
<style>
    .location-combobox {
        position: relative;
    }

    .location-combobox-toggle {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: space-between;
        padding: .55rem .75rem;
        border: 1px solid var(--tblr-border-color);
        border-radius: var(--tblr-border-radius);
        color: var(--tblr-body-color);
        background: var(--tblr-bg-forms);
        text-align: left;
    }

    .location-combobox-menu {
        position: absolute;
        z-index: 1080;
        top: calc(100% + .35rem);
        right: 0;
        left: 0;
        padding: .55rem;
        border: 1px solid var(--tblr-border-color);
        border-radius: var(--tblr-border-radius);
        background: var(--tblr-bg-surface);
        box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .25);
    }

    .location-combobox-results {
        max-height: 16rem;
        margin-top: .45rem;
        overflow-y: auto;
    }

    .location-combobox-option {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .5rem .55rem;
        border: 0;
        border-radius: .25rem;
        color: var(--tblr-body-color);
        background: transparent;
        text-align: left;
    }

    .location-combobox-option:hover {
        background: rgba(var(--tblr-primary-rgb), .12);
    }

    .location-combobox-option.is-selected {
        color: var(--tblr-primary);
        background: rgba(var(--tblr-primary-rgb), .16);
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const level = document.getElementById('location-level');
    const parent = document.getElementById('location-parent');
    const parentToggle = document.getElementById('location-parent-toggle');
    const parentMenu = document.getElementById('location-parent-menu');
    const parentSearch = document.getElementById('location-parent-search');
    const parentResults = document.getElementById('location-parent-results');
    const parentSelected = document.getElementById('location-parent-selected');
    const parentWrap = document.getElementById('location-parent-wrap');
    const parentLabel = document.getElementById('location-parent-label');
    const modalEl = document.getElementById('locationModal');
    if (!level || !parent || !parentToggle || !parentMenu || !parentSearch || !parentResults || !parentSelected || !parentWrap || !parentLabel || !modalEl) return;

    let parentItems = [];

    const parentLabels = {
        province: 'Country',
        district: 'Province / City',
        commune: 'District / Khan',
        village: 'Commune',
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const render = () => {
        const term = parentSearch.value.trim().toLowerCase();
        const items = term ? parentItems.filter((item) => item.label.toLowerCase().includes(term)) : parentItems;

        parentResults.innerHTML = '';
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'text-secondary px-2 py-2';
            empty.textContent = 'No options found';
            parentResults.appendChild(empty);
            return;
        }

        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `location-combobox-option${String(parent.value) === String(item.value) ? ' is-selected' : ''}`;
            button.dataset.id = item.value;
            button.dataset.parentId = item.value;
            button.dataset.label = item.label;
            button.dataset.parentLabel = item.label;
            button.innerHTML = `<span class="school-profile-khmer">${escapeHtml(item.html)}</span>${String(parent.value) === String(item.value) ? '<i class="ti ti-check"></i>' : ''}`;
            parentResults.appendChild(button);
        });
    };

    const setSelectedText = () => {
        const selected = parentItems.find((item) => String(item.value) === String(parent.value));
        parentSelected.textContent = selected ? selected.label : `Select ${parentLabels[level.value] || 'Parent'}`;
    };

    const openMenu = () => {
        parentMenu.classList.remove('d-none');
        parentSearch.value = '';
        render();
        parentSearch.focus();
    };

    const closeMenu = () => {
        parentMenu.classList.add('d-none');
    };

    const buildItemsFromSelect = () => {
        parentItems = Array.from(parent.options)
            .filter((option) => option.value)
            .map((option) => ({
                value: option.value,
                label: option.textContent.trim(),
                html: option.textContent.trim(),
            }));
    };

    const buildVillageItems = () => {
        fetch('/locations/options')
            .then((response) => response.json())
            .then((json) => {
                parentItems = (json.communes || []).map((item) => {
                    const label = item.commune_name_kh ? `${item.commune_name_kh} - ${item.commune_name_en}` : item.commune_name_en;
                    return { value: item.id, label, html: label };
                });
                setSelectedText();
                if (!parentMenu.classList.contains('d-none')) render();
            });
    };

    const syncParentPicker = () => {
        const hasParent = ['province', 'district', 'commune', 'village'].includes(level.value);

        parentWrap.classList.toggle('d-none', !hasParent);
        closeMenu();
        if (!hasParent) return;

        parentLabel.textContent = parentLabels[level.value];
        parent.classList.add('d-none');
        parentItems = [];
        parentSelected.textContent = `Select ${parentLabels[level.value]}`;
        if (level.value === 'village') {
            buildVillageItems();
        } else {
            buildItemsFromSelect();
            setSelectedText();
        }
    };

    level.addEventListener('change', () => setTimeout(syncParentPicker, 0));
    parentToggle.addEventListener('click', openMenu);
    parentSearch.addEventListener('input', render);
    parentResults.addEventListener('click', (event) => {
        const button = event.target.closest('[data-id]');
        if (!button) return;
        parent.value = button.dataset.id || '';
        parentSearch.value = button.dataset.label || '';
        setSelectedText();
        closeMenu();
    });
    document.addEventListener('click', (event) => {
        if (!parentWrap.contains(event.target)) closeMenu();
    });
    modalEl.addEventListener('shown.bs.modal', () => setTimeout(syncParentPicker, 0));
    modalEl.addEventListener('hidden.bs.modal', closeMenu);
    syncParentPicker();
});
</script>
@endsection
