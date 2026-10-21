# Family Management permissions

Families remain global shared records with no campus column. Permissions appear
under **Students > Family Management** (the Families submenu).

| Function | Campus staff | Central/global staff |
| --- | --- | --- |
| View, search, history, print, CSV export | All families, with the corresponding permission | Same |
| Create family and initial contacts | With Create permission | Same |
| Edit family / add, edit, remove contacts | A linked child must currently attend an assigned campus; each function needs its own grant | All families, with the relevant grant |
| Link / unlink children | Only children currently enrolled in an assigned campus, with separate Link / Unlink grants | All students, with those grants |
| Activate / deactivate a shared family | Unavailable | Requires Status permission |
| Delete family | Unavailable | Requires Delete permission and no student links, including historical or legacy links |

Current enrollment means an active enrollment with active status in a started,
non-deleted academic year. Former, withdrawn, transferred and inactive records
do not grant campus editing rights. Central identity follows the existing
enrollment rules: an active central campus assignment (`CEN`, `CENTRAL`,
`CENTRAL OFFICE`), a global user, or an active global role. Super administrators
retain full access.

Edits show the number of linked children and their current campuses, with a
notice that shared changes apply to the whole family. Moving a linked child
requires both Link and Unlink permissions; linking an unlinked child requires
only Link. Family changes update family/student/contact links without changing
any student's enrollment assignment or other children's links.

Family Management edits, contact actions, links/unlinks and family status changes
write `tb_family_history` inside the same transaction as the change. Entries keep
the actor ID/name, timestamp, action and complete before/after snapshots. History
is separately protected, paginated, and remains available after soft deletion.
Existing historical edits are not reconstructed.

Deploy with:

```sh
php artisan migrate --path=database/migrations/2026_10_21_000002_add_family_permissions_and_history.php --force
```

Existing View grants receive Search and History. Legacy Manage grants migrate
to Create, Edit, contact actions, Link and Unlink. Print/Export and central Status
are separate grants; existing Delete grants still require central access.
