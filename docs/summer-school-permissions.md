# Summer School permissions

Permissions are listed under **Students > Summer School** in the permission page:

| Action | Scope |
| --- | --- |
| View list | All campuses |
| Search and filter | All campuses |
| Register summer students | All campuses, including existing Western students |
| Edit student, family, photo and documents | Assigned campus; central/global staff: all campuses |
| Delete summer enrollment | Assigned campus; central/global staff: all campuses |
| Continue at Western | Assigned source campus; central/global staff: all campuses |

Every action requires its individual permission, the Students menu, and the
Summer School submenu. Super administrators retain full access. Central staff
follow the existing enrollment rules: an active central campus assignment
(`CEN`, `CENTRAL`, `CENTRAL OFFICE`), a global user, or an active global role.

The server computes per-record buttons and enforces the same permissions on
details, save, delete and continuation requests. Reading and registration are
not restricted to the user's campus. Filter options include all campuses and
all Summer students, rather than only the current page of results.

Edits send the existing enrollment ID and update that record without creating a
second student or enrollment. The stored student identity, origin and enrollment
assignment are preserved; use enrollment workflows for assignment changes.
During Edit, the Enrollment Information fieldset (including searchable dropdowns
and the enrolled-date calendar) is disabled. A notice directs users to the
Transfer module. Asynchronous Western-student loading cannot unlock the section.
Existing Western student registration leaves the shared personal/family data
unchanged. A historical external Summer record cannot bypass campus ownership
of a student's later regular enrollment.

Deploy the migration:

```sh
php artisan migrate --path=database/migrations/2026_10_21_000001_add_summer_school_action_permissions.php --force
```

Existing Summer viewers receive filter access. Existing Manage assignments are
migrated to Register, Edit and Continue permissions. Delete requires an explicit
grant. Legacy Manage is hidden from the permission action tree after migration.
