# Scholarship answer sheets

The **Answer Sheets** tab connects printed multiple-choice sheets to existing
Scholarship Scoring. It uses local browser alignment and PHP GD detection; there
is no cloud scanning service or additional Python model.

## Setup and permissions

Deploy the code and compiled assets, then run:

```bash
php artisan migrate --path=database/migrations/2026_10_22_000001_create_scholarship_answer_sheet_workflow.php
php artisan migrate --path=database/migrations/2026_10_22_000002_add_scholarship_question_scores.php
php artisan migrate --path=database/migrations/2026_10_22_000003_add_answer_sheet_delete_permission.php
php artisan migrate --path=database/migrations/2026_10_22_000004_add_answer_sheet_link_permission.php
php artisan migrate --path=database/migrations/2026_10_22_000005_update_answer_sheet_setup_permission_names.php
php artisan migrate --path=database/migrations/2026_10_22_000006_add_answer_sheet_unlink_permission.php
php artisan migrate --path=database/migrations/2026_10_22_000007_create_scholarship_teacher_key_sheets.php
```

The migration adds answer keys, issued sheets, scan attempts and the new permissions.
Existing scores and paper configurations are retained. Under **Students > WIS
Scholarship > Answer Sheets**, grant the tab and its actions by role, department or
user. Central and Campus staff can set up shared grade / subject papers, edit draft
keys and approve key revisions when granted each corresponding permission and the
Answer Sheets tab permission. Papers and keys are shared across campuses: an
authorized edit applies to every student using that paper. Deletion, score
verification and replacement are Central functions. Student linking, printing and uploading remain campus scoped. Grant
**Link grade students to answer-sheet papers** separately from printing. Saving verified scores also
requires the existing Scoring permission and a matching Score Entry committee duty
(super administrators retain their existing bypass).

## Operator flow

1. Select Academic Year, then create a paper for a subject, grade and version.
   Choose **Letters** or **Numbers** and enter a count from 2–26, for example
   6 generates A–F or 1–6. Configure 1–100 questions and a default question score.
   The default only initializes the individual question scores. Use a new version after
   sheets have been issued to students. To reuse its setup, select the existing paper in
   Paper Setup and enter a different Paper Version before saving. This creates
   a separate paper with its own draft key; the original key and sheets stay intact.
   The **Answer Sheet List** shows every paper for the selected academic year,
   with **View**, **Answer Key**, **Edit** and **Delete** actions. Answer Key opens
   that row's key directly, including linked papers, using the same key permissions
   as the header button. Saving and approving apply to the opened paper regardless
   of the current filter selection. View shows configuration and,
   only with key permissions, the approved/draft answer key and question scores.
   Edit changes an unlinked paper in place, including its subject, grade and version.
   Saving setup clears its draft answers and approvals after confirmation, and
   records the previous configuration/keys in the activity history. A new key
   must be approved before printing again. Delete requires the separate
   **Delete unlinked answer-sheet papers** permission and confirmation. Issued
   student sheets, legacy score references and scan references block editing or
   deletion on the server, even if no scan has yet been uploaded. Linked setups
   stay protected; their answer/score corrections use Answer Key revisions.
   **Unlink Students** has its own permission and can remove links before any
   scan or recorded score for this paper. It removes only links within the user's
   permitted campuses and records the removed sheets in the activity history.
   If links in other campuses remain, shared setup remains protected. Once all
   links are removed, edit the same paper (for example 20 to 22 questions), enter
   and approve the corrected key, link students again and print new sheets.
   Old print batches and sheet codes become invalid immediately after unlinking.
   Even a pending scan blocks unlinking; existing scores and scan history are
   never removed by this action.
