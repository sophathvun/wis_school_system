<div class="d-flex justify-content-between align-items-center permission-full-access-bar border rounded px-3 py-2 mb-3">
  <span class="fw-semibold"><i class="ti ti-lock-access me-1"></i>Full Access</span>
  <button type="button" class="status-toggle {{ ($fullAccess ?? false) ? 'is-active' : '' }}" data-full-access data-status="{{ ($fullAccess ?? false) ? 1 : 0 }}" aria-pressed="{{ ($fullAccess ?? false) ? 'true' : 'false' }}" @disabled($fullAccessLocked ?? false)>
    <span class="status-toggle-label">{{ ($fullAccess ?? false) ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span>
  </button>
</div>
