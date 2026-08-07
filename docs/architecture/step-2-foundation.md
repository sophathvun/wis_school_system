# Step 2 — Foundation and Multi-Campus Access

Status: Implemented

## Implemented

- Added `status`, `preferred_locale`, and `active_campus_id` to `users`.
- Added roles in `access_roles`.
- Added permissions in `access_permissions`.
- Added role-permission assignments in `access_role_permissions`.
- Added user-role assignments in `access_user_roles`.
- Added user-campus assignments in `access_user_campuses`.
- Added `User`, `Role`, `Permission`, and `SchoolInfo` relationships.
- Added campus context resolution.
- Added server-side campus access middleware.
- Added permission middleware.
- Added active-campus API endpoints.
- Added default roles and baseline permissions seeder.

## Default roles

- Super Administrator — global campus access
- Central Office Administrator — global campus access
- Campus Administrator — assigned-campus access
- Registrar — assigned-campus access
- Teacher — assigned-campus access

## Default permissions

- `dashboard.view`
- `students.view`
- `students.manage`
- `settings.manage`
- `reports.export`

## API endpoints

```text
GET  /access/campuses
POST /access/active-campus/{campus}
```

These endpoints require authentication, campus context, and campus access middleware. Existing routes are not yet protected so the current application remains usable during the transition.

## Campus source of truth

Campus records already exist in the School Profile table, `tb_school_info`. This table remains the canonical campus table for the SIS. The access foundation references its existing integer IDs through `access_user_campuses`, `access_user_roles`, and `users.active_campus_id`.

No separate `campuses` table will be created.

## Compatibility decision

The existing `users` and `tb_school_info` tables remain the source tables. Existing integer identifiers and `tb_` naming are preserved. The access tables are additive and can be used by future modules without changing current student or settings screens.

## Remaining work before Step 3

- Add or confirm the application login flow.
- Assign the first administrator to a user and campus.
- Add an administration screen for users, roles, permissions, and campus assignments.
- Apply `auth`, `campus.context`, `campus.access`, and permission middleware to module routes incrementally.
- Add feature tests for global, assigned-campus, and denied-campus access.
