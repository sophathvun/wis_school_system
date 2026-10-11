# Grade skipping approval flow

Super Admins configure the flow under **Students > Skipping Grade > Approval Flow**.

The default is **Current Flow**, which keeps Central Office approval as before. Each request saves its flow when it is created. Changing the system setting affects future requests; existing drafts, submitted requests, and completed requests keep their saved flow.

## Configure the step-by-step flow

1. Select the campus to configure. You can keep Current Flow selected while preparing reviewers or enable Step-by-step Flow for new requests when the selected campus and Central approval steps are ready.
2. Add campus approval steps in the required order, such as KH Coordinator followed by VSP. Add one or more reviewers to each step. Each reviewer needs an active account, an explicit assignment to the selected campus in User / Access Management, and access to Students > Skipping Grade. Global campus access does not count as a campus assignment.
3. Add any Central Office reviewers and select the final Vice President. These users must be assigned to Central (campus `CEN`; the names `Central` and `Central Office` are also supported). The Vice President always approves last. The dropdowns, configuration validation, and pending reviewer reassignment enforce these campus assignments.
4. Save the corresponding VP name, signature, and stamp in Approval Settings.
5. With the selected campus reviewers, Central approval steps, final VP, and signer details configured, select Step-by-step Flow and save. Other campuses do not need to be configured first; their requests require campus reviewers before submission.

Reviewer lists can be extended, reduced, or reordered. Central reviewers are shared across campuses; campus reviewer lists are configured separately. Each save includes the selected campus, the shared central reviewers, and the system flow setting.

Saving validates only the selected campus and shared Central approval steps. Unconfigured campuses or invalid reviewers in another campus do not block this save. The flow mode remains a system-wide setting: all newly created requests keep the selected mode, while previously created requests retain their saved mode. Submission validates the requesting campus's saved reviewers and grade coverage; an unconfigured campus cannot submit a step-by-step request until its flow is configured.

Every reviewer row, including rows added with Add Reviewer to This Step, has its own Position / Role selector listing the active system positions. Reviewers in the same step can have different roles and grade assignments. Existing configurations inherit their saved step title for reviewers without an individual role. Submission freezes each member's role; decision history records the acting member's role, and reassignment retains the replaced member's saved role and grades. Add Approval Step creates a subsequent decision in the sequence; Add Reviewer to This Step adds an alternative eligible reviewer within the same step. Reviewer eligibility checks permissions at the assigned campus, rather than the campus selected in the reviewer's last session; reviewers can be configured before their first login.

Each campus and central reviewer can have **All Grades** or **Assigned Grades** access. Assignments use the student's **current grade**, rather than the destination grade. Use From Grade / To Grade and Apply Range to select a range such as N–K3, N–Grade 4, or Grades 5–12, or select individual grades. All Grades includes future grades; assigned grades retain the specific grade IDs selected. A step can have overlapping assignments; one eligible reviewer's approval completes the step. A rejection from any eligible reviewer ends the workflow.

Every step needs a reviewer covering the current grade before submission. Submission saves only the eligible members and their grade assignments. Reviewers see those requests in My Approvals. Their access to the Requests tab, private documents, printouts, and exports follows the same saved assignments. Super Admin retains access to all requests. A reviewer with request creation permission can also access their own requests. Existing flow permissions continue to apply to legacy requests.

A campus can have several request creators. Assign each staff member the campus and Create / Submit permissions in User / Access Management. There is no one-creator-per-campus limit. The duplicate-request check prevents two simultaneous draft or pending requests for the same enrollment.

Campus staff can create requests only for students enrolled in their explicitly assigned active campuses. The new-request form limits campus and student choices to those assignments, and the server checks the source enrollment's campus when saving and submitting. An All Campuses access flag or global role does not bypass this restriction; Super Admin retains full access. Existing permissions for viewing requests and Central Office editing remain available.

Module permissions use the user's active campus. If no active campus is selected, Grade Skipping uses the primary active campus assignment (then the first active assignment). This lets new campus accounts open the menu and authorized reviewers use My Approvals before selecting a campus, without granting missing permissions or expanding their request or review assignments.

## Review requests

Campus users create a draft, upload supporting documents, and submit the parent-signed request. Submission saves the current reviewer sequence, reviewer identities and photos, and a private copy of the configured VP signature and stamp.

The request form shows Campus Committee Names only for Current Flow. New requests use the current system setting; existing drafts use their saved flow even after the system setting changes. Step-by-step reviewers come from Approval Flow and are captured at submission.

Supporting Documents can be attached directly when creating or editing a draft. Saving uploads PDF, JPG, PNG, or WEBP files to private storage (5 MB per file, 10 per save, 20 per request). Existing attachments remain available when more files are added. A failed save rolls back the request change and removes new files. Request creators can attach files during creation using Create permission; later uploads and draft edits require existing edit permission. Submitted requests do not accept new supporting documents.

The Supporting Documents uploader follows Student Enrollment's dashed drop box and file cards. Browse, drag and drop, or focus the box and paste copied files or screenshots. Saved attachments and files ready to upload appear inside the box; queued files can be removed before saving. Repeated selections are deduplicated, and invalid types, oversized files, and file count limits display a message. The same uploader is available when adding documents from the draft detail page.

Document cards highlight on hover or keyboard focus. Clicking anywhere on a card opens the saved attachment or queued file preview in a new tab. Parent-signed checkboxes and remove buttons remain separate controls. Queued previews use PDF/image MIME types and are released when files are removed or the page closes.

