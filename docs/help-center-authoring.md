# Help Center authoring

The Help Center contains general onboarding, G9 and G12 National Exam guides, Attendance guides, Administrator guides, and Students guides. Students has an overview and a guide for each of the 15 current submenu entries, including Import / Export Data. Other modules are not yet documented. The Help Center itself uses no AI service or external API; the G12 OCR guide describes the module's existing Google Cloud Vision integration.

- Article metadata and required G9 permissions: `app/Support/HelpCenter.php`.
- Article content: `resources/help/{slug}.md`, UTF-8 Markdown.
- Authenticated routes: `help.index` and `help.show`.
- Content is maintained in source control; there is no article editor or publication workflow yet.

To add an article, add a fixed slug and metadata entry, write the matching Markdown file, and verify its instructions against current screens and code. Use section headings, short steps, prerequisites, expected behavior, and practical troubleshooting. Do not include private records, real student screenshots, credentials, or private import paths. Add annotated screenshots only after reviewing and redacting their contents.

Use only existing route names in metadata. Category `g12` uses G12 permission checks and route names; category `g9` uses G9 checks and routes; `attendance` uses Attendance permissions and routes. Use the specific report-tab permission for tab-specific guides. The Open page action opens the module without carrying student identifiers or assuming the user's current year. Articles are filtered using existing permission checks; help access does not grant module access.

Administrator articles use full route names with no prefix. Users, Roles, and Dashboard Templates guides follow the current super-administrator-only controller checks. Other Administrator guides require the main Administrator menu and the relevant grant; the backup guide also recognizes the controller's `settings.manage` fallback. Keep these checks aligned with the actual tools as access rules evolve.

Students articles also use full route names. Enrollment, Summer School, Families, Placement Tests, ID Cards, Grade Skipping, and Scholarship use their existing permission helpers; other workspaces require Students access and the specific workspace grant. Withdrawal currently uses Students access. Keep these checks aligned with the actual controllers, including campus context and any future submenu permissions. Academic workflows have distinct consequences: promotion, transfer, graduation, withdrawal, re-entry, and grade skipping must retain separate instructions.

After changing a workflow, update its guide in the same change. Run `artisan test tests/Feature/HelpCenterTest.php` and the relevant module tests. Review desktop, mobile, dark mode, search, and print after UI changes. Khmer text uses the internal Khmer OS Siemreap font.
