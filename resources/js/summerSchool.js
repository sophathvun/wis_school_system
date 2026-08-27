import intlTelInput from "intl-tel-input";
import "intl-tel-input/styles";
import { showError } from "./helpers/sweet-alert2";

const summerField = (id) => document.getElementById(id);
const summerClearRegistrationPlaceholders = () => {
    const form = summerField("summerSchoolForm");
    if (!form) return;
    form.querySelectorAll("[placeholder]").forEach((control) =>
        control.removeAttribute("placeholder"),
    );
    form.querySelectorAll('select option[value=""]').forEach((option) => {
        option.textContent = "";
    });
};
const summerModal = summerField("summerSchoolModal")
    ? new bootstrap.Modal(summerField("summerSchoolModal"))
    : null;
const summerConvertModal = summerField("summerConvertModal")
    ? new bootstrap.Modal(summerField("summerConvertModal"))
    : null;
let summerOptions = {};
let summerStudentEnrollments = [];
const summerPhoneSyncs = [];
const summerExternalPhotoInput = summerField("summerExternalPhoto");
const summerExternalPhotoDropzone = summerField("summerExternalPhotoDropzone");
const summerExternalPhotoPreview = summerField("summerExternalPhotoPreview");
const summerExternalPhotoPreviewWrap = summerField("summerExternalPhotoPreviewWrap");
const summerShowExternalPhoto = (file) => {
    if (!file || !file.type?.startsWith("image/")) return;
    summerExternalPhotoPreview.src = URL.createObjectURL(file);
    summerExternalPhotoPreviewWrap.classList.remove("d-none");
};
summerExternalPhotoDropzone?.addEventListener("click", () => summerExternalPhotoInput?.click());
summerExternalPhotoInput?.addEventListener("change", () => summerShowExternalPhoto(summerExternalPhotoInput.files?.[0]));
summerExternalPhotoDropzone?.addEventListener("dragover", (event) => {
    event.preventDefault();
    summerExternalPhotoDropzone.classList.add("is-dragging");
});
summerExternalPhotoDropzone?.addEventListener("dragleave", () => summerExternalPhotoDropzone.classList.remove("is-dragging"));
summerExternalPhotoDropzone?.addEventListener("drop", (event) => {
    event.preventDefault();
    summerExternalPhotoDropzone.classList.remove("is-dragging");
    const file = event.dataTransfer.files?.[0];
    if (!file || !summerExternalPhotoInput) return;
    const transfer = new DataTransfer();
    transfer.items.add(file);
    summerExternalPhotoInput.files = transfer.files;
    summerShowExternalPhoto(file);
});
const summerEsc = (value) =>
    String(value ?? "").replace(
        /[&<>"']/g,
        (char) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;",
            })[char],
    );
const summerFill = (id, items, label, placeholder) => {
    const select = summerField(id);
    if (!select) return;
    const current = select.value;
    select.innerHTML =
        '<option value=""></option>' +
        items
            .map(
                (item) =>
                    `<option value="${item.id}">${summerEsc(item[label])}</option>`,
            )
            .join("");
    if (current && Array.from(select.options).some((option) => option.value === current))
        select.value = current;
};
const summerApplyPeriodOptions = (items = []) => {
    const select = summerField("summerAcademicYear");
    if (!select) return;
    const current = select.value;
    summerFill("summerAcademicYear", items, "academic_year", "");
    if (current && Array.from(select.options).some((option) => option.value === current))
        select.value = current;
    if (!select.value && items.length) select.value = String(items[0].id);
};
const summerPeriodOptionsPromise = fetch("/summer-school/period-options", {
    headers: { Accept: "application/json" },
})
    .then((response) => {
        if (!response.ok) throw new Error("Unable to load Summer periods.");
        return response.json();
    })
    .then((items) => {
        summerApplyPeriodOptions(items);
        return items;
    });
const summerSearchableFilters = {};
const summerSetupSearchableFilter = (id, label, skipFirstOption = true) => {
    const select = summerField(id);
    if (!select || summerSearchableFilters[id]) return;
    if (id === "summerStudentYearFilter")
        summerSetFilterOptions(
            id,
            summerStudentEnrollments
                .map((row) => [
                    row.academic_year_id,
                    row.academic_year?.academic_year,
                ])
                .filter(([value, text]) => value && text),
        );
    select.classList.add("d-none");
    select.insertAdjacentHTML(
        "afterend",
        `<div class="location-combobox summer-search-combobox"><button type="button" class="location-combobox-toggle"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search ${label}"><div class="location-combobox-results"></div></div></div>`,
    );
    const combo = select.nextElementSibling;
    const toggle = combo.querySelector(".location-combobox-toggle");
    const menu = combo.querySelector(".location-combobox-menu");
    const search = combo.querySelector(".location-combobox-search");
    const selected = combo.querySelector(".location-combobox-selected");
    const results = combo.querySelector(".location-combobox-results");
    const sync = () => {
        selected.textContent = select.value
            ? select.selectedOptions?.[0]?.textContent || ""
            : "";
    };
    const render = () => {
        const term = search.value.toLowerCase().trim();
        const options = Array.from(select.options)
            .slice(skipFirstOption ? 1 : 0)
            .filter(
                (option) =>
                    !term || option.textContent.toLowerCase().includes(term),
            );
        results.innerHTML = options.length
            ? options
                  .map(
                      (option) =>
                          `<button type="button" class="location-combobox-option" data-summer-filter-value="${summerEsc(option.value)}">${summerEsc(option.textContent)}</button>`,
                  )
                  .join("")
            : '<div class="text-secondary px-2 py-2">No options found</div>';
    };
    toggle.addEventListener("click", () => {
        document
            .querySelectorAll("#summerSchoolModal .location-combobox-menu")
            .forEach((other) => {
                if (other !== menu) other.classList.add("d-none");
            });
        menu.classList.toggle("d-none");
        if (!menu.classList.contains("d-none")) {
            search.value = "";
            render();
            search.focus();
        }
    });
    search.addEventListener("input", render);
    results.addEventListener("click", (event) => {
        const option = event.target.closest("[data-summer-filter-value]");
        if (!option) return;
        select.value = option.dataset.summerFilterValue;
        select.dispatchEvent(new Event("change", { bubbles: true }));
        menu.classList.add("d-none");
    });
    select.addEventListener("change", () => {
        sync();
        render();
    });
    summerSearchableFilters[id] = { select, sync, render };
    sync();
};
const summerRefreshSearchableFilters = () =>
    Object.values(summerSearchableFilters).forEach(({ sync, render }) => {
        sync();
        render();
    });
