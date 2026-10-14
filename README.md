<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Run the school system locally (Windows / Laragon)

1. Start Nginx and MySQL in Laragon (Start All).
2. Open http://127.0.0.1:8002/.

Laragon serves this address directly using
`C:\laragon\etc\nginx\sites-enabled\school-system-8002.conf`.
The source configuration is `scripts/laragon-school-system-8002.conf`.
It persists across Laragon restarts and requires PHP 8.4.1 or newer.
No separate server window is needed.

### Optional standalone PHP server

If Nginx is stopped, double-click `start-site.cmd` and keep its window open.
MySQL must still be running.

Press Ctrl+C in the standalone server window to stop.
The launcher finds PHP in Laragon or PATH and selects the newest installed version
that meets the installed dependencies' PHP 8.4.1 minimum. PHP 8.3 cannot run the
current dependencies. This command serves the existing compiled frontend assets.

From a terminal, run `.\start-site.cmd`.
Use `.\start-site.cmd -Check` to check prerequisites without starting a server,
or `.\start-site.cmd -Port 8003` to use another port. You can specify PHP explicitly
with `.\start-site.cmd -PhpPath "C:\path\to\php.exe"`.

If port 8002 is already in use, check the existing site before starting another
server. For the separate Laragon Nginx URL, select PHP 8.4.25 or newer in Laragon
and restart its services; that URL is http://school_system.test:8080/.

## Server upload limits

Grade Skipping supporting documents allow up to 25 MB **per file**, with up to
10 new documents in one upload. Submission can also include a separate signed
request of up to 25 MB. Central Settings signature/stamp images remain limited
to 2 MB each by the application.

All selected files are sent together in one multipart request. An HTTP 413
response means an upload limit was exceeded in the web server, a proxy, or PHP,
before the controller's per-file validation could run. Files individually below
25 MB can still exceed the combined request limit. For example, 350 KB + 4.95 MB
+ 4.89 MB exceeds the previously documented 8 MB request limit.

For Nginx, set `client_max_body_size 300m;` in the school site's active `server`
block (including the HTTPS server block). Nginx defaults to 1 MB for the entire
request. Check for a smaller override in a matching `location` block.
Validate the configuration with `sudo nginx -t`, then reload with
`sudo systemctl reload nginx`.

In the **PHP-FPM configuration used by the site**, set:

```ini
upload_max_filesize = 25M
post_max_size = 300M
```

Reload the site's PHP-FPM service after changing its configuration. The CLI
`php --ini` output may point to a different configuration from PHP-FPM.
These values provide space for the maximum file batch, a separate signed request,
and multipart fields. Keep any existing higher limits needed by other modules.
The application still enforces each module's own file and count limits. A Git
pull, rebuilding assets, and clearing Laravel caches do not update the server's
Nginx or PHP-FPM configuration.

If the site uses Apache, a hosting panel, or another reverse proxy, update its
equivalent request-body limit as well. Confirm the effective settings in the
site's web PHP handler, rather than relying on the local or server CLI settings.
If using CGI/FastCGI and the host permits `.user.ini` overrides, the PHP values
above can go in `public/.user.ini`; this does not change Nginx/proxy limits and
may take up to five minutes to be picked up. PHP-FPM pool admin settings can
prevent per-directory overrides.

After applying the live configuration, retry the failing three-file upload.
If it still returns 413, inspect the HTTPS virtual host, matching location, and
upstream proxy logs for the remaining lower limit. Do not expose a public
`phpinfo()` page to diagnose this.

