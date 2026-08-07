# Step 6 — Promotion and Transfer Management

Status: Implemented

## Workflow

Step 6 adds a dedicated workflow page for controlled academic movement:

- Promotion creates a new enrollment in the target academic year, grade, class,
  campus, and Group.
- The source enrollment is completed with a promotion reason.
- Transfer updates the current enrollment campus, grade, class, and Group.
- Class Promotion processes every active student in a selected source campus,
  academic year, grade, and class in one transaction.
- Class Promotion can keep the same campus or target another campus.
- Each promoted student receives an individual target enrollment and history
  record, while the source enrollment is completed.
- Duplicate target-year enrollments are blocked.
- Every action writes both a workflow record and an enrollment history record.
- Grade 12 students are handled through the dedicated Graduation page instead
  of normal promotion. Graduation closes the final enrollment and optionally
  marks the student as Alumni.

The user interface uses Group values such as `M`, `A`, and `III`, while the
existing `session_id` storage remains compatible with the current database.

## Test URL

```text
http://127.0.0.1:8002/students/promotion
```

The page is accessed from Students → Promotion / Transfer. It provides action
history, student search, promotion, transfer, target assignment fields,
effective date, reason, and notes. The old workflow URL redirects to this
canonical Student menu page.

Graduation is managed at:

```text
http://127.0.0.1:8002/students/graduation
```

Graduation supports filtering by Academic Year, Campus, and Class, plus
graduation date, certificate number, Alumni status, and notes.

## Database

Added `tb_student_enrollment_workflow` with source/target assignment snapshots,
action type, effective date, reason, notes, and changed user.

Migration:

```text
2026_08_03_000013_create_enrollment_workflow_actions
```

## Verification

- Migration completed successfully.
- Workflow page returned HTTP 200.
- Existing Pest tests passed: 2/2.
- PHP lint passed for the workflow service and controller.
