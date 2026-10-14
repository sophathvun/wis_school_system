# Phone notifications and Home Screen badges

The Home Screen app badge counts unread notifications plus unread chat messages. Opening the app refreshes the count and clears it when the total is zero. New admin notifications, chat messages, and new Grade Skipping reviewer/decision alerts send visible Web Push notifications; Grade Skipping alerts send only after their database transaction commits and do not repeat for existing alerts.

## Server deployment

Deploy the application changes, including `public/sw.js`, and the Vite build. In the server console, from `/var/www/wis_school_system`, run:

```sh
php artisan migrate --force
php artisan route:clear
php artisan view:clear
php artisan webpush:setup
php artisan config:cache
php artisan webpush:setup --check
```

The setup command generates missing VAPID keys directly in the server's environment file. It does not print private keys or replace existing keys. Retain these keys across deployments; replacing them invalidates existing device subscriptions. If keys are incomplete or invalid, correct the environment configuration instead of generating a replacement silently. `WEBPUSH_VAPID_SUBJECT` must be a valid HTTPS URL or `mailto:` contact. The default uses `APP_URL`.

The site must use HTTPS, and the server must allow outbound HTTPS to the browser push services, including Apple's `*.push.apple.com`. The check command validates configuration and storage; it does not prove external delivery. Failed deliveries are recorded in the Laravel log without printing VAPID secrets.

## Users

After an Android installation, or the first signed-in launch from an iPhone/Android Home Screen icon, WIS School offers **Enable notifications?** automatically when supported and server push is configured. Select **Yes, enable!**, then **Allow** in the phone's permission prompt. The app confirms success only after the device subscription is saved. Selecting **Later** postpones the automatic offer for seven days on that browser/device and account; the profile and Notifications page buttons remain available. Users who already allowed notifications are registered silently, and users who blocked permission are not repeatedly prompted.

1. Install WIS School on Android, or on iPhone use Safari → Share → Add to Home Screen.
2. Open WIS School from its Home Screen icon and sign in.
3. Open the profile menu or Notifications page and select **Enable Phone Notifications**. Choose **Allow** when the phone asks.
4. On iPhone, also allow **Badges** under Settings → Notifications → WIS School. iOS/iPadOS 16.4 or later is required. If already blocked, enable notifications in phone/browser settings and retry the button.

Each device needs permission and its own subscription. iPhone supports a numeric badge; Android normally shows a dot for active notifications and the launcher controls any numeric display. The browser's Share button itself does not receive a badge. Phone Focus/Do Not Disturb and per-app settings can suppress alerts or badges.

## Delivery check

The Notifications page includes **Test Phone Notifications**. This registers the current device and asks the server to send a visible test push to that device only. It reports whether the push service accepted the request, rejected it (including the HTTP status), or reported an expired subscription. Acceptance by the push service is not proof that the phone displayed the alert. Test requests are limited to three per minute per user and do not add unread messages or notifications. Therefore an iPhone with zero unread items can show the test alert without an icon count.

If Android shows no system notification, investigate delivery first. A shortcut badge depends on an active notification reaching the device; enabling icon badges alone cannot create one. If the test is accepted but no alert arrives, check the browser and installed app notification permissions, background/battery restrictions, and whether the icon is an installed app or just a browser shortcut. If a visible alert arrives but the icon has no dot, check the launcher's badge settings. Existing device subscriptions using an older server key are renewed automatically when enabling notifications or reopening the signed-in app.

Use a test recipient who has enabled notifications on their installed app. Send a notification or chat message to that recipient, then check the phone with the app closed. Confirm the alert opens the correct page and, on iPhone, the combined unread number appears on the app icon. Read the items, reopen the app, and confirm the number decreases or clears. Repeat on Android, expecting a dot where numeric badges are unavailable. No command sends a test message automatically.

Platform references: [WebKit Home Screen badging](https://webkit.org/blog/14112/badging-for-home-screen-web-apps/), [Chrome badging capabilities](https://developer.chrome.com/docs/capabilities/web-apis/badging-api).
