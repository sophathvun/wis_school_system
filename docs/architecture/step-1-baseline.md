# Step 1 — Existing System Baseline

Status: Completed

This document records the current implementation before the SIS foundation is changed. It is intentionally an audit and compatibility plan; it does not introduce migrations, new modules, or behavior changes.

## 1. Current technology baseline

- Framework requirement: Laravel `^13.8` in `composer.json`.
- PHP requirement: `^8.3`.
- Frontend build: Vite with Bootstrap, Tabler assets, and project JavaScript modules.
- PDF generation: `barryvdh/laravel-dompdf`.
- Authentication model exists, but the current route inventory does not show a completed login, role, permission, or campus-access workflow.
- The default database migrations use integer IDs.

Before Step 2, the Laravel version decision must be explicit: continue on the current Laravel 13 dependency or deliberately align the project with Laravel 12.

## 2. Current module inventory

### Implemented or partially implemented

- Dashboard
- Academic years
- School information / campus-like records
- Education levels
- Programs
- Groups
- Grades
- Classes
- Sessions
- Locations: country, province, district, commune, village
- Student records
- Student enrollment
- Student photo upload
- PDF exports for selected settings pages
- Status toggling
- Shared pagination helper
- Sidebar search and favorites
- Theme and layout settings

### Placeholder navigation or routes

- Student search
- Student promotion
- Student graduation
- Student update
- Student transfer
- Student withdrawal
- Terms / quarters
- Campuses

These routes currently return the dashboard view or have no dedicated implementation. They must not be treated as completed SIS workflows.

## 3. Current persistence map

| Current table | Current purpose | Target domain | Compatibility decision |
|---|---|---|---|
| `users` | Laravel users | Identity | Preserve initially; extend for access control |
| `tb_school_info` | School and campus-like data | Organization | Preserve initially; later become the campus source of truth |
| `tb_academic_year` | Academic years | Academic | Preserve initially; add missing scope rules later |
| `tb_education_level` | Education levels | Academic | Preserve |
| `tb_program` | Programs | Academic | Preserve and review relationships |
| `tb_grade` | Grades | Academic | Preserve and review naming |
| `tb_class` | Classes | Academic | Preserve initially; evolve toward class sections |
| `tb_session` | Sessions | Academic | Preserve initially; clarify whether this means term, shift, or session |
| `tb_group` | Student groups | Academic / Student | Preserve and define business meaning |
| `tb_student` | Student master record | Student | Preserve initially; extend carefully |
| `tb_student_enrollment` | Student academic enrollment | Student | Preserve initially; add stronger academic and campus rules |
| `tb_country`, `tb_province`, `tb_district`, `tb_commune`, `tb_village` | Geographic data | Shared reference | Preserve |
| `cache*`, `jobs*`, `sessions` | Laravel infrastructure | Platform | Preserve and configure as needed |

## 4. Current relationship map

```text
tb_school_info
    └── tb_student_enrollment

tb_academic_year
    ├── tb_class
    └── tb_student_enrollment

tb_grade
    ├── tb_class
    └── tb_student_enrollment

tb_class
    └── tb_student_enrollment

tb_session
    ├── tb_class
    └── tb_student_enrollment

tb_student
    └── tb_student_enrollment

tb_country
    └── tb_province
        └── tb_district
            └── tb_commune
                └── tb_village

tb_student birth-place fields reference the location hierarchy.
```

## 5. Current UI and frontend conventions

These conventions will be preserved:

- Main layout: `resources/views/layouts/app.blade.php`.
- Sidebar: `resources/views/layouts/partials/sidebar.blade.php`.
- Navbar, footer, and settings partials remain shared layout components.
- Tabler navigation and icons are already used.
- The sidebar already supports route highlighting, search, mobile collapse, and favorites.
- CRUD pages use Blade views and module-specific JavaScript files.
- `resources/js/helpers/pagination.js` is the shared pagination implementation.
- Pagination expects Laravel paginator JSON fields such as `current_page`, `last_page`, `from`, `to`, and `total`.
- Existing pages use AJAX-style fetch, save, delete, status-toggle, SweetAlert, and Tabler toast helpers.

New pages should use the same layout, sidebar, pagination markup, response format, and notification behavior unless there is a documented reason to improve a shared helper.

## 6. Current route conventions

The current application uses named routes such as:

