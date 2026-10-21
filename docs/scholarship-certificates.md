# WIS Scholarship certificates

Open **Students → WIS Scholarship → Certificates**. Choose an academic year, then use **Settings** to save the issue date, scholarship academic year, signing location, and two signers. Staff selections copy names/positions into the settings; they do not change HRM records.

Settings opens in a popup. Saving closes the form and confirms the action. The certificate list uses the shared Student Enrollment pagination controls: 15 rows by default, with All, 15, 25, 50 and 100 options, previous/next buttons, page numbers, and Go to Page. Print and PDF use all issued certificates matching the academic year, campus, grade, certificate type and student-name filters across all pages. There is no Print Scope selector. Clicking Print Preview or PDF opens a popup with **Content Only**, **Include Certificate Frame**, and **Cancel** actions.

**Customize Templates** provides separate Class Rank, Exam Place, and Best in Subject templates. Select a text/logo field, edit its text and automatic values, select its font, size, color, alignment and bold styling, and change its box size. Drag fields or use the arrow buttons to move them. Positions and print offsets use millimeters on portrait A4. Save each changed template. Long names shrink to their boxes. Use a test sheet to calibrate the printer offsets.

Branding School Logo 1/2 or an uploaded logo can replace the frame logo in framed output. Enable the Replacement Logo field in the editor. A physical preprinted logo cannot be erased by the printer. The supplied shared frame comes from `storage/app/imports/Certificate Portrait-01.jpg`; a different portrait frame can be uploaded in Settings. Uploads accept JPEG, PNG or WebP, up to 10 MB and 20 megapixels. Decoded images are re-encoded and stored privately. Custom text is plain text; HTML is escaped.

## Numbers and eligibility

Central staff preview and assign numbers only after regular scholarship results have been published. Assignment always includes eligible students across all campuses; display filters do not change this numbering scope. The confirmation list shows every student and proposed number.

- Format: academic-year ending two digits plus a four-digit sequence. `2025-2026` starts at `260001`; `2026-2027` starts at `270001`.
- First batch: grade ascending, configured Best in Subject order, 3rd Place, 2nd Place, 1st Place.
- Second batch: class ranks 1–3 excluding all exam award winners. Order: campus ascending, grade ascending, rank 3 → 2 → 1; class/name provide stable tie breakers.
- One student receives one certificate in an academic year. The sequence is shared across campuses and certificate types.
- Assigned numbers and recipient/award snapshots stay unchanged on reprints. Late eligible additions append to the last sequence. Duplicate source assignments must be corrected before issuing.
- Preview hashes and a transaction locking the academic year prevent stale or simultaneous assignments. Unique database constraints protect year/student, year/sequence, and certificate number.
- Cancelled certificates retain their number and reason permanently. Cancellation does not automatically issue a replacement or make the number reusable.
- Reopening results prevents printing. Source award/rank changes flag existing certificates for review and block printing; the old certificate remains visible. Do not cancel valid certificates simply to edit wording: edit the template/settings and reprint the existing number.
- Historical imports are not automatically issued new certificates.

## Permissions

Permission page grouping: **Students → WIS Scholarship → Certificates → functions**.

| Function | Access |
| --- | --- |
| View Certificates tab | Any permitted user, within their authorized campuses |
| Settings (dates, signers, frame/logo) | Central, with permission |
| Customize templates | Central, with permission |
| Preview / assign numbers | Central, with permission |
| A4 preview, print and PDF | With permission, within authorized campuses |
| Cancel issued certificates | Central, with permission |

Campus users cannot alter shared settings or number assignments even if those write grants were mistakenly selected. Certificate pages and exports contain award information, not examination scores or EOY averages. Settings, template saves, assignments and cancellations are logged in the scholarship event history.

## Printing and deployment

Choose the filters for the required certificates, then click **A4 Print Preview** or **PDF**. In the popup, **Content Only** prints onto preprinted frames. A4 preview displays the frame for alignment, while printing omits it. **Include Certificate Frame** prints/downloads the complete certificate. **Cancel** closes the popup without opening a preview or starting a download. PDFs contain one portrait A4 page per certificate. Browser print settings: A4 portrait, 100% scale, no margins, browser headers/footers off.

Deploy the application files and assets, then run:

```sh
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
```

The migration registers the new functions; administrators must grant them to the appropriate roles/users. The local development migration has already been applied. PHP GD is required for uploaded images. Existing mPDF and bundled fonts render PDFs locally, without a paid service.

Validation: `php artisan test tests/Feature/ScholarshipCertificatesTest.php`; `node tests/browser/scholarship-certificates.mjs`. The browser fixture uses its own temporary SQLite database and synthetic records.
