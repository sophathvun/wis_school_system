## Before you start

Open **Students → Import / Export Data**. This page uses separate CSV workflows for student information, enrollment records, and family members. It is different from G9/G12 Excel imports or scholarship score transfers.

## Choose the correct file type

| Section | Data |
| --- | --- |
| Student Information | Personal information and addresses |
| Student Enrollments | One row per student per academic year |
| Mother, Father & Guardian | Shared family members linked by Family Number |

## Prepare a CSV

1. Download the CSV template for the relevant section.
2. Keep an unchanged copy of the original data.
3. Preserve the template's expected columns and identifiers.
4. Check Student ID, Family Number, academic year, and placement fields as appropriate.
5. Review the page's CSV Preparation Notes before importing.

Import Student Information before Enrollments. Enrollment rows link by Student ID and Academic Year, allowing one student to retain several academic-year records.

Enrollment Class accepts a configured class letter (`A`), a grade/class label (`10A`, `K1-A`, or `N-A`), or a class ID. The selected class must fit the row's academic year, grade, and session. A class configured specifically for that placement takes priority over a general class; ambiguous matches require an ID.

Session and Group are different fields. Leave Group blank unless a separate group exists for the class. Older files that repeat the selected Session in Group remain accepted when no group with that name exists. Dates should use `YYYY-MM-DD`; Excel-style `M/D/YYYY` values are also accepted and converted to that format.

Use the same Family Number for siblings. Where the template supports it, a Student ID can specify a particular child's mother, father, or guardian association.

The Enrollment template includes `enrollment_status` and `status`. Use `active` for current enrollment, `completed` for a finished academic year, or `withdrawn` for an enrollment the student withdrew from. For withdrawal, set `ended_on` to the withdrawal date and enter `exit_reason` if known. Keep `status` as `1` so the historical record remains enabled. If blank or omitted, these fields default to `active` and `1`; older files remain accepted. The sample row describes a completed academic year.

The `academic_track_id` column accepts a configured Academic Track code, exact English/Khmer name, or ID. The track must match the row's grade. Leave it blank when not applicable or unknown for a historical record; a blank cell clears the track on update. Older CSV files without this column preserve any existing track. Unknown, deleted, ambiguous, or incompatible tracks are reported for review rather than guessed.

## Import and review the outcome

Choose the CSV in its matching section: drag and drop it, use **Browse File**, or paste a copied file while the upload box is focused. The selected filename and size appear in a file card; use its remove button to choose another file. Each import accepts one CSV/TXT file up to 10 MB.

Use **Import**. A live progress card shows the file upload percentage, then switches to the actual percentage of non-empty CSV rows processed, with imported and rejected counts. Keep the page open until it finishes. While the server prepares the file or a progress update is unavailable, the card shows a waiting indicator instead of an estimated percentage. After completion, review **Import Result**, including Rows processed, Imported successfully, and Not imported.

For failed rows, inspect CSV Row, Record, Missing Fields, Reason, and Submitted Information. Correct those records before retrying. Do not assume the whole file failed or succeeded when only some rows were imported.

For enrollment imports, use **Download Failed Rows CSV** to save only the rejected rows. The file preserves your submitted columns and values and adds `import_csv_row`, `import_missing_fields`, and `import_error`. Correct the data and upload this file again; these error columns are ignored by the importer. Successfully imported rows are excluded from this download.

Rejected enrollment CSV files are archived privately in `storage/app/private/student-data-transfer/failed-rows/`. The latest download remains available in the Student Enrollments card during your current session, including after a page refresh, until another enrollment import replaces the result. Download your copy before ending the session. Earlier imports performed before this feature was added have no archived file; retry the original CSV to generate one.

This workflow is not the preview/confirm interface used by the National Exam imports. Read the current page and result carefully before importing again.

## Photos and exports

CSV photo values refer to storage paths; importing CSV does not upload the photo files themselves. Use the appropriate photo workflow for file uploads.

Use **Export Existing Data** for the required section and review its contents before sharing it. Exports can include student and family information.