References: [Nginx request body limit](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size),
[PHP upload and POST limits](https://www.php.net/manual/en/ini.core.php#ini.post-max-size),
[PHP per-directory overrides](https://www.php.net/manual/en/configuration.file.per-user.php).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Student ID Card template

Student ID Cards use one saved template for all academic years and campuses. Users with **Student ID Card: Edit Template (all years)** permission can select Edit Template above the preview, drag blocks to move them, resize from the corner, or set positions and dimensions in millimetres. Text supports font, point size, color, alignment, and bold. Add Field inserts custom text or automatic student values; Remove Block removes a field. Show QR code controls QR visibility and its SCAN caption. Restore Original Layout and Discard Changes follow the certificate editor workflow. Save Template applies changes to individual and whole-class prints. Printing uses the saved template, including when the browser print shortcut is used while editing.

The card remains 54 × 90 mm. Automatic values come from the selected enrollment, including grade/class, campus, and academic year; student values include English/Khmer names, ID, phone, birth date, and gender. Templates reject unsupported placeholders and styles, and an outdated editor cannot overwrite a newer saved version. The new template permission is assigned to Super Administrator by default and can be granted through existing access management.

The permission page lists **Students → STU. ID CARD (QR)** with View, Generate QR, Print Individual Card, Print Whole Class, Copy Link, and Edit / Save / Discard / Restore Template. Actions require both Students and ID Card viewing permissions, and denied buttons are hidden. QR generation uses an authorized POST request; previewing a card does not generate a missing QR code. Individual and whole-class print routes check their separate permissions. Existing role, department, and user grants are preserved; new actions default to Super Administrator only.

Deployment requires `php artisan migrate --force` (migrations `2026_10_09_000018_create_student_id_card_template.php` and `2026_10_09_000019_add_student_id_card_action_permissions.php`) and `npm run build`.

## Message reactions

The Messages widget and full chat page support heart, thanks, thumbs-up, laugh, surprised, and sad reactions. Right-click a message on computers or tap it on phones to open quick reactions, Reply, and Delete (when permitted). Select an emoji to react; select your current emoji again to remove it. Each conversation member has one reaction per message and can change it. Only reaction counts remain visible on the message; counts and participant names refresh with the existing chat polling. Reacting does not send a new message or push notification.

Deployment requires `php artisan migrate --force` (migration `2026_10_09_000016_create_chat_message_reactions.php`) and `npm run build`. Reactions are restricted to current conversation members and visible, undeleted messages. Existing messages can receive reactions after deployment.

## Message replies

In the Messages widget and full chat page, right-click a message on a computer or tap it on a phone and choose Reply from the popup. Long-press also opens the popup on phones. A composer preview shows the selected message and lets users cancel. Sent text, file/photo, and voice replies show the original sender and a quoted preview; clicking the quote locates the original message when it is in the loaded history. Switching conversations clears the selected reply and popup. Links, attachments, and audio controls retain their normal click behavior.

Replies are restricted to visible messages in the same conversation. Deleted or personally hidden originals display "Message unavailable" without exposing their content. Deployment requires `php artisan migrate --force` (migration `2026_10_09_000017_add_chat_message_replies.php`) and `npm run build`.

## Pasting chat attachments

In the Messages widget and full chat page, paste a copied image, screenshot, or supported file into the composer with Ctrl+V, review the attachment preview, and click Send. Ordinary text paste still works. Messages accept one attachment up to 20 MB; invalid files leave the current attachment intact. Files must be available through the browser clipboard; if a copied file is not exposed by the browser, use the attachment picker. Deployment requires `npm run build`.

In both chat views, Enter sends the current message on computers and Shift+Enter adds a new line. On phones, Enter adds a new line and the Send button sends the message. Enter used to confirm an input-method composition does not send the draft, and holding Enter on computers does not repeatedly submit it.

The composer grows automatically for multiline drafts and wrapped text, keeping the latest message visible when the reader is at the bottom. Long drafts scroll inside the input after reaching a limit of 240 px or 40% of the available message/composer area. Sending the draft returns the input to one line; reading older messages preserves the current scroll position.

Opening a conversation in the Messages widget hides the Messages heading, tabs, search, and New Group Chat controls. The conversation photo/name and back button stay at the top; Back restores the chat/people list and its controls. On phones the message area fills the remaining screen height. Both chat views show a bottom-right down-arrow while reading older messages; clicking it returns to the latest message.

Group headers show the group name without a member subtitle. In both chat views, Group options > Group Members lists every participant, including the current user, with their photo, Owner/Admin/Member role, and online/offline status. All group members can view the roster; group-management actions remain restricted to authorized managers.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
