# StoreSiphon

Clean, structured product data from public WooCommerce stores. Laravel 12 · Livewire 3 · MariaDB · Tailwind 4.

- Product research: [docs/research/phase-1-reference-report.md](docs/research/phase-1-reference-report.md)
- Architecture decisions: [docs/architecture/architecture-decisions.md](docs/architecture/architecture-decisions.md)
- Brand guide and design system: [brand/README.md](brand/README.md), [brand/design-system.md](brand/design-system.md)

## Local development (Windows + XAMPP)

Requirements: PHP 8.2+ (`pdo_mysql`, `intl`, `sodium`), Composer 2, Node 22+, XAMPP MariaDB running.

1. Install dependencies:

   ```bash
   composer install
   npm install
   ```

2. Create the databases and app user. Do this once; edit the password in the script first if you're setting up a new machine:

   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root < database/local/create-local-db.sql
   ```

3. Configure and migrate:

   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   ```

   Set `DB_PASSWORD` in `.env` to match step 2.

4. Run the app: `npm run dev` (Vite; it syncs `brand/` assets into `public/brand` first) and `php artisan serve`, then open http://127.0.0.1:8000.
   - Emails (verification, password reset) go to `storage/logs/laravel.log` (`MAIL_MAILER=log`).
   - Extractions and exports run in the background. Keep a worker running:

     ```bash
     php artisan queue:work --queue=extractions,default --tries=1 --timeout=3600
     ```

   - Diagnostics (no data saved): `php artisan fetch:probe <url>` and `php artisan extract:run <store> --max=5`. Use them only on stores you are allowed to scrape.

## Tests

```bash
vendor/bin/pest          # uses the storesiphon_test database
vendor/bin/pint --test   # code style
```

CI (GitHub Actions) runs both against MariaDB 10.4 (local parity) and 11.4 LTS (production target).

## Security note for XAMPP

This folder lives inside `htdocs`, so the root `.htaccess` denies Apache access to everything except `public/` and `brand/`. Never remove it; otherwise `.env` would be downloadable from `http://localhost/StoreSiphon/.env`.
