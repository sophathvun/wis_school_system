# G12 National Exams

Open **G12 National Exams** in the main menu (`/g12-national-exams`). This module
stores MoEYS national exam information separately from the school's existing G12
certificate-printing module and student profile.

## Permissions

Under **G12 National Exams**, enable the main menu, the required submenu and
its actions in Roles & Permissions. Role, department and user grants follow the
existing permission system. Records, documents, photos and exports are limited
to campuses the user can access with the relevant action. Creating centers is
shared setting maintenance and has its own permission. Collection correction
and photo replacement have separate permissions. Missing entries are registered
when Roles & Permissions opens; no normal role receives grants automatically.

Submenus: Student Exam Records, Exam Center Settings, Certificate Tracking,
Excel Imports / History, Bulk Student Photos, and Reports.

## Initial setup and Excel import

Manually added Exam Centers receive a system ID such as `EC-000027` after saving.
The ID is read-only in Add/Edit. Existing MoEYS center IDs imported from Excel
are retained; an imported center without an ID receives a system ID as well.

1. Review campus permissions and Exam Center settings.
   In **Excel Imports / History**, use **Download Excel Template** for an `.xlsx`
   workbook with the system's import headers, a fictional sample student, exam
   center settings and an Instructions sheet. Replace the sample student, year
   and dates before uploading. Existing active centers and an accessible campus
   are included when available; otherwise replace the sample center and campus.
   This download uses the existing **Preview and confirm MoEYS Excel imports**
   permission and does not export student records. IDs and filenames remain text.
2. Open Excel Imports / History and upload `.xlsx` (up to 20 MB).
   Use Browse File, drag and drop, or paste a copied Excel file with Ctrl+V /
   Command+V. The selected filename and size appear with a Remove action.
   Paste transfers a file from the clipboard, rather than copied spreadsheet
   cells or a file path. Preview Import remains a separate step.
3. Sheet 1 uses the provided `g12_national_exam` headers; Sheet 2 contains
   `excenter_id` and `exam_center`. Headers are case-insensitive. The original
   misspellings `class_sellected` and `inage_hard_certreceived_name` are supported;
   `image_hard_certreceived_name` is also accepted.
4. Preview every row. Map unknown campus codes and spelling variants of exam
   centers to existing centers or Sheet 2 centers. Staff with center-management
   permission can create a center from a previously unknown source name. For
   source text describing a non-exam situation, choose No center to preserve
   the source text without creating an invalid center setting.
5. Refresh Preview after mappings; confirm only after all issues are resolved.
   Confirming imports missing Sheet 2 centers as well, so it requires center
   management permission when those centers do not already exist.

Matching uses Student ID + Academic Year. A repeat import updates that record,
retains files and staff-managed certificate collection, and does not overwrite
the school student profile. Student IDs are strings, preserving leading zeros.
Academic years use consecutive years, such as `2025-2026`. Standard Excel date
serials for birth/collection dates are converted to ISO dates. Other source date
text, including Khmer dates, is retained. Original field values are retained in
the exam snapshot. Import previews expire after two hours and can be confirmed
only once. Changes to existing exam records require a fresh preview.

The user's confirmed meaning of **Withdrew** is **Collected**. Imported collection
dates and recipients are retained. If there is not yet a receipt image, the
student view explicitly notes the missing evidence. Not Yet becomes Awaiting;
Failed becomes Not applicable; other certificate statuses remain Needs review.
Exam result and certificate status are separate fields.

Add New and Edit use the same student fields, including subject letter grades,
family information, G9 exam details and MoEYS document filenames. Student ID and
academic year are fixed after creation. Name corrections keep the document
folder stable; simultaneous edits are protected by record versions.

## Bulk student portraits

