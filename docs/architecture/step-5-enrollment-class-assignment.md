# Step 5 — Enrollment and Class Assignment

Status: Implemented

## Scope

Step 5 formalizes the student's academic enrollment lifecycle while preserving
the existing Student Enrollment screen and its pagination/sidebar conventions.

The assignment path is:

```text
Student → Academic Year → Campus → Grade → Class → Session
```

Group is not assigned separately. The existing `group_id` column is retained
for backward compatibility, but new enrollment saves keep it null and use the
existing Session/Class structure as the source of assignment context.

## Lifecycle

Supported enrollment statuses are:

- Active
- Completed
- Withdrawn
- Transferred
- Graduated
- Cancelled

Each enrollment stores its enrollment date, optional end date, exit reason, and
notes. Every create or update writes an immutable snapshot to
`tb_student_enrollment_history` with the action type, assignment, status,
effective date, reason, notes, and user who made the change.

## Database

- Extended `tb_student_enrollment` with lifecycle fields and assignment indexes.
- Added `tb_student_enrollment_history` for promotion, transfer, withdrawal,
  graduation, and correction history.
- Added Eloquent history relationships.
- Added duplicate protection for one student per academic year.
- Added automatic `new` / `old` student classification. A student with an
  enrollment in another academic year is classified as an old student.
- Added an Enrollment History action and modal to the Student Enrollment list.
- Added history API endpoint: `GET /student-enrollments/{enrollment}/history`.
- Enrollment History displays the record `updated_at` as a localized date and
  time. The history `changed_by` value is the current user when authenticated.

## Platform audit standard

All future transactional modules will include Created By, Created At, Updated
By, and Updated At. The common audit-trail implementation will be applied to
enrollment, fees, accounting, attendance, inventory, HR, and other transaction
records as those modules are implemented.

## Migration

```text
2026_08_03_000011_add_enrollment_lifecycle_history
2026_08_03_000012_add_student_type_to_enrollment_history
```

## Verification

- Migration completed successfully.
- Existing Pest tests passed: 2/2.
- PHP lint passed for the new model and enrollment controller.

## Next step

Step 6 can implement promotion and transfer workflows using the enrollment
history foundation, followed by Grading and Attendance.
