<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Tourbin local setup

The application uses MySQL. On Ubuntu, install the required server and PHP driver:

```bash
sudo apt update
sudo apt install mysql-server php8.5-mysql
sudo mysql
```

Then run these statements in the MySQL prompt (the credentials match the local `.env`):

```sql
CREATE DATABASE IF NOT EXISTS geyt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'geyt'@'localhost' IDENTIFIED BY 'GeytLocal_2026!';
ALTER USER 'geyt'@'localhost' IDENTIFIED BY 'GeytLocal_2026!';
GRANT ALL PRIVILEGES ON geyt.* TO 'geyt'@'localhost';
CREATE USER IF NOT EXISTS 'geyt'@'127.0.0.1' IDENTIFIED BY 'GeytLocal_2026!';
ALTER USER 'geyt'@'127.0.0.1' IDENTIFIED BY 'GeytLocal_2026!';
GRANT ALL PRIVILEGES ON geyt.* TO 'geyt'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

Create the MySQL schema and import the existing SQLite application data without deleting the source file:

```bash
php artisan migrate
php artisan db:import-sqlite
php artisan prices:crawl
php artisan serve
```

Every successful crawl (including an unavailable result with price zero) creates a price-history snapshot. The tour page displays up to 30 recent snapshots per provider. Ratings are only labelled as user ratings when the provider returns a review score; hotel classification is stored and displayed separately as hotel stars.

Provider-page content is checked during price crawls and by the daily `content:crawl` command. Relevant headings are deduplicated and compiled into a source-attributed travel guide without republishing source paragraphs. Ensure Laravel's scheduler is running in production:

```bash
php artisan schedule:work
php artisan queue:work --timeout=1800
```

## Tour discovery and one-click provisioning

Admins can refresh at least 100 demand-ranked tour suggestions from **Admin → Tour suggestions**. Discovery combines Google Trends, searches with no result on the site, and the configured destination catalog. Creating a suggestion queues SEO page creation, attaches 4–10 configured providers, crawls their structured price/rating data, compiles provider content, and then publishes the tour.

**Admin → Synchronization** centralizes tour discovery, price/rating refresh, content refresh, and full synchronization with run history. Discovery runs daily at 01:30, prices hourly, and provider content daily at 02:30. Keep both the scheduler and queue worker above running in production. Provider definitions and the Google Trends geography/feed can be customized in `config/crawler.php` or with `GOOGLE_TRENDS_GEO`, `GOOGLE_TRENDS_FEED_URL`, and `TOUR_SUGGESTIONS_LIMIT`.

## Price-drop SMS alerts

Visitors can subscribe from a tour page at its current minimum price. After each complete crawl, an SMS is sent only when the new tour minimum is lower than the subscriber's saved threshold. The threshold is then lowered to prevent duplicate messages. Phone numbers and unsubscribe tokens are encrypted at rest.

The default `SMS_DRIVER=log` writes development messages to `storage/logs/laravel.log`. To send through Kavenegar, configure:

```dotenv
SMS_DRIVER=kavenegar
KAVENEGAR_API_KEY=your-api-key
KAVENEGAR_SENDER=
```

Alternatively, set `SMS_DRIVER=webhook`, `SMS_WEBHOOK_URL`, and optionally `SMS_WEBHOOK_TOKEN`. The webhook receives JSON with `to` and `message` fields.

## Agency click billing

Purchase buttons use the internal `/go/{source}` route. Each request is recorded before redirecting to the provider. Agencies have a balance and a configurable cost per click, and every charged click creates an immutable ledger entry. Admins can configure click cost and add or subtract credit from **Admin → Agencies & Credit**. When an agency cannot afford its next click, its purchase button is disabled until credit is added. Existing agencies start with zero cost per click, so configure a cost before billing begins.

## Admin and agency analytics dashboard

`/admin/dashboard` is shared by administrators and agency users. It reports tour-page views, successful outbound purchase clicks, click conversion rate, and click spend per tour for selectable time ranges. Administrators can view all agencies or filter one agency; agency users are restricted to their own tours and metrics and receive HTTP 403 for tour, source, billing, and account-management routes. Create or update an agency login from **Admin → Agencies & Credit → Dashboard access**.

The administrator dashboard also lists potential tour keywords: submitted searches with zero results, grouped by normalized Persian spelling and whitespace. It shows search frequency, approximate unique visitors, and the most recent search within the selected dashboard period. Autocomplete requests are intentionally excluded so partially typed phrases do not pollute demand data.

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
