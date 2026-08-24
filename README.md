<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Geyt local setup

The application uses MySQL. On Ubuntu, install the required server and PHP driver:

```bash
sudo apt update
sudo apt install mysql-server php8.5-mysql ffmpeg
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

Provider-page content is checked during price crawls and by the daily `content:crawl` command. Relevant headings are deduplicated and compiled into a source-attributed travel guide without republishing source paragraphs. On a server with persistent processes, keep Laravel's scheduler and the default queue worker running:

```bash
php artisan schedule:work
php artisan queue:work --timeout=1800
```

On shared hosting, synchronization-center actions use the dedicated database queue named `sync`. Add this single cron entry, replacing the PHP binary and project path with the values from the hosting control panel:

```cron
*/5 * * * * cd /home/USER/geyt && /usr/local/bin/php artisan sync:work >> storage/logs/sync-cron.log 2>&1
```

The command starts at most five minutes after an administrator queues an action, automatically queues the daily price refresh after `DAILY_PRICE_REFRESH_AT` (03:00 by default), processes the `sync` queue until it is empty, and then exits. Keep `QUEUE_CONNECTION=database` and run `php artisan migrate --force` during deployment so the queue tables exist. No separate scheduler cron is required on shared hosting.

The image crawler uses PHP GD when available and otherwise uses `ffmpeg` to upscale and center-crop the largest undersized Wikimedia image. Keep one of these image processors installed on every queue-worker host.

## Tour discovery and one-click provisioning

Admins can refresh at least 100 demand-ranked tour suggestions from **Admin → Tour suggestions**. Discovery combines Google Trends, searches with no result on the site, and the configured destination catalog. Creating a suggestion queues SEO page creation, attaches 4–10 configured providers, crawls their structured price/rating data, compiles provider content, and then publishes the tour.

**Admin → Synchronization** centralizes tour discovery, price/rating refresh, content refresh, and full synchronization with run history. Discovery runs daily at 01:30, prices run once daily through the `sync` queue, and provider content runs daily at 02:30. Provider definitions and schedule settings can be customized in `config/crawler.php` or with `DAILY_PRICE_REFRESH_AT`, `GOOGLE_TRENDS_GEO`, `GOOGLE_TRENDS_FEED_URL`, and `TOUR_SUGGESTIONS_LIMIT`.

## SEO launch migration

Old `geyt.ir` detail URLs are stored in `legacy_redirects` and permanently redirect to the current canonical URL of their linked comparison page. Because the destination is linked by page ID, later slug changes do not break the migration map. Before launch:

While the old site is still publicly available, keep `GEYT_REFERENCE_LIVE_DISCOVERY=true` and capture its exact URLs:

```bash
php artisan tours:discover
php artisan seo:audit-migration
```

Resolve all unmatched URLs in the admin panel before switching the site. After cutover, set `GEYT_REFERENCE_LIVE_DISCOVERY=false` so the new site is not scanned as though it were the legacy source. Production settings and deployment commands are:

```dotenv
APP_URL=https://geyt.ir
APP_ENV=production
APP_DEBUG=false
GEYT_REFERENCE_LIVE_DISCOVERY=false
```

```bash
php artisan migrate --force
php artisan seo:audit-migration
```

Open **Admin → Content & Revenue → SEO Redirects**, resolve every unmatched URL, and run the audit again until it exits successfully. The launch checklist is:

1. Crawl a sample of old URLs and verify one-hop `301` responses to relevant new pages; do not redirect unrelated removed pages to the home page.
2. Verify `https://geyt.ir/sitemap.xml` and submit it in Google Search Console.
3. Keep all old URL redirects for at least one year, preferably indefinitely.
4. Preserve Search Console verification and analytics tags, update internal/profile/campaign links, and monitor 404s, indexing, crawl load, and traffic after launch.
5. Test JSON-LD with Google's Rich Results Test and inspect representative tour, hotel, accommodation, visa, category, magazine, and provider URLs.

The new site serves self-referencing canonicals, an XML sitemap containing only published canonical pages, crawler rules in `public/robots.txt`, structured data matching visible content, and true `404` responses for old pages without a relevant replacement.

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
