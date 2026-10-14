# Supporting Document Archive

## Deployment

Deploy the application files, enable PHP ZIP for the web handler and CLI worker, and run:

```sh
php artisan migrate --force
php artisan config:clear
php artisan queue:work document-archives --queue=document-archives --timeout=1800 --tries=1
```

Run that worker persistently using Supervisor/systemd or the hosting service manager. The dedicated database queue has a 1,860-second reservation, longer than its 1,800-second job timeout; normal application queues are unchanged. Standard Laravel `jobs` and `failed_jobs` tables must exist. Restart the worker after code updates. Use your normal `config:cache` deployment step after configuration changes if applicable.

On Permission, enable Students → Grade Skipping → Supporting Document Archive, then the separate Backup, Delete, and Restore actions for authorized staff. Only Super Admin receives these permissions automatically; existing staff access is not expanded.

For restore uploads, configure the web PHP handler with `upload_max_filesize = 220M`, `post_max_size = 300M`, and the site's Nginx request-body limit with `client_max_body_size 300m` (or the equivalent Apache/proxy setting). Keep any existing higher limits. Ordinary supporting-document uploads remain validated at 25 MB per file. A backup ZIP contains up to 200 MB of documents plus manifests, so its restore upload needs a larger allowance. Changing CLI PHP settings does not change PHP-FPM. Reload the relevant services or wait for supported `.user.ini` overrides to refresh.

Ensure private local storage is writable by both web and worker accounts, with enough free space for temporary ZIPs and restore staging. Never expose archive folders through public storage links. WinSCP file uploads alone cannot run migrations, start the worker, or reload Nginx/PHP-FPM; those steps require the server administrator's shell/service access.

## Storage

New documents use private storage:

```text
storage/app/private/grade-skipping/attachments/
  2026-2027/
    Request-123/     # Before final approval
    SG2627-123/      # After the organization job runs
```

The year is the requested/target academic year. Each Approval Number represents a student's approved request. Stored file names are randomized to prevent collisions; original names remain on requests and in backups. Files are copied and checksummed, their new database pointers committed, then old copies removed. Failed copies retain originals. Legacy request-ID folders and signed-request paths continue working.

To organize existing approved requests, queue them with:

```sh
php artisan grade-skipping:organize-documents --user=AUTHORIZED_USER_ID
```

The user must be active with backup permission. Run the archive worker to process the jobs. Organization is repeatable and skips documents referenced by active archives, preserving their manifests. Failed jobs can be retried after correcting the cause.

## Backup and verification

Open Grade Skipping → Supporting Document Archive. Choose a requested academic year and optionally a campus, then Create Backup. Preparation and ZIP generation run in the background. Only completed approved or rejected requests are included. Rejected requests without Approval Numbers use `Request-ID` folders. Draft and awaiting-approval requests are excluded.

Parts contain at most 200 MB of source files or 1,000 documents. Each ZIP includes:

- `ACADEMIC-YEAR/APPROVAL-NUMBER/FILE-ID-original-name.ext` documents;
- `students.csv`: UTF-8 with BOM, Khmer/English names, student ID, campus, Approval Number and request ID;
- `documents.csv`: original names, paths, sizes, SHA-256 checksums and request/student IDs;
- `manifest.json`: archive identity, part number, student details and document checksums;
- `README.txt`: retention and restore instructions.

Document bytes are streamed for hashing instead of loaded into memory. Every ZIP document is verified before downloading; the entire ZIP has its own stored checksum. Verify / Retry Backup rechecks existing parts and retries unfinished creation. If an existing ZIP is corrupt and cannot be verified, create a new backup from the unchanged online files.

Download every part unchanged. A download-start record cannot prove the browser finished saving the bytes: the user must separately confirm every part is complete and safely stored outside the school server. Include the application database in normal database backups; it contains archive identity, document metadata and approval history needed for restore.

## Retention and deletion

The newest regular academic year marked Started defines the current year. That year and the previous academic year are protected. Future planning years do not advance the retention cutoff. Without a valid Started year, deletion is disabled. With current year 2026–2027, 2025–2026 and newer are protected; 2024–2025 and older may be archived.

Archive annually to consistently retain only two years. Running the manual process every three years temporarily retains more than two years. There is no automatic deletion schedule.

Deletion requires separate permission, every ZIP part verified and downloaded, an external-backup confirmation checkbox, and the exact backup reference typed into the form. The worker rechecks authorization, retention, ZIP checksums, source-file checksums and completed request scope. Changed or missing sources stop deletion.

Recoverable archive metadata is committed before live bytes are removed. After all documents are archived, temporary server ZIPs are also removed to release disk space; restore then requires the external copies. Student/request/enrollment/approval records, approval-form signatures/stamps, original file names, checksums and audit history are retained. Request pages show Archived with the backup reference and date. Authorized attempts to open archived files return HTTP 410.

## Restore and recovery

Open the original archive and upload each original ZIP part using Restore Part. Restore runs in the background. The ZIP checksum must match the retained manifest before files are read. Only expected entries are streamed to recorded private paths; ZIP paths are never blindly extracted. Changed ZIPs and conflicting existing files are rejected.

Files are staged, checksummed, then renamed into place, with metadata updated under row locks. Existing identical files can be reused after interruption. Successfully restored files/parts remain available if another fails; re-upload the failed part after correcting the cause. Status remains Partially Restored until all deleted files are restored.

Archive events record backup creation, verification, download starts, external-backup confirmation, deletion, restore and failures, with acting users and timestamps. Queued actions recheck the user's active status and current permission before execution.

No documents are deleted during deployment or migration. Backups and deletions require explicit actions on the archive page. Retain archive database tables: rollback refuses to silently discard existing archive history needed for restoration.