Choose the academic year and upload individual JPG/PNG/WebP photos or ZIP files.
Academic Year is a searchable dropdown of distinct years found in student exam
records for the user's accessible campuses, newest first. Import student exam
records first if the dropdown is empty.
The maximum is 5,000 photos per batch, 20 MB and 40 megapixels per image, and
200 MB per ZIP upload / 500 MB expanded. The HTTP server/PHP upload and request
size limits must also permit the intended upload size. Photos are processed one
at a time after preview; the progress table retains failures for correction and
retry. A row already processed is idempotent.
Click **Preview Photo Matches** after choosing files or a ZIP. The preview lists
each photo and automatically selects exact matches. For unmatched photos, choose
the student and tick Select; Select All can select the pending rows together.
Upload Selected Photos remains disabled until a preview and selection exist.
Changing the files or academic year clears the preview to prevent uploading an
outdated selection. Failed previews keep the upload action unavailable.
For an archive larger than 200 MB (for example 598 MB), split it into smaller ZIPs
and preview/upload them one at a time. Size checks run before sending the file.
The server also returns JSON validation errors, including rejected PHP uploads,
so a size rejection is not misreported as an empty photo preview. A lower server
upload/request limit may require smaller batches or a server configuration change.

The matcher uses the optional `photo_filename` (or `moeys_photo_filename`) column
first, ignoring case and extension. It then uses an exact Student ID token in the
filename. An unrelated MoEYS filename such as `120804049559121000001.jpg` is not
guessed: staff manually select the student within that academic year in preview.
When no expected photo filename was set, the confirmed manual match is remembered
for later uploads.
Duplicate, ambiguous, existing and unmatched photos are highlighted. Staff select
which rows to upload and explicitly confirm replacements, which require permission.
New portrait uploads, including photos inside ZIPs, are converted to WebP strictly
below 40,000 bytes. The filename stem is retained, for example MoEYS-filename.jpg
becomes MoEYS-filename.webp, while the source filename remains in matching and
audit metadata. JPEG orientation is applied, proportions are preserved, transparent
areas use white, and photos are never enlarged. Photos fit within 600×800 pixels;
the encoder starts at quality 95 and reduces dimensions only when quality 80
cannot meet the size limit. Earlier photo files and versions remain available.
Certificate images, signed receipts and other documents retain their upload format.

Paths are relative to the **private local storage disk**:

```text
stuimages_g12_national_exam/2025-2026/MoEYS-filename.webp
stuimages_g12_national_exam/2025-2026/versions/unique-version/MoEYS-filename.webp

g12_national_exam_documents/2025-2026/ENGLISH_NAME_STUDENTID_identity-suffix/
  soft_certificate/unique-version/original-filename.jpg
  soft_certificate_received/unique-version/signed-copy.jpg
  hard_certificate/unique-version/original-filename.pdf
  hard_certificate_received/unique-version/signed-copy.jpg
  other_documents/unique-version/original-filename.pdf
```

The identity suffix prevents collisions when names or IDs contain characters
that cannot be used in folder names. Documents are opened through authorized
routes, not public storage URLs. Staged upload archives and source import files
remain private; include this disk in normal backups and storage maintenance.

## Certificates and documents

Open a student's View screen. Upload certificate images, signed copies and other
JPG/PNG/WebP/PDF documents in the Documents panel. The panel shows thumbnails,
original filenames, upload time and View/Download actions. Uploading a replacement
adds another version and retains earlier files; it does not mark a certificate
collected automatically.

Soft and hard certificates have independent tracking:

- Awaiting certificate
- Available at school (number and date received)
- Collected (collection date, recipient and signed-copy evidence)
- Not applicable / Needs review

New collection entries require a signed certificate-copy image/PDF. An already
uploaded signed copy can be used. Changing a collected certificate requires the
collection-correction permission and a reason of at least five characters. The
activity history preserves previous and new information. Updating one certificate
does not change the other type.

## Reports and deployment

Student and certificate lists use the shared pagination controls, with default
15 and All / 15 / 25 / 50 / 100. Reports apply the selected academic year, campus,
class, center, result and certificate filters to Excel and landscape A4 print.

Deploy code and built Vite assets, then run:

```sh
php artisan migrate --path=database/migrations/2026_10_26_000001_create_g12_national_exam_module.php --force
php artisan migrate --path=database/migrations/2026_10_26_000002_move_g12_sections_to_submenus.php --force
```

No MoEYS records or portraits are imported just by installing the module. Import
them through the preview and confirmation screens after assigning permissions.

Validation: `tests/Feature/G12NationalExamsTest.php` covers permissions, campus
boundaries, real sample workbook reading, duplicate/stale imports, collection
history, receipts, ZIP paths, portrait matching and private storage. The browser
suite `tests/browser/g12-national-exams.mjs` uses disposable SQLite and synthetic
students to exercise desktop/mobile forms, imports, documents, collection,
automatic/manual photo matching, pagination and report links.