2. Open **Answer Key**. The heading identifies the subject, grade and paper version.
   Each row has Question, Correct Answer and Score columns. Choose a suggested
   score or type a custom positive value (up to four decimal places); questions
   may have different scores. **Fill Question Scores** can apply a preset or
   custom score to all rows, after which any individual row can still be edited.
   The live total must match the subject maximum before approval. Fill the
   correct responses and scores, save the draft, then approve it.
   Approving with blank answers lists their question numbers, marks each missing
   answer with a red border and its own message, and focuses the first blank.
   Warnings clear as answers are selected; incomplete drafts can still be saved.
   Approval creates an immutable numbered key revision. The printed sheet contains
   no correct answers. Users without key permissions never receive draft or approved
   answers in the page data.
   Alternatively, click **Print Teacher Key** (in the header or Answer Key modal)
   to print a blank **ANSWER KEY** for the selected grade, subject and paper.
   This shares the student sheet's A4 header, instruction box, grouped questions
   and labeled bubbles, with School Logo 1, teacher name/date/signature
   fields and a distinct teacher-key QR/identification code. Printing a teacher
   key does not require approved answers, confirmed seats or student links.
   The teacher fills one correct bubble per question. Open **Answer Key >
   Scan / Upload Teacher Key**. Drag and drop one JPG/PNG/WebP (up to 10 MB / 24 MP),
   click **Browse File**, paste a copied photo with **Ctrl+V / Command+V**, or use
   **Take Photo**. The uploader uses the student scanner's style, shows the filename
   and size, and lets you remove or replace the photo. Replacing or removing it
   clears previous detection results. Check Auto Align or set the four corners manually, then
   click **Upload & Detect**. Review the aligned image and choose **Use Detected
   Answers** to replace answers in the editor while keeping question scores.
   Blank, multiple or unclear marks get per-question red warnings. The scanned
   preview remains available in the editor for comparison. Review every answer,
   **Save Draft**, then **Approve Key**. Scanning never saves, approves, replaces
   an approved key or changes a student score automatically. Detection activity,
   flagged answers and an image hash are recorded; the image preview stays in the
   current editor session and is not retained as an attachment.
   Grant **Print blank teacher answer-key sheets** and **Scan/upload teacher
   answer keys** under Answer Sheets separately. Scanning also requires **Edit
   shared draft answer keys**. Central and Campus staff can use these grants.
   A student sheet, wrong paper, old paper setup or stale draft is rejected.
   Teacher sheets cannot be uploaded as student scores. Template identity stays
   valid across key revisions while its grade/subject/question layout is unchanged.
   Newly issued teacher keys use the current student layout. Previously printed
   teacher keys keep their original layout and remain scannable for the same setup.
   Adding a paper is blocked if its subject, grade and paper version already
   exist in the exam. A **Paper Already Exists** SweetAlert identifies the paper.
   Selecting an existing paper in Paper Setup copies its settings for a new
   version; use **Edit** in the list to change an existing unlinked paper.
3. Confirm seats. In **Answer Sheet List**, click **Link Students** for the desired
   paper and confirm. This links students in that paper's grade across permitted
   campuses and exam centers without opening a print preview. The answer key must
   already be approved. The linked count refreshes after completion. Repeating
   the action keeps existing links and adds missing students; it creates no scores.
   A student is linked once per paper, even when a layout update, corrected name
   or seating change produces a different printed-sheet snapshot. The list and
   link/unlink totals count distinct students. Historical printed-sheet versions
   and their scans are retained, so older tokens do not inflate the student count.
   **Print Sheets** offers all students in the selected center,
   selected rooms within one center, or selected students. Each candidate receives
   one A4 portrait page with School Logo 1, candidate details, QR code, an independent
   machine-readable identification block, and four alignment squares. Reprinting
   the same candidate/paper/seating keeps its sheet identity. The browser print
   dialog can also save PDF. Print at Actual size / 100%, no margins, and turn off
   browser headers/footers. Keep all four squares visible.
   New student sheets follow the reference format: School Logo 1, scholarship
   examination heading, academic year, subject and grade, invigilator lines,
   shaded instructions, student name/grade/room/seat, and choices inside circles
   under an Answer heading. The usual 40-question A–E sheet uses three grouped
   columns (1–16, 17–32, 33–40). Other question/choice counts fit on one A4 page.
   QR and sheet-identification blocks are retained for matching scanned scores.
   Layout version is saved on each issued sheet. Previously printed layouts stay
   readable; new print batches issue the new format without rewriting old sheet
   identities. A reprint of the same current layout and seating reuses its ID.
