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

Central Grade Skipping Settings accepts a digital signature and school stamp of
up to 2 MB **each**. Both files are sent in one multipart request. An HTTP 413
response means the web server or PHP rejected the request before the application's
per-file validation could run.

For Nginx, set `client_max_body_size 8m;` in the school site's active `server`
block (including the HTTPS server block). Nginx defaults to 1 MB for the entire
request. Check for a smaller override in a matching `location` block.
Validate the configuration with `sudo nginx -t`, then reload with
`sudo systemctl reload nginx`.

In the **PHP-FPM configuration used by the site**, set:

```ini
upload_max_filesize = 2M
post_max_size = 8M
```

Reload the site's PHP-FPM service after changing its configuration. The CLI
`php --ini` output may point to a different configuration from PHP-FPM.
The application still enforces 2 MB per signature/stamp image. These settings
only provide sufficient space for the complete request; a Git pull does not
update the server's Nginx or PHP-FPM configuration.

References: [Nginx request body limit](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size),
[PHP upload and POST limits](https://www.php.net/manual/en/ini.core.php#ini.post-max-size).

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

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
