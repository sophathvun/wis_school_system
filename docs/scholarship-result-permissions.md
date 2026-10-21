# Scholarship results and permission tabs

`Students > WIS Scholarship > Scholarship Result` is a read-only view of **published, awarded students only**. Any user with `students.view`, `western-scholarship.view` and `western-scholarship.tab-scholarship-result` can open it, within the existing campus/committee scope. It never grants access to another campus automatically.

The view shows student identity/photo, exam center, room/seat/code, assigned award label and scholarship award percentage. Scores, totals, score percentages, numerical score ranks, EOY averages, award exception reasons, unawarded students and unpublished records are excluded by the server. Reopening results removes them from this view.

`export-scholarship-result` independently enables Print, Excel and PDF. These endpoints use the same score-free winner projection, force the winner scope even if `report_students=all` is submitted, and validate the selected grades/centers. Multiple grades use separate Excel sheets; PDF can combine grades or provide separate files in a ZIP. Signers are set by Central staff in the confidential Result tab.

The existing **Result** tab retains confidential score review and winner management. It now requires a Central/global identity (including an active Central campus assignment) and its tab permission. Its actions and confidential exports cannot be enabled for Campus users merely by assigning all scholarship grants. Import / Export is likewise Central only because its archives contain scores.

## Permission page structure

`Students (main menu) > WIS Scholarship (submenu) > tab > functions`

- Assign Student Rank: rank/EOY/signers management, candidate rank reports.
- Scholarship Settings: exam/subject/schedule/order/center settings.
- Seat Plan: generate/confirm/reopen, candidate/attendance/covers/stickers/seat-plan reports and report signers.
- Scoring: score drafts and absences, reviews, score verification reports.
- Result — Central / Confidential: winners, publication/reopening, result signers, result-list exports, confidential score exports.
- Scholarship Result — Published Award Winners: read-only published winners and separate score-free export permission.
- Exam Committee: committee/invigilator/import/duty/session management and invigilator exports.
- Import / Export: historical imports and score-containing archive exports.

Disabling a main menu, submenu or tab clears dependent functions in the UI and on the server. The old shared `western-scholarship.export` grant is hidden; each tab has its own report grant.

## Deployment

Run `php artisan migrate --force` and rebuild frontend assets. Migration `2026_10_21_000003_add_scholarship_published_result_permissions` registers the new permissions, copies existing Result visibility to Scholarship Result, splits old report grants by tab, and preserves explicit user denials. It does not grant scholarship access to all users automatically; administrators still choose authorized roles/users. Central-only rules apply regardless of migrated grants.