```text
academic-years.index
academic-years.fetch
academic-years.save
academic-years.delete
studentEnrollment.index
student-enrollments.fetch
student-enrollments.save
student-enrollments.delete
```

The naming is not fully consistent. Existing route names should be preserved while the system is stabilized. New modules should use one consistent convention, preferably:

```text
students.index
students.fetch
students.store
students.update
students.destroy
```

Route aliases may be used during transition so existing sidebar links do not break.

## 7. Current database risks and decisions

### Integer IDs

Current tables use auto-increment integer primary keys. We will not convert existing tables to UUIDs during Step 1. A future UUID transition would require a deliberate migration and compatibility plan.

New security-sensitive or externally exposed entities should use the approved public identifier strategy after Step 2 design review.

### `tb_` naming

Existing tables use the `tb_` prefix and mostly singular table names. Renaming them immediately would create unnecessary migration and code risk. Existing tables remain in place.

New tables should use the approved naming convention only after deciding whether the project will:

1. Continue the legacy `tb_` convention for compatibility, or
2. Use a new convention for new modules and introduce a controlled boundary.

The recommended option is to preserve legacy tables and use a documented naming boundary for new enterprise modules.

### Campus representation

`tb_school_info` is the existing School Profile and campus source of truth. Student enrollment already references it through `campus_id`. The system must not introduce a duplicate `campuses` table. Step 2 extends access control around `tb_school_info` rather than replacing it.

Step 2 must make it the controlled campus source of truth or introduce a compatibility layer before adding multi-campus authorization.

### Academic-year representation

Academic year is currently connected to class and enrollment records. The current implementation does not yet provide a complete active-year context, campus-specific year policy, term model, or year-locking rules.

### Soft deletes and audit

The reviewed models do not currently show a consistent soft-delete or audit-trail strategy. These must be introduced before sensitive modules such as grading, attendance, or finance.

### Authentication and authorization

The default `users` table and `User` model exist, but campus-scoped permissions are not implemented in the current baseline. Routes and controllers currently do not show a complete authentication and authorization boundary.

### Enrollment workflow

`StudentEnrollmentController` currently combines student creation/update, enrollment creation/update, photo storage, validation, duplicate checks, and pagination concerns in one controller. It uses a database transaction, which is good, but this workflow should move to an application service during the foundation phase.

## 8. Compatibility rules

The following rules apply to future implementation:

1. Do not rename or drop current tables during Step 1.
2. Do not replace the existing sidebar or pagination helper without a measured need.
3. Do not change existing route names without aliases.
4. Do not delete existing student or enrollment data.
5. Do not convert integer IDs to UUIDs without a migration rehearsal and rollback plan.
6. Do not add campus authorization only in the frontend; it must be enforced server-side.
7. Do not add attendance, grading, or finance before campus and academic-year scope exists.
8. Do not use hard deletes for historical student or enrollment records.
9. Keep existing bilingual fields where they are already used.
10. Add tests around existing behavior before refactoring shared workflows.

## 9. Target system map

```text
Shared Layout and Sidebar
        │
        ├── Dashboard
        ├── Settings
        ├── Students
        ├── Families
        ├── Attendance
        ├── Grading
        ├── Scheduling
        ├── Finance
        └── Reports

Request
  ↓
Authentication
  ↓
Campus Scope + Academic-Year Scope
  ↓
Permission / Policy Check
  ↓
Application Service
  ↓
Repository / Query Service
  ↓
Existing or New Database Tables
  ↓
Audit Event
```

## 10. Step 2 prerequisites

Before implementing the foundation, confirm:

- Laravel version target: 12 or current 13 dependency.
- Database engine and version.
- Whether `tb_school_info` becomes the campus table.
- Whether existing data already represents more than one campus.
- Whether student numbers must remain numeric and sequential.
- Whether current route names must remain permanently backward compatible.
- Central Office role requirements.
- Required bilingual labels and data fields.

## 11. Step 1 conclusion

The current system is a functioning settings and enrollment foundation, not yet a multi-campus SIS. The safest implementation path is incremental:

```text
Preserve current UI and data
        ↓
Add identity and campus access foundation
        ↓
Standardize existing settings
        ↓
Refactor student enrollment into services
        ↓
Build the remaining SIS modules
```

The next implementation step is **Step 2 — Foundation and Multi-Campus Access**. It should begin with a detailed schema decision for users, campuses, roles, permissions, user-campus assignments, academic-year context, and audit logs.
