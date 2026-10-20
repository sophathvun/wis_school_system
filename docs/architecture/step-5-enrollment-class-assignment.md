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

The enrollment list opens on the started regular academic year. Its default year
option is labeled Active Academic Year. Searching by name, ID or family number,
or choosing a student, shows one enrollment per student: the active-year record
when present, otherwise the latest academic-year record. Clearing the search
restores the active-year list. Explicit historical year filters remain available.
Transfers in the same year prefer the active placement, then the newest record.
This selection happens before pagination, campus/status filters, and totals;
historical records remain available in the student's academic-year details.
When editing a historical record, the form retains its original academic year
even after that year is completed. Saving profile corrections accepts that
original year; new enrollments still require a started or pending year, and
assignment changes still require the Transfer Student workflow.
Edit permits student details, family contacts and documents only. The entire
Enrollment Information fieldset is read-only, including status, dates and remarks.
The save endpoint rejects submitted changes to enrollment fields and obtains
omitted disabled values from the stored record. Profile saves preserve the
enrollment row, including legacy group/type values and terminal status, rather
than recalculating them or clearing historical dates/reasons. Transfer and the
other enrollment lifecycle workflows remain the paths for enrollment changes.

Search waits for a 300 ms typing pause; Enter submits immediately, and clearing
the box restores the active-year list immediately. Outdated list, statistics and
filter requests are cancelled. The server resolves matching student/campus IDs
before choosing each student's preferred enrollment, avoiding a history lookup
for every enrollment in the database during a name search. Existing student/year
indexes support the narrowed lookup; no new database migration is required.

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

## Student Enrollment permissions

`StudentEnrollmentPermissions` enforces `students.view`, the enrollment submenu,
and individual list/filter/create/update/delete/profile/history/print/PDF actions.
The permission page lists those actions under Students > Student Enrollment;
document upload, preview, download and deletion remain under Student Documents.
Legacy Manage grants are migrated to create/update; status changes continue to
use the enrollment lifecycle workflows.

Campus users can read/filter/profile/history across all campuses and create new
enrollments at any campus when granted the relevant action. Editing and deleting
existing enrollments require the record campus to be assigned to the user and
the student to still belong to an assigned campus. Ownership uses the started
regular year first, otherwise the latest regular year, so an older enrollment
does not grant a former campus access to a transferred student's shared profile.
Central-office campus assignments (CEN/CENTRAL/CENTRAL OFFICE), global users and
active global roles can modify all campuses, with the individual action grant.
Super administrators retain full access.

List responses supply server-calculated `can_edit`/`can_delete` flags; save/delete
also enforce ownership server-side. Ownership and grants are cached only within
the request, with ownership loaded once for all students on the current page.
Adding an enrollment for an existing student without edit access preserves the
stored profile, photo and family information. Document writes also respect
campus ownership. New enrollment attachments retain their separate upload
permission and a session-bound, ten-minute creation window for other campuses.

Deploy `2026_10_20_000001_add_student_enrollment_action_permissions` to register
the actions and preserve existing enrollment viewers' filter/profile/history/
print access. Delete always requires its own grant.

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
