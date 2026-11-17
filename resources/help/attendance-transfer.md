## Open Import / Export

Go to **Attendance > Import / Export**. Your administrator must grant Import / Export access and the separate Import or Export action for the appropriate campuses.

This workspace transfers **daily attendance**. It does not create subject teaching sessions or change enrollment, graduation, or certificate records.

## Prepare a CSV

1. Download the template and replace its example row.
2. Keep **Student ID** as text, including any leading zeros. Names are not used to match students.
3. Enter the academic year exactly as configured, such as **2022-2023**.
4. Use **YYYY-MM-DD** for attendance dates. Old **M/D/YYYY** and **DD-Mon-YYYY** formats are also accepted. Future dates are rejected.
5. Use **E** or **EX** for Excused Absence, **U** or **UX** for Unexcused Absence, **T** for Tardy, and **P** only when presence was actually recorded. Legacy L is accepted as Tardy.
6. Enter optional time/period, reason, campus name, and missed-session count. Use the exact campus name shown in the system if needed to disambiguate enrollment.
7. Save as **CSV UTF-8 (Comma delimited)**. Each file may contain up to 50,000 data rows and must be no larger than 12 MB.

Old Access headings Student ID, AbsDate, AbsTime, Attandance, Reason, and AcademicYear are recognized. A time such as **8:40-9:30 AM** is preserved with the reason; it is not guessed into a current schedule-period assignment.

## Match historical students

The system matches Student ID, academic year, and enrollment valid on that date. Withdrawn, completed, or graduated students can have earlier attendance imported when their enrollment dates cover the record. Missing students, overlapping enrollments, missing classes, and dates outside enrollment go to review.

Use one daily row per student and date. If the old system has different records for several periods on the same date, review and combine them into one daily record before importing. The system does not guess which daily status should win.

## Preview and confirm

1. Drag and drop a CSV, browse for it, or paste a file into the upload area.
2. Select **Upload & preview**. The upload shows its percentage; validation shows a separate checking stage.
3. Review Ready, Needs review, and Duplicates skipped counts. No attendance is saved during preview.
4. Select **Import valid rows** and confirm. The import shows percentage progress while saving small batches.
5. Keep the page open until completion. If interrupted, reopen the saved batch from **Your import history** and resume. Already saved rows are not inserted twice.

Existing attendance is never overwritten by this import. Conflicting records and scheduled approved leave go to review. Enrollment and permissions are checked again when rows are saved.

## Download review rows

Use **Download review CSV** to retain the source information, CSV row number, result, and explanation. The file includes both review rows and duplicates; remove rows you do not need to submit again, correct the remaining information, and upload a new file. Each saved batch remains available in your import history.

## Export records

Choose an academic year, optional campus, date range, and attendance status, then select **Export CSV**. Only recorded marks in your permitted campuses are exported. Schedule-period snapshots are included as reference information, not automatically restored as current period assignments. Use system backups for a complete restore.

## Absence-only history

When the source recorded only absent or late students, leave all other students unmarked. Missing records do not mean Present. Imported exception counts can be used in absence reports, but incomplete source data cannot establish a full attendance percentage.
