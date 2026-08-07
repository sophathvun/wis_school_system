# Step 4 — Student and Family Management

Status: Implemented

## Implemented

- Added normalized family records in `tb_family`.
- Added family members in `tb_family_member` with explicit relationship types: Mother, Father, and Guardian.
- Added the family-to-student relationship in `tb_family_student`.
- Added student contacts in `tb_student_contact`.
- Added student addresses in `tb_student_address`.
- Added student documents in `tb_student_document`.
- Backfilled family records and family-student links from existing `tb_student.family_number` values.
- Added Eloquent relationships for students, families, family members, contacts, addresses, and documents.
- Added `FamilyService` to keep normalized family data synchronized during student enrollment save operations.
- Added Family Management page using the existing layout, sidebar, Tabler styling, AJAX conventions, and pagination helper.
- Added family search, pagination, create, update, view API, and safe delete behavior.

## Compatibility

The existing `tb_student.family_number` field remains in place. It continues to support the current enrollment form while `tb_family` becomes the normalized source for future parent, guardian, and portal functionality.

Existing student enrollment routes and UI were preserved.

The Create Student Enrollment form now accepts optional Mother, Father, and Guardian information. These records are saved together with the student and enrollment transaction, so staff can complete the family information once during enrollment.

Each family member supports English and Khmer nationality values, linked to the
existing Country master through `tb_family_member.nationality_country_id`.
Country records now store the corresponding `nationality_name_en`,
`nationality_name_kh`, and `flag_path`, so staff can select a searchable
nationality with its flag during enrollment. For example, Country = Cambodia
and Nationality = Cambodian.

The separate nationality prototype table remains only as a compatibility
artifact from the earlier implementation; it is not used by enrollment and is
not exposed in the Settings sidebar. Country/Locations is the single place to
add or edit nationality labels and flags.

Campus references continue to use the existing School Profile records in `tb_school_info`; no duplicate campus entity was introduced.

## Migration

```text
2026_08_03_000003_create_student_family_management_tables
```

## Family API

```text
GET    /families
GET    /families/fetch
GET    /families/{family}
POST   /families/save
DELETE /families/{family}
GET    /families/{family}/members
POST   /families/{family}/members/save
DELETE /families/{family}/members/{member}
```

Family members are validated as `mother`, `father`, or `guardian`. A family may have multiple guardians and can store separate mother and father records.

Families with linked students cannot be deleted. This protects student history and requires an explicit unlink or archival workflow later.

## Document storage note

Student documents currently contain a nullable `file_id` compatibility field without a foreign key because the platform file-storage table is not implemented yet. The foreign key will be added during the file-storage phase.

## Verification

- Migration completed successfully.
- Existing Pest tests passed: 2/2.
- Vite production build passed.
- Family frontend entrypoint is included in `vite.config.js`.

## Next step

The next implementation phase is **Step 5 — Enrollment and Class Assignment**. It should formalize promotion, transfer, withdrawal, graduation, and class-assignment history on top of the current enrollment data.