4. In **Scan & Upload**, drag up to **25 photos** into the upload box, click **Browse Files**,
   paste a copied photo with **Ctrl+V / Command+V**, or use **Take Photo** on a phone.
   JPG, PNG and WebP are supported (up to 10 MB and 24 megapixels). The selected
   filenames, sizes, thumbnails and per-sheet states appear in the queue. Each
   queue is for the selected exam, grade, subject and paper. Click **Upload Batch**
   to upload one sheet at a time. **Pause** finishes the current sheet; **Resume
   Batch** continues with the remaining ready sheets. A failed sheet does not stop
   other uploads. **Retry Failed** retries only failed entries; uploaded entries
   are kept. Wrong paper identities, duplicate images and inaccessible students
   are checked by the same per-sheet server rules as a single upload.
   Use **Align** on any sheet needing attention, correct its four corners, then
   upload that sheet or resume the batch. Uploaded rows identify the student and
   show flagged-question counts with pending verification status. Authorized
   reviewers can open **Review** directly from a completed row. **Refresh Scan
   History** reloads the list. **Clear Queue** removes local selections only;
   uploaded scans remain saved. Unuploaded photos are kept only on the current
   page. The queue summary reports uploaded, needs-attention, failed and ready
   counts. Single-photo uploads and camera capture are also available.
   If the upload box is greyed out, a yellow **Uploads are currently unavailable**
   message identifies the selected year/grade/subject/paper and every blocking
   condition: an unconfirmed Seat Plan, an unapproved key, published results or
   a historical exam. Confirm seats and approve the selected paper's key, or ask
   an authorized staff member to reopen published results as appropriate.
   Auto Align looks for corner squares. If it fails, choose
   **Set Corners**, then tap their centers clockwise: top left, top right, bottom
   right, bottom left. Check the aligned preview before uploading.
5. Upload creates a **Pending** attempt; it does not save a score. Detection runs
   on server image pixels after validating the four markers and sheet identity.
   The original and aligned images stay in private storage. Exact duplicates for
   the same key are rejected. Multiple marks, weak marks and blanks are flagged;
   no reliable single answer is recorded for ambiguous questions.
6. Central staff open **Review**, inspect the image using zoom, check flagged
   questions and correct responses if necessary. A blank counts as incorrect, not
   absence. Corrections require a reason. Confirm the verification checkbox, then
   **Verify & Save Draft Score**. The server calculates the score against the
   approved key's question scores; a client-supplied total score is never used.
7. Continue the existing Scoring review, award selection and publication workflow.
   A changed score clears review and affected grade awards. Other subjects' saved
   scores and provenance are preserved.

## Corrections and locks

- An existing subject score, including zero, requires the separate replacement
  permission, an explicit replacement checkbox and a reason.
- An accepted attempt cannot be verified twice. **Recheck with Current Key** creates
  a new pending attempt and retains the earlier attempt and score for audit.
- Approving a corrected key leaves existing scores intact but clears affected
  candidate reviews and grade awards. Those scanned scores must be explicitly
  rechecked before review/publication. A scan made before another candidate-score
  change also requires rechecking to capture the current version.
- Answer and score changes are versioned together. Already approved keys without
  individual scores retain their original uniform scores; existing issued sheets
  remain valid. A wider choice range uses an adapted two-column A4 layout, while
  already issued 2–5-choice sheets retain their original bubble coordinates.
- Missing sheets leave scores blank. Absence is managed explicitly in Scoring.
  Scans cannot overwrite a subject/all-exam absence until attendance is corrected.
- Published and historical exams reject mutations. Changed seating or paper
  details invalidate an old sheet. Unauthorized campus candidates/rooms, wrong
  paper identifiers, unreadable codes and misaligned images are rejected.
- The application stores an activity history for setup, key approval, issuance,
  upload, recheck and verification, including correction/replacement reasons.

Detection uses fill-density thresholds, not handwriting recognition. Use a pen or
pencil that produces a dark, fully filled circle, consistent lighting and clear
erasure. Test physical sheets from the intended printer and phone/scanner before
exam use. All uploads require human verification; faint erasures, shadows, blur
and unusual paper scaling can still need manual correction.

## Verification

`tests/Feature/ScholarshipAnswerSheetsTest.php` covers configuration, both label
styles, immutable keys, scoped issuance, actual pixel detection, locks, private
storage, duplicate uploads, permissions, replacement, provenance and rechecks.
`tests/js/answer-sheet-alignment.test.mjs` covers perspective mapping and corner
detection. `tests/browser/scholarship-answer-sheets.mjs` exercises the real Blade/JS
on desktop and mobile using an isolated SQLite fixture, rasterizes the actual SVG
print layout, marks its circles, aligns a simulated photo, uploads it and saves a
verified score. It never uses production students or sessions.
