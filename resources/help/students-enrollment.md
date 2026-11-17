## Before you start

Open **Students → Student Enrollment**. Viewing, filtering, creating, editing, deleting, viewing history, and profile printing have separate permissions. Existing-record edits may be limited to the student's campus.

## Bulk upload student photos

Open **Students → Bulk Upload Student Photos**, directly below **Import / Export Data**. The shortcut in Student Enrollment also opens this page. On the permission page, enable **Students → Bulk Upload Student Photos**, together with Students and Student Enrollment view access. Campus users can update students belonging to their campus; Central Office can work across campuses.

Name each file **Student Name_Student ID.jpg**, for example **SOK DARA_2613230.jpg**. The ID after the last underscore must match exactly, including leading zeros. Name spelling does not determine the match. JPG, PNG and WebP files are supported, individually or inside a ZIP.

Choose up to 500 photos per batch. Each photo must be at most 10 MB and 24 megapixels. ZIP files can be up to 50 MB, with at most 250 MB of uncompressed contents; the server's upload limits may be lower. Individual files upload one at a time so PHP's multi-file count limit does not prevent a large batch.

The default mode is **Replace existing student photos**. Turn it off to fill missing photos only. Review current and uploaded images side by side, uncheck photos you do not want to import, and confirm the selected ready matches. Duplicate IDs, unreadable images, missing IDs and students outside your campus permissions are left for review. Download their review CSV and correct the files for another batch.

The progress bar shows upload and import progress separately. The importer uses the same portrait WebP converter as normal Student Enrollment uploads, up to 40 KB per saved photo. It updates the shared Student Information photo only, not National Exam photos or certificates. If another user changes a photo after preview, that row needs a new review instead of being overwritten.

You can resume confirmed work from **Your recent batches** for 24 hours. Private preview files expire after 24 hours and are pruned daily when the Laravel scheduler is running. The import history retains outcomes and previous/saved image paths. Previous public photos are retained for recovery. Administrators must grant the new permission explicitly; it is not automatically granted to existing roles.

## Check for an existing student

Search by Student ID and name before registering. Confirm whether this is a new student identity or a new enrollment for an existing student.

Check the academic year, campus, grade, class, session, and program context offered by the form. An enrollment belongs to its year; the student's identity and family links can be shared across years.

## Register the enrollment

1. Open the available enrollment or new-student action.
2. Enter or select the correct student.
3. Complete required student and contact details.
4. Select the intended academic and class placement.
5. Link or create family information as appropriate.
6. Add a student photo using the available upload/editor controls.
7. Review the form and save.

Read validation messages before retrying. Do not create a second identity to bypass a Student ID or enrollment conflict.

## Edit and review history

Open the correct student and year before editing. Review family and sibling information carefully because shared family data can affect more than one child.

Use the profile and enrollment-history actions to check previous years and academic changes. Promotion, transfer, withdrawal, graduation, and re-entry should use their dedicated workflows.

## Print a profile

Use the available profile Print or PDF action after verifying the selected student and displayed information. Check the preview before distributing a student profile.

## Troubleshooting

If you can view but cannot edit, check your editing permission and campus ownership. If a class or year is unavailable, review its configuration and lifecycle rather than entering an unrelated destination.
