# Historical G9 certificates

The maintenance command imports the old certificate workbook from
`storage/app/imports/G9_National_Exam_Certificates` and its academic-year folders.
Use an active super administrator's ID for the audit trail:

```powershell
php artisan g9:import-certificate-backup --user=ADMIN_ID
php artisan g9:import-certificate-backup --user=ADMIN_ID --commit
```

The first command only previews. Each run writes a private `plan.json` under
`storage/app/private/g9-certificate-import/`. A committed run also saves original
database rows in `before.json`, row outcomes in `report.json`, and `summary.json`.
These files contain student information and must remain private.

Matching requires one existing candidate with the same academic year and student
ID and an agreeing Khmer name. Source photos must match the workbook filename
stem exactly, ignoring case, inside that academic year's corresponding folder.
Missing, ambiguous, and unreferenced photos are never guessed. Original files
remain untouched. Imported photos use the same WebP master/thumbnail pipeline and
protected routes as interactive uploads.

A filename containing another student's ID is rejected even if the workbook
references it. A private `--photo-map=relative/path.json` may record an individually
reviewed correction keyed by `academic-year|student-code`, then `provisional` or
`receipt`. Each entry requires `filename`, original-byte `sha256`, the exact issued
`certificate_number`, and a review `reason`. The corrected filename must contain
the student's ID and remain in the same academic-year/document-kind folder. Check
the certificate number and biographical details visually before adding a mapping;
the importer never infers these corrections from a shared name.

Issued numbers are copied literally, including Khmer digits and the old sequence
length. Any conflict with an existing ledger, tracking, or legacy number blocks
that row. Historical duplicate numbers are preserved and flagged, not renumbered.
The command does not call the number generator or modify the number ledger.

Only passed students receive tracking records/documents. Failed, absent, and
pending-result records remain blocked. Existing candidate records, scores, and
legacy issued numbers are unchanged. An existing manually managed tracking record
is not overwritten. Repeating the same source skips identical tracking/documents;
student changes after preflight require a fresh run.

Historical `Withdrew` means received. Missing receipt evidence, dates, or recipient
names are retained as incomplete imported history, with a review badge. This
maintenance exception does not relax normal upload/receipt validation. The source
certificate issue date is preserved separately; it is never assumed to be the
actual printing/signing date.
