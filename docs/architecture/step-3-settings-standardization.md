# Step 3 — Existing Settings Standardization

Status: Implemented

## Preserved

- Existing sidebar and menu labels
- Existing Blade layout and Tabler styling
- Existing route names and URLs
- Existing AJAX response shape from Laravel pagination
- Existing pagination component and page-size choices
- Existing SweetAlert and Tabler toast behavior

## Implemented

- Added bounded settings pagination: minimum 1 and maximum 100 records per page.
- Added shared search normalization by trimming search values.
- Added shared sort-direction validation.
- Added shared allow-listed sort-column validation.
- Applied the shared query conventions to academic years, education levels, grades, classes, sessions, school information, programs, groups, and locations.
- Fixed program search grouping so the search condition cannot escape the intended query scope.
- Added soft deletes to configuration entities:
  - Academic years
  - School information / campuses
  - Education levels
  - Programs
  - Grades
  - Classes
  - Groups
  - Sessions
- Added `SoftDeletes` to the matching Eloquent models.

## Migration

```text
2026_08_03_000002_add_soft_deletes_to_settings_tables
```

Existing delete endpoints remain unchanged from the frontend perspective. They now move supported configuration records into the soft-deleted state instead of physically removing them.

## Deliberately deferred

- Applying authentication and permission middleware to every existing settings route. The current application still has no completed login UI.
- Soft deletes for geographic reference data, students, enrollments, attendance, grades, payments, and audit records. Those require domain-specific retention rules.
- Full audit logging. This remains part of the security and governance hardening before production use.
- Replacing legacy `tb_` tables or integer IDs.

## Next step

The next implementation phase is **Step 4 — Student and Family Management**. Before that phase, the application should add the first administrator assignment flow and begin applying the new campus and permission middleware to protected routes.