document.addEventListener("click", (event) => {
    if (!event.target.closest(".summer-search-combobox"))
        document
            .querySelectorAll(".summer-search-combobox .location-combobox-menu")
            .forEach((menu) => menu.classList.add("d-none"));
});
const summerAddEnrollmentParityFields = () => {
    if (summerField("summerExternalComplete")) return;
    document.querySelectorAll("#summerSchoolForm .summer-external-field").forEach((node) => node.remove());
    const panel = document.createElement("div");
    panel.id = "summerExternalComplete";
    panel.className = "summer-external-field d-none summer-external-complete";
    panel.innerHTML = `<div class="enrollment-profile-card summer-form-card"><h4>Student Information</h4><div class="row g-3"><div class="col-md-3 premium-floating-field"><label class="form-label">Student No. (Auto)</label><input name="student_no" class="form-control" readonly></div><div class="col-md-3 premium-floating-field"><label class="form-label">Student ID</label><input name="student_id" class="form-control"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Existing Family / Sibling</label><input name="existing_family_number" class="form-control"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Family Number</label><input name="family_number" class="form-control"></div></div><div class="row g-3"><div class="col-md-3 premium-floating-field"><label class="form-label">Full Name (English) *</label><input name="full_name_en" class="form-control"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Full Name (Khmer)</label><input name="full_name_kh" class="form-control school-profile-khmer"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Gender (English)</label><select name="gender" class="form-select"><option value=""></option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div><div class="col-md-3 premium-floating-field"><label class="form-label">Gender (Khmer)</label><input name="gender_kh" class="form-control school-profile-khmer"></div></div><div class="row g-3"><div class="col-md-3 premium-floating-field"><label class="form-label">Nationality (English)</label><select name="nationality_country_id" id="summerNationality" class="form-select"></select></div><div class="col-md-3 premium-floating-field"><label class="form-label">Nationality (Khmer)</label><input name="nationality_kh" class="form-control school-profile-khmer" readonly></div><div class="col-md-3 premium-floating-field"><label class="form-label">Date of Birth (English)</label><input type="date" name="date_of_birth" class="form-control"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Date of Birth (Khmer)</label><input name="date_of_birth_kh" class="form-control school-profile-khmer" readonly></div></div><div class="row g-3"><div class="col-md-3 premium-floating-field"><label class="form-label">Home Phone</label><input name="home_phone" class="form-control"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div><div class="col-12 premium-floating-field"><label class="form-label">Remarks</label><textarea name="summer_remarks" class="form-control" rows="2"></textarea></div></div><div class="summer-photo-field"><label class="form-label summer-photo-label">Student Photo</label><div class="summer-student-photo-upload"><div class="logo-dropzone summer-external-photo-dropzone" id="summerExternalPhotoDropzone" tabindex="0"><i class="ti ti-cloud-upload logo-dropzone-icon"></i><div><strong>Drag and drop student photo here</strong></div><div class="text-secondary">or click, paste, or upload a file</div><input type="file" name="photo" id="summerExternalPhoto" class="d-none" accept="image/jpeg,image/png,image/webp"><div class="d-none summer-external-photo-preview-wrap" id="summerExternalPhotoPreviewWrap"><img id="summerExternalPhotoPreview" class="summer-external-photo-preview" alt="Student photo preview"></div></div><small class="form-hint">JPG, PNG, or WEBP. Maximum size: 2 MB.</small></div></div></div><div class="enrollment-profile-card summer-form-card"><h4>Place of Birth</h4><div class="row g-3 summer-five-column-row"><div class="premium-floating-field"><label class="form-label">Country (English)</label><select name="birth_country_id" id="summerBirthCountry" class="form-select"></select></div><div class="premium-floating-field"><label class="form-label">Country (Khmer)</label><input name="birth_country_kh" class="form-control school-profile-khmer" readonly></div><div class="premium-floating-field"><label class="form-label">Province / City</label><select name="birth_province_id" id="summerBirthProvince" class="form-select"></select></div><div class="premium-floating-field"><label class="form-label">District / Khan</label><select name="birth_district_id" id="summerBirthDistrict" class="form-select"></select></div><div class="premium-floating-field"><label class="form-label">Commune</label><select name="birth_commune_id" id="summerBirthCommune" class="form-select"></select></div><div class="premium-floating-field"><label class="form-label">Village</label><select name="birth_village_id" id="summerBirthVillage" class="form-select"></select></div></div></div><div class="enrollment-profile-card summer-form-card"><h4>Home Address</h4><div class="row g-3 summer-five-column-row"><div class="premium-floating-field"><label class="form-label">Country (English)</label><select name="address_country_id" id="summerAddressCountry" class="form-select"></select></div><div class="premium-floating-field"><label class="form-label">Country (Khmer)</label><input name="address_country_kh" class="form-control school-profile-khmer" readonly></div><div class="premium-floating-field"><label class="form-label">Province / City</label><select name="address_province_id" id="summerAddressProvince" class="form-select"></select></div><div class="premium-floating-field"><label class="form-label">District / Khan</label><select name="address_district_id" id="summerAddressDistrict" class="form-select"></select></div><div class="premium-floating-field"><label class="form-label">Commune</label><select name="address_commune_id" id="summerAddressCommune" class="form-select"></select></div><div class="premium-floating-field"><label class="form-label">Village</label><select name="address_village_id" id="summerAddressVillage" class="form-select"></select></div></div><div class="row g-3 summer-four-column-row"><div class="premium-floating-field"><label class="form-label">House No. (English)</label><input name="address_house_no_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">House No. (Khmer)</label><input name="address_house_no_kh" class="form-control school-profile-khmer" readonly></div><div class="premium-floating-field"><label class="form-label">Street No. (English)</label><input name="address_street_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Street No. (Khmer)</label><input name="address_street_kh" class="form-control school-profile-khmer" readonly></div></div><div class="row g-3"><div class="col-md-6 premium-floating-field"><label class="form-label">Current Address (English)</label><textarea name="current_address_en" class="form-control" rows="1"></textarea></div><div class="col-md-6 premium-floating-field"><label class="form-label">Current Address (Khmer)</label><textarea name="current_address_kh" class="form-control school-profile-khmer" rows="1"></textarea></div></div></div><div class="enrollment-profile-card summer-form-card"><h4>Previous School and Assessment</h4><div class="row g-3 summer-four-column-row"><div class="premium-floating-field"><label class="form-label">Previous School *</label><input name="previous_school" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Tested By</label><input name="tested_by" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Experience English</label><textarea name="experienced_english" class="form-control" rows="1"></textarea></div><div class="premium-floating-field"><label class="form-label">Test Result</label><textarea name="test_result" class="form-control" rows="1"></textarea></div></div><div class="row g-3"><div class="col-md-6 premium-floating-field"><label class="form-label">Continue at Western?</label><select name="continue_at_western" class="form-select"><option value="pending">Pending Decision</option><option value="yes">Yes</option><option value="no">No</option></select></div></div></div><div class="enrollment-profile-card summer-form-card"><h4>Family Information</h4><div class="summer-family-subsection"><h5>Mother</h5><div class="row g-3 summer-family-grid"><div class="premium-floating-field"><label class="form-label">Mother Name (English) *</label><input name="mother_name_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Mother Name (Khmer)</label><input name="mother_name_kh" class="form-control school-profile-khmer"></div><div class="premium-floating-field"><label class="form-label">Occupation (English)</label><input name="mother_occupation_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Occupation (Khmer)</label><input name="mother_occupation_kh" class="form-control school-profile-khmer"></div><div class="premium-floating-field"><label class="form-label">Nationality (English)</label><input name="mother_nationality_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Phone Number</label><input name="mother_phone" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Work Place</label><input name="mother_workplace" class="form-control"></div></div></div><div class="summer-family-subsection"><h5>Father</h5><div class="row g-3 summer-family-grid"><div class="premium-floating-field"><label class="form-label">Father Name (English) *</label><input name="father_name_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Father Name (Khmer)</label><input name="father_name_kh" class="form-control school-profile-khmer"></div><div class="premium-floating-field"><label class="form-label">Occupation (English)</label><input name="father_occupation_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Occupation (Khmer)</label><input name="father_occupation_kh" class="form-control school-profile-khmer"></div><div class="premium-floating-field"><label class="form-label">Nationality (English)</label><input name="father_nationality_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Phone Number</label><input name="father_phone" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Work Place</label><input name="father_workplace" class="form-control"></div></div></div><div class="summer-family-subsection"><h5>Guardian</h5><div class="row g-3 summer-family-grid"><div class="premium-floating-field"><label class="form-label">Guardian Name (English)</label><input name="guardian_name_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Guardian Name (Khmer)</label><input name="guardian_name_kh" class="form-control school-profile-khmer"></div><div class="premium-floating-field"><label class="form-label">Occupation (English)</label><input name="guardian_occupation_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Occupation (Khmer)</label><input name="guardian_occupation_kh" class="form-control school-profile-khmer"></div><div class="premium-floating-field"><label class="form-label">Nationality (English)</label><input name="guardian_nationality_en" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Phone Number</label><input name="guardian_phone" class="form-control"></div><div class="premium-floating-field"><label class="form-label">Work Place</label><input name="guardian_workplace" class="form-control"></div></div></div></div><div class="enrollment-document-card summer-form-card"><h4>Student Documents <span class="text-secondary fw-normal fs-5">(Optional)</span></h4><div class="row g-3"><div class="col-md-3 premium-floating-field"><label class="form-label">Document Type</label><select name="document_type_id" id="summerDocumentType" class="form-select"></select></div><div class="col-md-3 premium-floating-field"><label class="form-label">Document Title</label><input name="document_title" class="form-control"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Document Number</label><input name="document_number" class="form-control"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Document File</label><input type="file" name="document_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div><div class="col-12 premium-floating-field"><label class="form-label">Description</label><textarea name="document_description" class="form-control" rows="2"></textarea></div></div></div>`;
    const initialStudentNo = panel.querySelector('[name="student_no"]');
    if (initialStudentNo && summerOptions.nextStudentNo) initialStudentNo.value = summerOptions.nextStudentNo;
    const photoUploadField = panel.querySelector(".summer-photo-field");
    const photoDropzoneInside = photoUploadField?.querySelector(".summer-external-photo-dropzone");
    const photoLabel = photoUploadField?.querySelector(".summer-photo-label");
    const photoHint = photoUploadField?.querySelector(".summer-student-photo-upload > .form-hint");
    if (photoDropzoneInside && photoLabel) {
        photoLabel.classList.add("summer-photo-label-inside");
        photoDropzoneInside.prepend(photoLabel);
    }
    if (photoDropzoneInside && photoHint) {
        const photoInputInside = photoDropzoneInside.querySelector('input[type="file"]');
        photoDropzoneInside.insertBefore(photoHint, photoInputInside || null);
    }
    const requiredFields = [["student_id", "Student ID"], ["full_name_en", "Full Name (English)"]];
    requiredFields.forEach(([name, label]) => {
        const field = panel.querySelector(`[name="${name}"]`);
        const labelElement = field?.closest(".premium-floating-field")?.querySelector(".form-label");
        if (field) field.required = true;
        if (labelElement) labelElement.innerHTML = `${label} <span class="text-danger">*</span>`;
    });
    const emailInput = panel.querySelector('[name="email"]');
    if (emailInput && !emailInput.closest(".summer-email-input")) {
        const emailWrapper = document.createElement("div");
        emailWrapper.className = "summer-email-input";
        emailWrapper.innerHTML = '<i class="ti ti-mail summer-email-icon"></i>';
        emailInput.parentElement?.insertBefore(emailWrapper, emailInput);
        emailWrapper.appendChild(emailInput);
        emailInput.type = "email";
        emailInput.inputMode = "email";
        emailInput.autocomplete = "email";
        emailInput.closest(".premium-floating-field")?.classList.add("summer-email-field");
        const emailControlWrapper = emailInput.closest(".summer-email-input");
        const emailFeedback = document.createElement("div");
        emailFeedback.className = "summer-email-feedback";
        emailFeedback.textContent = "Enter a valid email address, for example name@example.com.";
        emailControlWrapper?.parentElement?.appendChild(emailFeedback);
        const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || "").trim());
        const validateEmail = (showAlert = false) => {
            const value = emailInput.value.trim();
            const valid = !value || isValidEmail(value);
            emailInput.setCustomValidity(valid ? "" : "Please enter a valid email address, for example name@example.com.");
            emailControlWrapper?.classList.toggle("is-invalid", !valid);
            emailFeedback.classList.toggle("is-visible", !valid);
            emailInput.setAttribute("aria-invalid", valid ? "false" : "true");
            if (!valid && showAlert) {
                showError("Invalid Email", "Please enter a valid email address, for example name@example.com.");
                window.setTimeout(() => emailInput.focus(), 0);
            }
            return valid;
        };
        emailInput.addEventListener("input", () => validateEmail(false));
        emailInput.addEventListener("blur", () => validateEmail(true));
    }
    const dobInput = panel.querySelector('[name="date_of_birth"]');
    if (dobInput) {
        const dobField = dobInput.closest(".premium-floating-field");
        dobField?.classList.add("summer-dob-always-float");
        const dobParent = dobInput.parentElement;
        const dobNext = dobInput.nextSibling;
        const picker = document.createElement("div");
        picker.className = "date-picker summer-date-picker";
        dobInput.type = "hidden";
        picker.appendChild(dobInput);
        picker.insertAdjacentHTML("beforeend", '<div class="date-picker-trigger summer-dob-trigger"><i class="ti ti-cake summer-dob-cake"></i><input type="text" class="date-picker-display" inputmode="numeric" autocomplete="off"><i class="ti ti-calendar summer-dob-calendar"></i></div><div class="date-picker-popup d-none"><div class="date-picker-header"><button type="button" class="date-picker-nav" data-dob-nav="prev"><i class="ti ti-chevron-left"></i></button><button type="button" class="date-picker-year-toggle"><span class="date-picker-month-label"></span></button><button type="button" class="date-picker-nav" data-dob-nav="next"><i class="ti ti-chevron-right"></i></button></div><div class="date-picker-grid"><div class="date-picker-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div><div class="date-picker-days"></div></div></div>');
        dobParent?.insertBefore(picker, dobNext);
        const trigger = picker.querySelector(".date-picker-trigger");
        const popup = picker.querySelector(".date-picker-popup");
        const display = picker.querySelector(".date-picker-display");
        const monthLabel = picker.querySelector(".date-picker-month-label");
        const days = picker.querySelector(".date-picker-days");
        const dobKhmer = panel.querySelector('[name="date_of_birth_kh"]');
        if (dobKhmer) {
            const khmerInputParent = dobKhmer.parentElement;
            khmerInputParent?.classList.add("summer-dob-always-float");
            const khmerIconGroup = document.createElement("div");
            khmerIconGroup.className = "input-icon summer-dob-khmer-input";
            khmerIconGroup.innerHTML = '<span class="input-icon-addon"><i class="ti ti-cake"></i></span>';
            khmerIconGroup.appendChild(dobKhmer);
            khmerInputParent?.appendChild(khmerIconGroup);
        }
        const khmerDigits = (value) => String(value).replace(/[0-9]/g, (digit) => "០១២៣៤៥៦៧៨៩"[Number(digit)]);
        const khmerMonths = ["មករា", "កុម្ភៈ", "មីនា", "មេសា", "ឧសភា", "មិថុនា", "កក្កដា", "សីហា", "កញ្ញា", "តុលា", "វិច្ឆិកា", "ធ្នូ"];
        let cursor = new Date();
        const formatIso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
        const selectedDate = () => dobInput.value ? new Date(`${dobInput.value}T00:00:00`) : null;
        const syncDate = () => {
            const date = selectedDate();
            const shortMonths = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            display.value = date && !Number.isNaN(date.getTime())
                ? `${String(date.getDate()).padStart(2, "0")}-${shortMonths[date.getMonth()]}-${date.getFullYear()}`
                : "";
            if (dobKhmer) {
                dobKhmer.value = date && !Number.isNaN(date.getTime())
                    ? `${khmerDigits(date.getDate())} ${khmerMonths[date.getMonth()]} ${khmerDigits(date.getFullYear())}`
                    : "";
                dobKhmer.closest(".premium-floating-field")?.classList.toggle("has-value", Boolean(date));
            }
            dobField?.classList.toggle("has-value", Boolean(date));
        };
        const renderCalendar = () => {
            const year = cursor.getFullYear();
            const month = cursor.getMonth();
            monthLabel.textContent = cursor.toLocaleDateString("en-US", { month: "long", year: "numeric" });
            const first = new Date(year, month, 1);
            const cells = [];
            for (let index = first.getDay() - 1; index >= 0; index -= 1) cells.push(new Date(year, month - 1, new Date(year, month, 0).getDate() - index));
            for (let day = 1; day <= new Date(year, month + 1, 0).getDate(); day += 1) cells.push(new Date(year, month, day));
            while (cells.length < 42) cells.push(new Date(year, month + 1, cells.length - first.getDay() - new Date(year, month + 1, 0).getDate() + 1));
            const selected = selectedDate();
            days.innerHTML = cells.map((date) => `<button type="button" class="date-picker-day${date.getMonth() !== month ? " is-outside" : ""}${selected && formatIso(date) === dobInput.value ? " is-selected" : ""}" data-dob-date="${formatIso(date)}">${date.getDate()}</button>`).join("");
        };
        trigger.addEventListener("click", () => {
            document.querySelectorAll("#summerSchoolModal .date-picker-popup").forEach((other) => { if (other !== popup) other.classList.add("d-none"); });
            const date = selectedDate();
            if (date && !Number.isNaN(date.getTime())) cursor = new Date(date.getFullYear(), date.getMonth(), 1);
            popup.classList.toggle("d-none");
            if (!popup.classList.contains("d-none")) renderCalendar();
        });
        display.addEventListener("click", (event) => event.stopPropagation());
        display.addEventListener("focus", () => {
            const date = selectedDate();
            if (date && !Number.isNaN(date.getTime())) display.value = `${String(date.getDate()).padStart(2, "0")}-${String(date.getMonth() + 1).padStart(2, "0")}-${date.getFullYear()}`;
        });
        display.addEventListener("input", () => {
            const value = display.value.trim();
            const match = value.match(/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/);
            if (!match) return;
            const date = new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
            if (date.getFullYear() !== Number(match[3]) || date.getMonth() !== Number(match[2]) - 1 || date.getDate() !== Number(match[1])) return;
            dobInput.value = formatIso(date);
            cursor = new Date(date.getFullYear(), date.getMonth(), 1);
            syncDate();
            dobInput.dispatchEvent(new Event("change", { bubbles: true }));
        });
        display.addEventListener("blur", syncDate);
        picker.querySelectorAll("[data-dob-nav]").forEach((button) => button.addEventListener("click", () => { cursor.setMonth(cursor.getMonth() + (button.dataset.dobNav === "next" ? 1 : -1)); renderCalendar(); }));
        days.addEventListener("click", (event) => { const button = event.target.closest("[data-dob-date]"); if (!button) return; dobInput.value = button.dataset.dobDate; syncDate(); dobInput.dispatchEvent(new Event("change", { bubbles: true })); popup.classList.add("d-none"); });
        syncDate();
    }
    ["home_phone", "mother_phone", "father_phone", "guardian_phone"].forEach((name) => {
        const input = panel.querySelector(`[name="${name}"]`);
        if (!input || input.dataset.phoneReady) return;
        input.dataset.phoneReady = "1";
        const parent = input.parentElement;
        const group = document.createElement("div");
        group.className = "phone-input-group";
        const visible = input.cloneNode(true);
        visible.removeAttribute("name");
        visible.type = "text";
        visible.id = `${name}_number`;
        const hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = name;
        hidden.value = input.value || "";
        group.append(visible, hidden);
        input.replaceWith(group);
        const intl = intlTelInput(visible, {
            initialCountry: "kh",
            nationalMode: true,
            separateDialCode: true,
            loadUtils: () => import("intl-tel-input/utils"),
        });
        const sync = () => {
            hidden.value = intl.getNumber() || visible.value.trim();
            input.closest(".premium-floating-field")?.classList.toggle("has-value", Boolean(visible.value.trim()));
        };
        visible.addEventListener("input", () => { visible.value = visible.value.replace(/\s+/g, ""); sync(); });
        visible.addEventListener("countrychange", sync);
        summerPhoneSyncs.push(sync);
    });
    const familyInput = panel.querySelector('[name="existing_family_number"]');
    if (familyInput) {
        const familyField = familyInput.closest(".premium-floating-field");
        const familySelect = document.createElement("select");
        familySelect.name = "existing_family_number";
        familySelect.id = "summerExistingFamily";
        familySelect.className = "form-select d-none";
        familySelect.innerHTML = '<option value=""></option>';
        familyInput.replaceWith(familySelect);
        const familyNumber = panel.querySelector('[name="family_number"]');
        if (familyNumber) familyNumber.readOnly = true;
        familySelect.insertAdjacentHTML("afterend", '<div class="location-combobox summer-bilingual-combobox summer-family-combobox"><button type="button" class="location-combobox-toggle"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search Family / Sibling"><div class="location-combobox-results"></div></div></div>');
        const combo = familySelect.nextElementSibling;
        const toggle = combo.querySelector(".location-combobox-toggle");
        const menu = combo.querySelector(".location-combobox-menu");
        const search = combo.querySelector(".location-combobox-search");
        const selected = combo.querySelector(".location-combobox-selected");
        const renderSelected = () => {
            selected.textContent = familySelect.selectedOptions?.[0]?.dataset?.display || "";
        };
        const renderOptions = () => {
            const term = search.value.toLowerCase().trim();
            combo.querySelector(".location-combobox-results").innerHTML = Array.from(familySelect.options).slice(1)
                .filter((option) => !term || option.dataset.display.toLowerCase().includes(term))
                .map((option) => `<button type="button" class="location-combobox-option" data-value="${summerEsc(option.value)}">${summerEsc(option.dataset.display)}</button>`)
                .join("") || '<div class="text-secondary px-2 py-2">No options found</div>';
        };
        toggle.addEventListener("click", () => {
            document.querySelectorAll("#summerSchoolModal .location-combobox-menu").forEach((other) => {
                if (other !== menu) other.classList.add("d-none");
            });
            menu.classList.toggle("d-none");
            if (!menu.classList.contains("d-none")) { search.value = ""; renderOptions(); search.focus(); }
        });
        search.addEventListener("input", renderOptions);
        combo.querySelector(".location-combobox-results").addEventListener("click", (event) => {
            const option = event.target.closest("[data-value]");
            if (!option) return;
            familySelect.value = option.dataset.value;
            familySelect.dispatchEvent(new Event("change", { bubbles: true }));
            menu.classList.add("d-none");
        });
        const syncFamilyNumber = () => {
            if (!familyNumber) return;
            familyNumber.value = familySelect.value || `F${(panel.querySelector('[name="student_id"]')?.value || "").trim()}`;
        };
        let familyDetails = {};
        const populateFamily = () => {
            const family = familyDetails[familySelect.value] || {};
            ["mother", "father", "guardian"].forEach((type) => {
                const member = family[type] || {};
                [
                    ["name_en", "full_name_en"], ["name_kh", "full_name_kh"],
                    ["occupation_en", "occupation_en"], ["occupation_kh", "occupation_kh"],
                    ["nationality_en", "nationality_en"], ["nationality_kh", "nationality_kh"],
                    ["phone", "phone"], ["workplace", "workplace"],
                ].forEach(([fieldName, memberName]) => {
                    const field = panel.querySelector(`[name="${type}_${fieldName}"]`);
                    if (field) field.value = member[memberName] || "";
                });
            });
        };
        familySelect.addEventListener("change", () => { renderSelected(); syncFamilyNumber(); populateFamily(); });
        panel.querySelector('[name="student_id"]')?.addEventListener("input", syncFamilyNumber);
        syncFamilyNumber();
        familySelect._summerRenderOptions = renderOptions;
        familySelect._summerRenderSelected = renderSelected;
        if (familyField) familyField.classList.add("summer-searchable-family-field");
        fetch("/student-enrollments/quick-options", { headers: { Accept: "application/json" } })
            .then((response) => response.json())
            .then((result) => {
                const studentNo = panel.querySelector('[name="student_no"]');
                if (studentNo) studentNo.value = result.nextStudentNo || "";
                familySelect.innerHTML = '<option value=""></option>' + (result.families || []).map((family) => {
                    const display = `${family.family_number}${family.full_name_en ? ` - ${family.full_name_en}` : ""}`;
                    return `<option value="${summerEsc(family.family_number)}" data-display="${summerEsc(display)}">${summerEsc(display)}</option>`;
                }).join("");
                familySelect._summerRenderSelected();
                syncFamilyNumber();
            })
            .catch(() => {});
        fetch("/summer-school/family-options", { headers: { Accept: "application/json" } })
            .then((response) => response.json())
            .then((result) => { familyDetails = result || {}; populateFamily(); })
            .catch(() => {});
    }
    ["birth_country_kh", "address_country_kh", "gender_kh", "nationality_kh"].forEach((name) => panel.querySelector(`[name="${name}"]`)?.closest(".premium-floating-field")?.remove());
    panel.insertAdjacentHTML("beforeend", '<input type="hidden" name="gender_kh"><input type="hidden" name="nationality_kh">');
    const informationCard = panel.querySelector(".summer-form-card");
    const photoField = panel.querySelector(".summer-photo-field");
    informationCard?.classList.add("summer-student-information-card");
    const subsectionCard = (title) => Array.from(panel.querySelectorAll(".summer-form-card"))
        .find((card) => card !== informationCard && card.querySelector(":scope > h4")?.textContent.trim() === title);
    ["Previous School and Assessment", "Family Information"].forEach((title) =>
        subsectionCard(title)?.classList.add("summer-dark-header-card"),
    );
    ["Place of Birth", "Home Address"].forEach((title) => {
        const subsection = subsectionCard(title);
        if (!informationCard || !subsection) return;
        subsection.classList.add("summer-student-subsection");
        informationCard.appendChild(subsection);
    });
    const firstInformationRow = informationCard?.querySelector(".row");
    if (informationCard && photoField && firstInformationRow)
        informationCard.insertBefore(photoField, firstInformationRow);
    const informationRows = informationCard ? Array.from(informationCard.querySelectorAll(":scope > .row")) : [];
    const namesRow = informationRows[1];
    const detailsRow = informationRows[2];
    const contactRow = informationRows[3];
    const nationalityField = panel.querySelector('[name="nationality_country_id"]')?.closest(".premium-floating-field");
    const phoneField = panel.querySelector('[name="home_phone"]')?.closest(".premium-floating-field");
    const emailField = panel.querySelector('[name="email"]')?.closest(".premium-floating-field");
    if (namesRow && nationalityField) namesRow.appendChild(nationalityField);
    if (detailsRow && phoneField) detailsRow.appendChild(phoneField);
    if (detailsRow && emailField) detailsRow.appendChild(emailField);
    const filters = summerField("summerWesternFilters");
    filters?.parentElement?.insertBefore(panel, filters.nextSibling);
    const syncKhmer = (source, target, prefix = "") => {
        const sourceField = panel.querySelector(`[name="${source}"]`);
        const targetField = panel.querySelector(`[name="${target}"]`);
        sourceField?.addEventListener("input", () => {
            if (targetField && !targetField.dataset.edited) targetField.value = sourceField.value ? `${prefix}${sourceField.value}` : "";
        });
    };
    syncKhmer("address_house_no_en", "address_house_no_kh", "ផ្ទះលេខ ");
    syncKhmer("address_street_en", "address_street_kh", "ផ្លូវ ");
    ["summerBirthCountry", "summerAddressCountry"].forEach((id) => summerField(id)?.addEventListener("change", (event) => {
        const kh = event.target.selectedOptions?.[0]?.dataset?.kh || "";
        const target = panel.querySelector(`[name="${id === "summerBirthCountry" ? "birth_country_kh" : "address_country_kh"}"]`);
        if (target) target.value = kh;
    }));
    const photoInput = panel.querySelector("#summerExternalPhoto");
    const photoDropzone = panel.querySelector("#summerExternalPhotoDropzone");
    const photoPreview = panel.querySelector("#summerExternalPhotoPreview");
    const photoPreviewWrap = panel.querySelector("#summerExternalPhotoPreviewWrap");
    const showPhoto = (file) => {
        if (!file || !file.type?.startsWith("image/")) return;
        photoPreview.src = URL.createObjectURL(file);
        photoPreviewWrap.classList.remove("d-none");
    };
    photoDropzone?.addEventListener("click", () => photoInput?.click());
    photoInput?.addEventListener("change", () => showPhoto(photoInput.files?.[0]));
    photoDropzone?.addEventListener("dragover", (event) => { event.preventDefault(); photoDropzone.classList.add("is-dragging"); });
    photoDropzone?.addEventListener("dragleave", () => photoDropzone.classList.remove("is-dragging"));
    photoDropzone?.addEventListener("drop", (event) => {
        event.preventDefault();
        photoDropzone.classList.remove("is-dragging");
        const file = event.dataTransfer.files?.[0];
        if (!file || !photoInput) return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        photoInput.files = transfer.files;
        showPhoto(file);
    });
    return;
    if (summerField("summerParityFields")) return;
    const anchor = document
        .querySelector('[name="continue_at_western"]')
        ?.closest(".summer-external-field");
    if (!anchor) return;
    const wrapper = document.createElement("div");
    wrapper.id = "summerParityFields";
    wrapper.className = "col-12 summer-external-field d-none";
    wrapper.innerHTML = `<div class="enrollment-profile-card"><h4 class="mb-3">Place of Birth</h4><div class="row g-3"><div class="col-md-4 premium-floating-field"><label class="form-label">Birth Country</label><select name="birth_country_id" id="summerBirthCountry" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">Birth Province / City</label><select name="birth_province_id" id="summerBirthProvince" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">Birth District / Khan</label><select name="birth_district_id" id="summerBirthDistrict" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">Birth Commune</label><select name="birth_commune_id" id="summerBirthCommune" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">Birth Village</label><select name="birth_village_id" id="summerBirthVillage" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">Academic Track</label><select name="academic_track_id" id="summerAcademicTrack" class="form-select"></select></div></div><h4 class="mb-3">Home Address</h4><div class="row g-3"><div class="col-md-4 premium-floating-field"><label class="form-label">Address Country</label><select name="address_country_id" id="summerAddressCountry" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">Address Province / City</label><select name="address_province_id" id="summerAddressProvince" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">Address District / Khan</label><select name="address_district_id" id="summerAddressDistrict" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">Address Commune</label><select name="address_commune_id" id="summerAddressCommune" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">Address Village</label><select name="address_village_id" id="summerAddressVillage" class="form-select"></select></div><div class="col-md-4 premium-floating-field"><label class="form-label">House No. / Street</label><input name="address_house_no_en" class="form-control"><input name="address_street_en" class="form-control mt-2"></div><div class="col-md-6 premium-floating-field"><label class="form-label">Current Address (English)</label><textarea name="current_address_en" class="form-control" rows="1"></textarea></div><div class="col-md-6 premium-floating-field"><label class="form-label">Current Address (Khmer)</label><textarea name="current_address_kh" class="form-control school-profile-khmer" rows="1"></textarea></div></div></div>`;
    const addressHouseEnglish = wrapper.querySelector('[name="address_house_no_en"]');
    const addressHouseColumn = addressHouseEnglish?.closest(".premium-floating-field");
    if (addressHouseColumn) {
        addressHouseColumn.className = "col-md-3 premium-floating-field";
        addressHouseColumn.innerHTML = `<label class="form-label">House No. (English)</label><input name="address_house_no_en" class="form-control">`;
        addressHouseColumn.insertAdjacentHTML("afterend", `<div class="col-md-3 premium-floating-field"><label class="form-label">House No. (Khmer)</label><input name="address_house_no_kh" class="form-control school-profile-khmer"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Street (English)</label><input name="address_street_en" class="form-control"></div><div class="col-md-3 premium-floating-field"><label class="form-label">Street (Khmer)</label><input name="address_street_kh" class="form-control school-profile-khmer"></div>`);
        const syncAddress = (source, target, prefix) => {
            const sourceField = wrapper.querySelector(`[name="${source}"]`);
            const targetField = wrapper.querySelector(`[name="${target}"]`);
            sourceField?.addEventListener("input", () => {
                if (targetField && !targetField.dataset.edited)
                    targetField.value = sourceField.value ? `${prefix}${sourceField.value}` : "";
            });
            targetField?.addEventListener("input", () => { targetField.dataset.edited = "1"; });
        };
        syncAddress("address_house_no_en", "address_house_no_kh", "ផ្ទះលេខ ");
        syncAddress("address_street_en", "address_street_kh", "ផ្លូវ ");
    }
    wrapper.querySelector(".enrollment-profile-card > .row")?.classList.add("summer-five-column-row");
    anchor.parentElement.insertBefore(wrapper, anchor);
    const parentSection = document
        .querySelector('[name="father_workplace"]')
        ?.closest(".summer-external-field")?.parentElement;
    if (parentSection && !summerField("summerGuardianName")) {
        const guardian = document.createElement("div");
        guardian.className = "row g-3 mt-2 summer-external-field d-none";
        guardian.innerHTML = `<div class="col-12"><h5 class="mb-0">Guardian</h5></div><div class="col-md-4"><label class="form-label">Guardian Name (English)</label><input name="guardian_name_en" id="summerGuardianName" class="form-control"></div><div class="col-md-4"><label class="form-label">Guardian Name (Khmer)</label><input name="guardian_name_kh" class="form-control school-profile-khmer"></div><div class="col-md-4"><label class="form-label">Phone</label><input name="guardian_phone" class="form-control"></div><div class="col-md-6"><label class="form-label">Occupation</label><select name="guardian_occupation_id" id="summerGuardianOccupation" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Nationality</label><select name="guardian_nationality_country_id" id="summerGuardianNationality" class="form-select"></select></div><div class="col-md-12"><label class="form-label">Workplace</label><input name="guardian_workplace" class="form-control"></div>`;
        parentSection.appendChild(guardian);
    }
};
// Build the final external form immediately; option data can load into it later.
summerAddEnrollmentParityFields();