The draft detail page combines Supporting Documents and Submission. Mark any saved or queued file cards as Parent-signed Request. Save Documents saves attachments and these labels while keeping the request as a draft; it does not require the signed date or confirmation. Submit for Approval (Submit to Central Office for Current Flow) uploads queued documents, saves the labels, and submits in one transaction after validating the parent-signed date and confirmation. Upload or submission failure preserves the draft and its previous documents and removes new files. Adding documents or changing their labels requires draft edit permission; staff with Submit permission alone can submit already saved documents. Stale document counts require refreshing before saving so labels cannot shift to a different attachment. Existing separately uploaded signed requests remain accessible.

Assigned users use **My Approvals** to find requests awaiting their decision, their past decisions, or all assigned requests. Request details show the approval timeline and private attachments. Only an eligible reviewer in the current step can approve or reject. Intermediate approval forwards the request; rejection ends the workflow. Only final approval changes enrollment and creates the approval number and signed approval form. The actual reviewer who makes the decision is recorded in the approval history.

The Request View filter offers My Approval and My Rejected to reviewers, and My Requested to requesters. Users with both roles see all three options. My Approval defaults to all assigned requests and past decisions; Awaiting My Approval shows only the current actionable step. My Rejected includes only requests personally rejected by the user. My Requested includes records the user created or submitted, including drafts and requests using the Current Flow. Campus Requests remains available and includes requests from explicitly assigned active campuses with reviewer grade restrictions; eligible campus records can be opened without changing who can approve them. Saved reviewer assignments and decisions remain visible when future settings change. Current Flow approval decisions also appear in personal review history. Changing Request View or Status applies the filter immediately; changing Request View resets Status to All except My Rejected, which uses Rejected by Me.

Central reviewers appear individually in the request list, My Approvals, and the detailed timeline, with each person's saved photo and role. Multiple Central reviewers assigned to one step still require one approval. Only the acting reviewer shows the decision, date, and comments. Other members show Step completed after approval or Step closed after rejection. Historical single-reviewer steps remain supported.

The detailed timeline starts with Requester, showing the request creator's saved name, photo, and submission date and time. If a different staff member submits the request, their name appears as Submitted by. New submissions save both identities so later account changes do not change this history. Existing submissions without these snapshots use the recorded creator and submitter accounts. Campus labels are omitted from the timeline.

The Approvals column in the request list and My Approvals starts with the requester photo and submission date, followed by reviewers in one row with their completed review dates below their photos. Hovering over a photo shows the person's name, role, status, and decision or submission time.

The personal approval list scales its text, student photos, reviewer profiles, and spacing with the available page width. Below 1050 pixels of available width, each request uses a stacked layout with Current Grade and Skipped Grade side by side. Reviewer profiles remain in one row and can scroll within their section when needed. Filters also adapt to the available width, including sidebar changes.

The detailed timeline scales photos, text, and spacing to the workflow card's width. Reviewers share an equal-width horizontal row when there is enough room, and switch to a vertical timeline on narrow screens or when the reviewer count makes the row too crowded. Resizing the browser or expanding/collapsing navigation recalculates the layout; names and badges wrap inside their available space.

Submitted step-by-step requests cannot be edited. The existing flow retains its current editing rules. Both flows use the existing request list, reports, statistics, and exports.

The request list has separate Print, Excel, and PDF menus for the student list and statistics. Each action uses the current filters across all pages. Print List and Print Statistics open the browser's print preview automatically after report fonts and images are ready. Share Statistics opens the existing A4 landscape image popup directly from the list, with Telegram, WhatsApp, Facebook Messenger, Copy Image, and Download A4 Image options. Statistics always include only approved requests, while student lists follow the selected status filter.

## Change staff

Update Approval Flow for future submissions. Submitted requests retain their saved reviewers; completed decisions retain their original reviewer and actor identities, dates, comments, and photos.

For a pending request, a Super Admin can open **Reassign an Unfinished Reviewer** in its approval timeline. Select the individual reviewer to replace and provide a reason. The replacement inherits that member's saved grade assignment. Only awaiting or future steps can be reassigned. Change history records the previous reviewer, replacement, administrator, date, and reason. An already opened review form must be refreshed after reassignment.

If replacing the final Vice President on a pending request, update Approval Settings first. Final-step reassignment saves those current signer details and assets for that request. Approved requests retain their original signature and stamp.

## Deployment

Grade skipping uses the existing in-app notification bell. Submission notifies only eligible reviewers in the current saved step; approving a step closes its unread alerts and notifies the next step. One eligible decision clears the pending count for all reviewers in that step. Rejection stops later steps and notifies the creator and actual submitter; VP final approval also notifies both. Notifications retain their history and open the protected request form. Opening an alert marks it read without changing the pending approval count.

Students, Grade Skipping, and My Approvals show the number of requests currently awaiting the signed-in user's decision, using saved assignments and current permissions. Counts refresh with the existing notification poll every 10 seconds. The mobile notification bell opens notification history. Current Flow retains its existing approval permissions and also receives pending alerts. Pending-step reassignment closes old alerts and notifies eligible replacements; future steps receive alerts only when they become current.

Run `php artisan grade-skipping:notify-pending` once after deploying notifications to create alerts for requests already awaiting approval. It can be rerun without duplicating or reopening existing alerts. No additional database migration is needed for notifications.

Apply `database/migrations/2026_10_11_000001_add_configurable_skipping_grade_approval_flow.php` and `database/migrations/2026_10_11_000002_add_grade_scoped_skipping_reviewers.php`, then run `npm run build`. The migrations do not assign reviewers or enable the new flow. Previously submitted requests keep their saved single-reviewer steps and approval history.
