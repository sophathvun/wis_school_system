# WIS and GEP program workspaces

The top navigation offers a Program switcher to users authorized for both programs. Users with one program see its label and enter that workspace automatically. Mobile users can switch in the sidebar header. The selected program is saved in the session and on the user account for later sign-ins. Program switching warns when another form has unsaved changes.

## Assign access

Open Role and Permission and find **Program Workspaces**. Enable its main switch and select:

- **WIS Workspace** for WIS-only staff.
- **GEP Workspace** for GEP-only staff.
- Both for authorized Central Management staff.

Program access is an additional requirement, alongside existing module, action and campus permissions. An all-campus role does not automatically grant both programs. Inherited permissions still apply: assigning a GEP role does not remove WIS access inherited from another role or department. Review all inherited grants when creating a GEP-only account.

The migration preserves WIS access for existing roles and departments, and for existing roleless/departmentless accounts using direct overrides. Super Administrators can access both programs; the standard Central Office Administrator role receives both. Other roles require an explicit GEP grant. Existing student and enrollment records are unchanged.

## Current scope

This release adds program permissions, saved selection, a protected switch endpoint and a separate GEP landing dashboard/menu. The GEP landing page does not display WIS student statistics. Authenticated WIS academic endpoints are blocked while the selected program is GEP, including direct links and stale form submissions. Shared profile, account administration, communication and notification endpoints retain their existing authorization requirements.

GEP enrollment, attendance, reports, placement testing, a combined overview and shared accounting are subsequent work. They are not implemented by this workspace release. Existing Program settings remain unchanged; a workspace is not an academic-year-specific Program settings record.

The GEP Settings menu provides Academic Year, Terms, Levels, Classes and Group navigation with individual view permissions under **GEP Settings** on the permission page. Academic Year now supports a paginated list, Add New/Edit modal, deletion and Set Started. Year labels use consecutive years such as 2026-2027; statuses are Pending, Started and Finished. Starting a year finishes the previous started GEP year and never changes WIS years. Started years cannot be deleted. Future linked GEP data must reference this year table with restrictive foreign keys to protect against deletion. Create, Edit, Delete and Set Started permissions are independent of view access. Group remains a landing page. Super Administrators and the standard Central Office Administrator have access by default; other roles require explicit grants in addition to GEP workspace access.

## Deployment

Deploy the code and rebuilt assets, then run `php artisan migrate --force` to apply `2026_11_19_000002_add_program_workspace_access.php`. Refresh open application tabs. Existing live `.env` settings are not changed by this release.

The GEP Settings menu also requires `2026_11_19_000003_add_gep_settings_menu_permissions.php` (included in the same migrate command).

GEP Academic Year requires `2026_11_19_000004_create_gep_academic_years.php` and rebuilt assets containing `resources/js/gepAcademicYears.js`. The migration creates an empty GEP year table; it does not copy or change WIS academic years.

GEP Terms now supports Term Name, Abbreviation, On/Off status, Add/Edit modal, Delete, search and pagination. Terms are reusable definitions (for example Term 1 / T1); year-specific scheduling will be linked separately. New terms default to On. Status changes have their own permission and endpoint; editing a name does not overwrite status. This requires `2026_11_19_000005_create_gep_terms.php` and the rebuilt `resources/js/gepTerms.js` entry. No terms are pre-populated. Future linked records must use restrictive foreign keys to prevent deleting terms in use.

The local permission backup and rendered GEP verification page are in `storage/app/private/`; keep those working files local.

GEP Levels also includes a numeric Order field (default 0). Lists sort by Order ascending, then Level Name. Apply `2026_11_19_000008_add_sort_order_to_gep_levels.php` and rebuild assets.

GEP Classes provides reusable Class Name definitions (such as A, B, C, D or E), Order, On/Off status, Add/Edit modal, Delete, search and pagination. Each action has an independent permission. New classes default to On; lower Order values appear first. Assignments to campus, term and level will be introduced with enrollment. Apply `2026_11_19_000009_create_gep_classes.php` and rebuild assets including `resources/js/gepClasses.js`. Future linked records must use restrictive foreign keys to prevent deletion of classes in use. Group remains a landing page.

GEP Levels supports Type, Level Name, Abbreviation, Program, an On/Off toggle, Add/Edit modal, Delete, search and pagination. Type and Program are optional text fields and included in search. New levels default to On. Each action has a separate permission. Apply `2026_11_19_000006_create_gep_levels.php` and `2026_11_19_000007_add_type_and_program_to_gep_levels.php`, and rebuild assets including `resources/js/gepLevels.js`. No levels are pre-populated. Future linked records must use restrictive foreign keys to prevent deletion of levels in use.

GEP Classes no longer uses Abbreviation. Apply `2026_11_19_000010_make_gep_class_abbreviation_optional.php`; existing legacy abbreviations are retained internally, while new classes only require Class Name.

GEP Class Types is a separate GEP Settings submenu, with Full-Time and Part-Time initially On. It provides Class Type, Order, Status, Add/Edit/Delete, search, pagination and top-right success notifications. View and each action have independent permission entries. Apply `2026_11_19_000012_create_gep_class_types.php` and rebuild assets including `resources/js/gepClassTypes.js`. This defines class types only; linking them to class offerings and fees will be implemented with those modules. Future links must use restrictive foreign keys.

GEP Group settings provides Session, automatic Group code, Order, Status and Actions, using the same modal, pagination and success notification style as Classes. Migration `2026_11_19_000011_create_gep_groups.php` creates Morning/M, Afternoon/A and Evening/E with status On and order 1–3. Only these sessions are accepted; codes are derived on the server. Create/Edit/Delete/Status permissions are separate from view access. Rebuild assets including `resources/js/gepGroups.js`. Future linked records must use restrictive foreign keys to prevent deletion of groups in use.
