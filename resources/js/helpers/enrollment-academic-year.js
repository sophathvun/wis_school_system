// Keep the saved year visible when editing a historical enrollment. Create
// options remain limited to operational years and are rebuilt on each opening.
export function selectEnrollmentAcademicYear(select, enrollment) {
    if (!select || !enrollment?.academic_year_id) return;
    const value = String(enrollment.academic_year_id);
    if (![...select.options].some(option => option.value === value)) {
        select.add(new Option(enrollment.academic_year?.academic_year || `Academic Year ${value}`, value));
    }
    select.value = value;
}
