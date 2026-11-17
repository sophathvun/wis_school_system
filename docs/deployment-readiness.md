# Deployment readiness

## Keep local verification separate from production

Performance measurements on a local machine do not establish live-server response
times. Use the same code and migrated database on a separate server restore, then
verify the workflows with production PHP, storage, and HTTPS configuration.

Tests use SQLite in memory. `composer test` runs each PHP test
file in a separate process with isolated temporary disks so large suites do not
accumulate memory or overwrite another process's fixture files. Read both the
exit status and the JSON test summary; skipped tests are not passed tests.

## Prepare code and configuration

- Use the committed application code and lock files with compiled Vite assets.
- Install locked dependencies with `composer install --no-dev --optimize-autoloader`.
- Confirm platform requirements with `composer check-platform-reqs --no-dev` and
  check current vulnerability advisories with `composer audit --locked`.
- Use production configuration with `APP_ENV=production`, `APP_DEBUG=false`, the
  real HTTPS `APP_URL`, and correctly configured public verification and passkey
  domains. Keep credentials and encryption keys private. Check encrypted data
  compatibility before choosing the production application key.
- Point the web-server document root to `public`. Do not expose the project root,
  private backups, import source files, review files, or diagnostics.
- The repository's existing Dockerfile/Compose placeholders are not a validated
  production deployment for this Laravel application.

## Database and storage

Restore both the database and its matching public/private uploaded files. Review
record counts, certificate numbers and issue dates, document downloads, and file
permissions. Keep original issued numbers unchanged. Do not copy temporary testing
disks, development logs, local diagnostic scripts, source imports, and old database
backups into publicly accessible storage.

Run only the pending migrations required by the deployed code. Avoid automatic
seeders or database resets when restoring the migrated dataset. Confirm public
storage linking and private-file delivery separately.

## Runtime and performance

Enable OPcache in the web PHP handler. CLI OPcache can differ from the web worker;
measure the actual web handler rather than assuming CLI timings are browser timings.
Enable compression and long-lived caching for hashed frontend assets in the server
configuration. Keep HTML, private documents, and permission-dependent JSON out of
public shared caches.

After configuration and files are final, run:

```bash
php artisan optimize
```

The local development environment may remain uncached so code/configuration changes
are visible. During production deploys, refresh caches after configuration changes.

Confirm upload limits in the active web-server and web PHP configuration. See
the README upload-limit section; request limits must accommodate the entire file
batch, not just one file. Confirm GD/WebP, Ghostscript, and any configured photo
background-removal dependencies on the server.

Web Push key generation requires a working OpenSSL configuration. The isolated
Windows test runner uses the installed PHP distribution's `extras/ssl/openssl.cnf`
when `OPENSSL_CONF` is unset. Configure OpenSSL explicitly for the actual server
and command-line environment before generating production notification keys.

Pause scheduled jobs and workers during database/file replacement. Restart the
required queue workers and scheduler after verification. Prevent old queued test
notifications or tasks from running against the restored data.

## Final acceptance

Check sign-in, roles and campus permissions, student enrollment, family selection,
photos, documents, National Exam records and certificates, WIS certificates,
attendance, and report printing. Confirm HTTPS-specific passkey and QR verification
behavior on the real domains. Keep a tested rollback copy until staff accept the
restored system. Automated tests supplement this verification; they do not replace
it or prove every production workflow.
