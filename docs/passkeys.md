# Face or passkey login

Users can add one or more passkeys from **My Profile → Edit Profile → Logon
Information**. Enrollment requires the user's current password. After a passkey
is added, the login page can use Windows Hello, Face ID, Android biometrics, a
device PIN, or a compatible security key. Password login remains available.

The authenticator verifies the user on the device. The school server stores the
WebAuthn public credential and sign-in metadata; it does not receive a face scan,
fingerprint, device PIN, or HRM staff photo comparison.

## Deployment

Install locked Composer dependencies, apply the migration, and build assets:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
```

WebAuthn requires HTTPS on a deployed domain. Keep one canonical login hostname
because credentials are scoped to the relying-party domain. The request hostname
and origin are used automatically, or production can set them explicitly:

```dotenv
PASSKEY_RP_NAME="Western International School"
PASSKEY_RP_ID=registrarsis.site
PASSKEY_ORIGIN=https://registrarsis.site
PASSKEY_TIMEOUT=60000
```

If these values change after users enroll, their existing passkeys may stop
matching the site. Preserve the application's `APP_KEY` across deployments
because stored credential records are encrypted with it.
