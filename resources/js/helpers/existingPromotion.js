export function existingPromotedPlacement(student, yearId) {
    return student?.existing_promotions?.find((promotion) => String(promotion.target.academic_year_id) === String(yearId)) || null;
}

export function minimumRequestedGradeOrder(student, yearId) {
    return existingPromotedPlacement(student, yearId)?.grade_order ?? student?.grade_order ?? 0;
}