const summerRowsMatchingFilters = (includeGrade = true) => {
    const year = summerField("summerStudentYearFilter")?.value || "";
    const campus = summerField("summerStudentCampusFilter")?.value || "";
    const gradeClass = includeGrade
        ? summerField("summerStudentGradeClassFilter")?.value || ""
        : "";
    return summerStudentEnrollments.filter(
        (row) =>
            (!year || String(row.academic_year_id) === year) &&
            (!campus || String(row.campus_id) === campus) &&
            (!gradeClass || `${row.grade_id}:${row.class_id}` === gradeClass),
    );
};
const summerPopulateWesternYears = () =>
    summerSetFilterOptions(
        "summerStudentYearFilter",
        summerStudentEnrollments
            .map((row) => [
                row.academic_year_id,
                row.academic_year?.academic_year,
            ])
            .filter(([id, label]) => id && label),
    );
const summerSetFilterOptions = (id, entries) => {
    const select = summerField(id);
    if (!select) return;
    const current = select.value;
    select.innerHTML =
        '<option value=""></option>' +
        Array.from(new Map(entries).entries())
            .sort((a, b) => String(a[1]).localeCompare(String(b[1])))
            .map(
                ([value, label]) =>
                    `<option value="${summerEsc(value)}">${summerEsc(label)}</option>`,
            )
            .join("");
    if (Array.from(select.options).some((option) => option.value === current))
        select.value = current;
};
const summerRenderInternalStudents = () => {
    const matching = summerRowsMatchingFilters();
    const unique = new Map(
        matching
            .map((row) => [row.student?.id, row.student])
            .filter(([id, student]) => id && student),
    );
    const select = summerField("summerInternalStudent");
    const current = select.value;
    select.innerHTML =
        '<option value=""></option>' +
        Array.from(unique.values())
            .sort((a, b) =>
                String(a.student_id || "").localeCompare(
                    String(b.student_id || ""),
                ),
            )
            .map(
                (student) =>
                    `<option value="${student.id}">${summerEsc(`${student.student_id || "-"} - ${student.full_name_en || student.full_name_kh || "-"}`)}</option>`,
            )
            .join("");
    if (Array.from(select.options).some((option) => option.value === current))
        select.value = current;
    summerSearchableFilters.summerInternalStudent?.sync();
    summerSearchableFilters.summerInternalStudent?.render();
};
const summerSetupStudentFilters = () => {
    summerStudentEnrollments = summerOptions.studentEnrollments || [];
    summerSetupSearchableFilter("summerStudentYearFilter", "Academic Year");
    summerSetupSearchableFilter("summerStudentCampusFilter", "Campus");
    summerSetupSearchableFilter(
        "summerStudentGradeClassFilter",
        "Grade / Class",
    );
    summerSetupSearchableFilter("summerInternalStudent", "Student Name");
    const refresh = () => {
        const year = summerField("summerStudentYearFilter")?.value || "";
        const yearRows = summerStudentEnrollments.filter(
            (row) => !year || String(row.academic_year_id) === year,
        );
        summerSetFilterOptions(
            "summerStudentCampusFilter",
            yearRows
                .map((row) => [row.campus_id, row.campus?.campus_name_en])
                .filter(([id, label]) => id && label),
        );
        const campus = summerField("summerStudentCampusFilter")?.value || "";
        const gradeRows = yearRows.filter(
            (row) => !campus || String(row.campus_id) === campus,
        );
        summerSetFilterOptions(
            "summerStudentGradeClassFilter",
            gradeRows
                .map((row) => [
                    `${row.grade_id}:${row.class_id}`,
                    `${row.grade?.grade || ""}${row.school_class?.class_name || ""}`,
                ])
                .filter(([id, label]) => id && label),
        );
        summerRenderInternalStudents();
        summerRefreshSearchableFilters();
    };
    summerField("summerStudentYearFilter")?.addEventListener("change", refresh);
    summerField("summerStudentCampusFilter")?.addEventListener(
        "change",
        refresh,
    );
    summerField("summerStudentGradeClassFilter")?.addEventListener(
        "change",
        summerRenderInternalStudents,
    );
    refresh();
};
const summerLoadOptions = async () => {
    summerOptions = await (await fetch("/summer-school/options")).json();
    summerFill(
        "summerAcademicYear",
        summerOptions.academicYears || [],
        "academic_year",
        "Select Summer Period",
    );
    summerFill(
        "summerCampus",
        summerOptions.campuses || [],
        "campus_name_en",
        "Select Campus",
    );
    summerFill(
        "summerGrade",
        summerOptions.grades || [],
        "grade",
        "Select Grade",
    );
    summerFill(
        "summerClass",
        summerOptions.classes || [],
        "class_name",
        "Select Class",
    );
    summerFill(
        "summerSession",
        summerOptions.sessions || [],
        "session_short_name",
        "Select Group",
    );
    summerField("summerNationality").innerHTML =
        '<option value="">Select Nationality</option>' +
        (summerOptions.countries || [])
            .map(
                (country) =>
                    `<option value="${country.id}">${summerEsc(country.nationality_name_en || country.nationality_name_kh || "-")}</option>`,
            )
            .join("");
    summerFill("summerDocumentType", summerOptions.documentTypes || [], "name_en", "");
    summerFill(
        "summerConvertYear",
        summerOptions.regularAcademicYears || [],
        "academic_year",
        "Select Academic Year",
    );
    summerFill(
        "summerConvertCampus",
        summerOptions.campuses || [],
        "campus_name_en",
        "Select Campus",
    );
    summerFill(
        "summerConvertGrade",
        summerOptions.grades || [],
        "grade",
        "Select Grade",
    );
    summerFill(
        "summerConvertClass",
        summerOptions.classes || [],
        "class_name",
        "Select Class",
    );
    summerFill(
        "summerConvertSession",
        summerOptions.sessions || [],
        "session_short_name",
        "Select Group",
    );
    summerField("summerFilterYear").innerHTML =
        '<option value="">All Summer Periods</option>' +
        (summerOptions.academicYears || [])
            .map(
                (year) =>
                    `<option value="${year.id}">${summerEsc(year.academic_year)}</option>`,
            )
            .join("");
    summerSetupStudentFilters();
    summerLoadRows();
    fetch("/summer-school/student-options", { headers: { Accept: "application/json" } })
        .then((response) => response.json())
        .then((students) => {
            summerOptions.studentEnrollments = students;
            summerSetupStudentFilters();
        })
        .catch(() => {});
};
const summerLoadRows = async () => {
    const params = new URLSearchParams({ perPage: "100" });
    if (summerField("summerFilterYear")?.value)
        params.set("academic_year_id", summerField("summerFilterYear").value);
    [
        ["summerFilterCampus", "campus_id"], ["summerFilterGrade", "grade_id"],
        ["summerFilterGroup", "session_id"], ["summerFilterStudent", "student_id"],
        ["summerFilterStatus", "enrollment_status"], ["summerFilterSearch", "search"],
    ].forEach(([id, key]) => { if (summerField(id)?.value) params.set(key, summerField(id).value); });
    const result = await (await fetch(`/summer-school/fetch?${params}`)).json();
    const allRows = result.data || [];
    const fillFilter = (id, values, label) => {
        const select = summerField(id); if (!select || select.dataset.loaded) return;
        select.innerHTML = `<option value="">${select.options[0]?.textContent || "All"}</option>` + [...new Map(values.map((row) => [row.id, row[label]])).entries()].filter(([, text]) => text).map(([id, text]) => `<option value="${summerEsc(id)}">${summerEsc(text)}</option>`).join("");
        select.dataset.loaded = "1";
    };
    fillFilter("summerFilterCampus", allRows.map((row) => ({ id: row.campus_id, name: row.campus?.campus_name_en })), "name");
    fillFilter("summerFilterGrade", allRows.map((row) => ({ id: row.grade_id, name: row.grade?.grade })), "name");
    fillFilter("summerFilterGroup", allRows.map((row) => ({ id: row.session_id, name: row.session?.session_short_name })), "name");
    fillFilter("summerFilterStudent", allRows.map((row) => ({ id: row.student_id, name: `${row.student?.student_id || "-"} - ${row.student?.full_name_en || row.student?.full_name_kh || "-"}` })), "name");
    summerField("summerSchoolRows").innerHTML = (result.data || []).length
        ? result.data
              .map((item) => {
                  const canConvert =
                      item.enrollment_origin === "external" &&
                      item.continue_at_western === "yes";
                  const photo = item.student?.photo_path ? `<img class="summer-student-photo" src="/storage/${summerEsc(item.student.photo_path)}" alt="Student photo">` : `<span class="summer-student-photo-placeholder"><i class="ti ti-user"></i></span>`;
                  return `<tr><td>${photo}</td><td>${summerEsc(item.student?.student_id || "-")}</td><td><strong>${summerEsc(item.student?.full_name_en || item.student?.full_name_kh || "-")}</strong></td><td><span class="badge ${item.enrollment_origin === "external" ? "bg-warning-lt" : "bg-blue-lt"}">${item.enrollment_origin === "external" ? "New" : "Old"}</span></td><td>${summerEsc(item.academic_year?.academic_year || "-")}</td><td>${summerEsc(item.campus?.campus_name_en || "-")}</td><td>${summerEsc(item.grade?.grade || "-")}</td><td>${summerEsc(item.academic_track?.name_en || "-")}</td><td>${summerEsc(item.session?.session_short_name || "-")}</td><td><span class="badge ${item.enrollment_status === "active" ? "bg-success-lt" : item.enrollment_status === "withdrawn" ? "bg-danger-lt" : "bg-warning-lt"}">${summerEsc(item.enrollment_status || "-")}</span></td><td>${canConvert ? `<button type="button" class="btn btn-sm btn-outline-primary" data-summer-convert="${item.id}"><i class="ti ti-arrow-right me-1"></i>Continue</button>` : "-"}</td></tr>`;
              })
              .join("")
        : '<tr><td colspan="11" class="text-center text-secondary">No Summer School students found.</td></tr>';
};
const summerToggleOrigin = () => {
    const external = summerField("summerOrigin").value === "external";
    document
        .querySelectorAll("#summerSchoolForm .summer-external-field .form-label")
        .forEach((label) =>
            label.closest('[class*="col-"]')?.classList.add(
                "premium-floating-field",
            ),
        );
    document.querySelectorAll("[data-summer-origin]").forEach((button) =>
        button.classList.toggle(
            "is-active",
            button.dataset.summerOrigin === (external ? "external" : "internal"),
        ),
    );
    summerField("summerWesternFilters").classList.toggle("d-none", external);
    summerField("summerInternalField").classList.toggle("d-none", external);
    document
        .querySelectorAll(".summer-external-field")
        .forEach((field) => field.classList.toggle("d-none", !external));
    summerField("summerInternalStudent").required = !external;
};
document.querySelectorAll("[data-summer-origin]").forEach((button) => {
    button.addEventListener("click", () => {
        const origin = summerField("summerOrigin");
        if (!origin) return;
        origin.value = button.dataset.summerOrigin || "internal";
        origin.dispatchEvent(new Event("change", { bubbles: true }));
        summerToggleOrigin();
        summerSearchableFilters.summerOrigin?.sync();
    });
});
// Student Type is static, so initialize its searchable control immediately;
// it must not depend on the slower Summer options request.
summerSetupSearchableFilter("summerOrigin", "Student Type", false);
summerField("summerOrigin")?.addEventListener("change", summerToggleOrigin);
summerField("summerFilterYear")?.addEventListener("change", summerLoadRows);
["summerFilterCampus", "summerFilterGrade", "summerFilterGroup", "summerFilterStudent", "summerFilterStatus"].forEach((id) => summerField(id)?.addEventListener("change", summerLoadRows));
summerField("summerFilterSearch")?.addEventListener("input", (() => { let timer; return () => { clearTimeout(timer); timer = setTimeout(summerLoadRows, 250); }; })());
summerField("summerSchoolNew")?.addEventListener("click", () => {
    summerField("summerSchoolForm").reset();
    const enrolledOn = summerField("summerSchoolForm").querySelector('[name="enrolled_on"]');
    if (enrolledOn) enrolledOn.value = new Date().toISOString().slice(0, 10);
    const status = summerField("summerSchoolForm").querySelector('[name="enrollment_status"]');
    if (status) status.value = "active";
    summerToggleOrigin();
    summerSearchableFilters.summerOrigin?.sync();
    summerClearRegistrationPlaceholders();
    summerModal?.show();
});
[
    "summerStudentYearFilter",
    "summerStudentCampusFilter",
    "summerStudentGradeClassFilter",
].forEach((id) =>
    summerField(id)?.addEventListener("change", summerRenderInternalStudents),
);
summerField("summerStudentSearch")?.addEventListener(
    "input",
    summerRenderInternalStudents,
);
summerField("summerSchoolRows")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-summer-convert]");
    if (!button) return;
    summerField("summerConvertEnrollmentId").value =
        button.dataset.summerConvert;
    summerField("summerConvertError").classList.add("d-none");
    summerConvertModal?.show();
});
summerField("summerSchoolForm")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const emailInput = summerField("summerSchoolForm")?.querySelector('[name="email"]');
    if (emailInput && emailInput.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
        emailInput.setCustomValidity("Please enter a valid email address, for example name@example.com.");
        showError("Invalid Email", "Please enter a valid email address, for example name@example.com.");
        emailInput.focus();
        return;
    }
    summerPhoneSyncs.forEach((sync) => sync());
    const error = summerField("summerSchoolError");
    error.classList.add("d-none");
    const response = await fetch("/summer-school/save", {
        method: "POST",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN":
                document.querySelector('meta[name="csrf-token"]')?.content ||
                "",
        },
        body: new FormData(event.target),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        error.textContent =
            result.message ||
            Object.values(result.errors || {})[0]?.[0] ||
            "Unable to register Summer School student.";
        error.classList.remove("d-none");
        return;
    }
    summerModal?.hide();
    summerLoadRows();
});
summerField("summerConvertForm")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const error = summerField("summerConvertError");
    error.classList.add("d-none");
    const id = summerField("summerConvertEnrollmentId").value;
    const response = await fetch(`/summer-school/${id}/convert-to-western`, {
        method: "POST",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN":
                document.querySelector('meta[name="csrf-token"]')?.content ||
                "",
        },
        body: new FormData(event.target),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        error.textContent =
            result.message ||
            Object.values(result.errors || {})[0]?.[0] ||
            "Unable to create Western enrollment.";
        error.classList.remove("d-none");
        return;
    }
    summerConvertModal?.hide();
    summerLoadRows();
});
const summerPopulateParityOptions = () => {
    summerAddEnrollmentParityFields();
    const optionList = (items, en, kh, parentKey = "") =>
        '<option value=""></option>' +
        (items || [])
            .map(
                (item) =>
                    `<option value="${item.id}" data-parent-id="${summerEsc(parentKey ? item[parentKey] || "" : "")}" data-en="${summerEsc(item[en] || item.country_name_en || "")}" data-kh="${summerEsc(item[kh] || item.country_name_kh || "")}" data-flag="${summerEsc(item.flag_path || "")}">${summerEsc(item[en] || item.country_name_en || item[kh] || item.country_name_kh || "-")}</option>`,
            )
            .join("");
    const setupBilingualSelect = (id, label, withFlag = false) => {
        const select = summerField(id);
        if (!select || select.dataset.bilingualReady) return;
        select.dataset.bilingualReady = "1";
        select.classList.add("d-none");
        select.insertAdjacentHTML("afterend", `<div class="location-combobox summer-bilingual-combobox"><button type="button" class="location-combobox-toggle"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search ${label}"><div class="location-combobox-results"></div></div></div>`);
        const combo = select.nextElementSibling;
        const toggle = combo.querySelector(".location-combobox-toggle");
        const menu = combo.querySelector(".location-combobox-menu");
        const search = combo.querySelector(".location-combobox-search");
        const selected = combo.querySelector(".location-combobox-selected");
        const floatingField = select.closest(".premium-floating-field");
        const genderIcon = (value) => value === "Male"
            ? '<i class="ti ti-gender-male summer-bilingual-icon" aria-hidden="true"></i>'
            : value === "Female"
                ? '<i class="ti ti-gender-female summer-bilingual-icon" aria-hidden="true"></i>'
                : '<i class="ti ti-user summer-bilingual-icon" aria-hidden="true"></i>';
        if (id === "summerNationality") {
            const labelElement = floatingField?.querySelector(".form-label");
            if (labelElement) labelElement.textContent = "Nationality (Khmer / English)";
        }
        const syncFloatingLabel = () => floatingField?.classList.toggle("has-value", Boolean(select.value));
        const renderSelected = () => {
            const option = select.selectedOptions?.[0];
            if (!option?.value) { selected.textContent = ""; syncFloatingLabel(); return; }
            selected.innerHTML = `${id === "summerGender" ? genderIcon(option.value) : ""}${withFlag && option.dataset.flag ? `<img src="${summerEsc(option.dataset.flag)}" class="location-flag me-2" alt="">` : ""}<span class="location-combobox-selected-text"><span class="location-combobox-khmer">${summerEsc(option.dataset.kh || "")}</span><span class="location-combobox-english d-block">${summerEsc(option.dataset.en || option.textContent)}</span></span>`;
            syncFloatingLabel();
        };
        const renderOptions = () => {
            const term = search.value.toLowerCase().trim();
            combo.querySelector(".location-combobox-results").innerHTML = Array.from(select.options).slice(1).filter((option) => !option.hidden && (!term || `${option.textContent} ${option.dataset.en || ""} ${option.dataset.kh || ""}`.toLowerCase().includes(term))).map((option) => `<button type="button" class="location-combobox-option" data-value="${summerEsc(option.value)}">${id === "summerGender" ? genderIcon(option.value) : ""}${withFlag && option.dataset.flag ? `<img src="${summerEsc(option.dataset.flag)}" class="location-flag me-2" alt="">` : ""}<span><span class="location-combobox-khmer">${summerEsc(option.dataset.kh || "")}</span><span class="location-combobox-english d-block">${summerEsc(option.dataset.en || option.textContent)}</span></span></button>`).join("") || '<div class="text-secondary px-2 py-2">No options found</div>';
        };
        toggle.addEventListener("click", () => {
            document.querySelectorAll("#summerSchoolModal .location-combobox-menu").forEach((other) => {
                if (other !== menu) other.classList.add("d-none");
            });
            menu.classList.toggle("d-none");
            if (!menu.classList.contains("d-none")) { search.value = ""; renderOptions(); search.focus(); }
        });
        search.addEventListener("input", renderOptions);
        combo.querySelector(".location-combobox-results").addEventListener("click", (event) => { const option = event.target.closest("[data-value]"); if (!option) return; select.value = option.dataset.value; select.dispatchEvent(new Event("change", { bubbles: true })); menu.classList.add("d-none"); });
        select.addEventListener("change", () => {
            renderSelected();
            const khTarget = id === "summerGender" ? "gender_kh" : id === "summerNationality" ? "nationality_kh" : null;
            if (khTarget) {
                const hidden = panel.querySelector(`[name="${khTarget}"]`);
                if (hidden) hidden.value = select.selectedOptions?.[0]?.dataset?.kh || "";
            }
        });
        renderSelected();
    };
    const panel = summerField("summerExternalComplete");
    const gender = panel?.querySelector('[name="gender"]');
    if (gender) {
        gender.id = "summerGender";
        gender.innerHTML = '<option value="" data-en="" data-kh=""></option><option value="Male" data-en="Male" data-kh="ប្រុស">Male</option><option value="Female" data-en="Female" data-kh="ស្រី">Female</option><option value="Other" data-en="Other" data-kh="ផ្សេងទៀត">Other</option>';
    }
    const nationality = summerField("summerNationality");
    if (nationality)
        nationality.innerHTML = optionList(summerOptions.countries, "nationality_name_en", "nationality_name_kh");
    [
        "summerBirthCountry",
        "summerAddressCountry",
        "summerGuardianNationality",
    ].forEach((id) => {
        const field = summerField(id);
        if (field)
            field.innerHTML = optionList(
                summerOptions.countries,
                "nationality_name_en",
                "nationality_name_kh",
            );
    });
    ["summerBirthCountry", "summerAddressCountry"].forEach((id) => {
        const field = summerField(id);
        const cambodia = Array.from(field?.options || []).find((option) =>
            /cambodia|កម្ពុជា/i.test(option.textContent || ""),
        );
        if (field && cambodia) field.value = cambodia.value;
    });
    ["summerBirthProvince", "summerAddressProvince"].forEach((id) => {
        const field = summerField(id);
        if (field)
            field.innerHTML = optionList(
                summerOptions.provinces,
                "province_name_en",
                "province_name_kh",
                "country_id",
            );
    });
    ["summerBirthDistrict", "summerAddressDistrict"].forEach((id) => {
        const field = summerField(id);
        if (field)
            field.innerHTML = optionList(
                summerOptions.districts,
                "district_name_en",
                "district_name_kh",
                "province_id",
            );
    });
    ["summerBirthCommune", "summerAddressCommune"].forEach((id) => {
        const field = summerField(id);
        if (field)
            field.innerHTML = optionList(
                summerOptions.communes,
                "commune_name_en",
                "commune_name_kh",
                "district_id",
            );
    });
    ["summerBirthVillage", "summerAddressVillage"].forEach((id) => {
        const field = summerField(id);
        if (field)
            field.innerHTML = optionList(
                summerOptions.villages,
                "village_name_en",
                "village_name_kh",
                "commune_id",
            );
    });
    const track = summerField("summerAcademicTrack");
    if (track)
        track.innerHTML =
            '<option value=""></option>' +
            (summerOptions.academicTracks || [])
                .map(
                    (item) =>
                        `<option value="${item.id}">${summerEsc(item.name_en || item.name_kh || item.code || "-")}</option>`,
                )
                .join("");
    const guardianOccupation = summerField("summerGuardianOccupation");
    if (guardianOccupation)
        guardianOccupation.innerHTML = optionList(
            summerOptions.occupations,
            "occupation_name_en",
            "occupation_name_kh",
        );
    [
        ["summerGender", "Gender"],
        ["summerNationality", "Nationality", true],
        ["summerBirthCountry", "Country", true],
        ["summerBirthProvince", "Province / City"],
        ["summerBirthDistrict", "District / Khan"],
        ["summerBirthCommune", "Commune"],
        ["summerBirthVillage", "Village"],
        ["summerAddressCountry", "Country", true],
        ["summerAddressProvince", "Province / City"],
        ["summerAddressDistrict", "District / Khan"],
        ["summerAddressCommune", "Commune"],
        ["summerAddressVillage", "Village"],
    ].forEach(([id, label, flag]) => setupBilingualSelect(id, label, flag));
    const applyLocationFilter = (childId, parentId) => {
        const child = summerField(childId);
        const parent = summerField(parentId);
        if (!child || !parent) return;
        const parentValue = parent.value;
        let currentValueVisible = false;
        Array.from(child.options).forEach((option, index) => {
            if (index === 0 || !option.value) {
                option.hidden = false;
                return;
            }
            const visible = Boolean(parentValue) && option.dataset.parentId === parentValue;
            option.hidden = !visible;
            if (option.value === child.value) currentValueVisible = visible;
        });
        if (!currentValueVisible) child.value = "";
        child.dispatchEvent(new Event("change", { bubbles: true }));
        child._summerRenderOptions?.();
        child._summerRenderSelected?.();
    };
    [
        ["summerBirthProvince", "summerBirthCountry"],
        ["summerBirthDistrict", "summerBirthProvince"],
        ["summerBirthCommune", "summerBirthDistrict"],
        ["summerBirthVillage", "summerBirthCommune"],
        ["summerAddressProvince", "summerAddressCountry"],
        ["summerAddressDistrict", "summerAddressProvince"],
        ["summerAddressCommune", "summerAddressDistrict"],
        ["summerAddressVillage", "summerAddressCommune"],
    ].forEach(([childId, parentId]) => {
        const parent = summerField(parentId);
        parent?.addEventListener("change", () => applyLocationFilter(childId, parentId));
        applyLocationFilter(childId, parentId);
    });
    const updateSummerCurrentAddress = () => {
        const valueFor = (name, language) => {
            const field = panel.querySelector(`[name="${name}"]`);
            if (!field) return "";
            if (field.tagName === "SELECT") return field.selectedOptions?.[0]?.dataset?.[language] || "";
            return field.value?.trim() || "";
        };
        const english = [
            "address_house_no_en", "address_street_en", "address_village_id",
            "address_commune_id", "address_district_id", "address_province_id", "address_country_id",
        ].map((name) => valueFor(name, "en")).filter(Boolean);
        const khmer = [
            "address_house_no_kh", "address_street_kh", "address_village_id",
            "address_commune_id", "address_district_id", "address_province_id", "address_country_id",
        ].map((name) => valueFor(name, "kh")).filter(Boolean);
        const englishAddress = panel.querySelector('[name="current_address_en"]');
        const khmerAddress = panel.querySelector('[name="current_address_kh"]');
        if (englishAddress) englishAddress.value = english.join(", ");
        if (khmerAddress) khmerAddress.value = khmer.join(", ");
    };
    [
        "address_country_id", "address_province_id", "address_district_id",
        "address_commune_id", "address_village_id", "address_house_no_en", "address_street_en",
    ].forEach((name) => {
        const field = panel.querySelector(`[name="${name}"]`);
        field?.addEventListener(field.tagName === "SELECT" ? "change" : "input", updateSummerCurrentAddress);
    });
    updateSummerCurrentAddress();
    summerToggleOrigin();
};
summerLoadOptions().then(() => {
    summerPopulateParityOptions();
    summerSetupSearchableFilter("summerOrigin", "Student Type", false);
    summerSearchableFilters.summerOrigin?.sync();
    summerClearRegistrationPlaceholders();
    const session = summerField("summerConvertSession");
    if (session && !summerField("summerConvertTrack")) {
        const column = document.createElement("div");
        column.className = "col-md-6";
        column.innerHTML =
            '<label class="form-label">Academic Track</label><select name="academic_track_id" id="summerConvertTrack" class="form-select"></select>';
        session.closest(".row")?.appendChild(column);
        summerField("summerConvertTrack").innerHTML =
            '<option value="">Select Academic Track</option>' +
            (summerOptions.academicTracks || [])
                .map(
                    (item) =>
                        `<option value="${item.id}">${summerEsc(item.name_en || item.name_kh || item.code || "-")}</option>`,
                )
                .join("");
    }
    fetch("/summer-school/location-options")
        .then((response) => response.json())
        .then((locations) => {
            Object.assign(summerOptions, locations);
            summerPopulateParityOptions();
        });
});
summerPeriodOptionsPromise.catch(() => {});
