<div class="modal modal-blur fade" id="wmiYearModal" tabindex="-1" aria-labelledby="wmiYearModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered"><form class="modal-content wmi-year-modal" data-wmi-year-form>
        @csrf
        <div class="modal-header"><span class="wmi-content-icon me-3"><i class="ti ti-calendar-plus" aria-hidden="true"></i></span><h2 class="modal-title" id="wmiYearModalTitle">Add WMI Year</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="alert alert-danger" data-wmi-year-error role="alert" hidden></div>
            <div class="wmi-year-field premium-form-field wmi-modal-field"><label class="form-label" for="wmiYearInput">WMI Year <span class="text-danger">*</span></label><input class="form-control" id="wmiYearInput" name="year" type="text" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" placeholder="2026" autocomplete="off" required aria-describedby="wmiYearHint"><div class="form-hint mt-2" id="wmiYearHint">Enter four digits. The year will display as WMI 2026. Each WMI year must be unique.</div></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" data-wmi-year-save><i class="ti ti-check me-2" aria-hidden="true"></i><span>Save Year</span></button></div>
    </form></div>
</div>
