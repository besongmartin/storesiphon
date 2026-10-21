# StoreSiphon — Production runbook (EU VPS)

Target (architecture D5): one Linux VPS in the EU running Nginx, PHP 8.3-FPM, MariaDB 11.4 LTS and Redis. Queue workers run under Supervisor, and the scheduler runs from cron. Files for this setup are in `deploy/`.

## 1. Server (Ubuntu 24.04 LTS, 2 vCPU / 4 GB is enough to start)

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install nginx mariadb-server redis-server supervisor git unzip ufw fail2ban certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis php8.3-intl php8.3-mbstring php8.3-bcmath php8.3-curl php8.3-xml php8.3-zip
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash - && sudo apt -y install nodejs
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
sudo mariadb-secure-installation        # set a root password, remove test DB and anonymous users
```

- Create the user `storesiphon`, plus `/var/www/storesiphon/{releases,shared/storage}` and the shared `.env` (from `deploy/env.production.example`).
- Create the database and user: `CREATE DATABASE storesiphon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` and a dedicated user with privileges only on it.
- Redis: bind to 127.0.0.1 and set `requirepass`.
- PHP: `expose_php=Off`, `memory_limit=256M`, OPcache on.

## 2. Install the configuration

```bash
sudo cp deploy/nginx/storesiphon.conf /etc/nginx/sites-available/ && sudo ln -s /etc/nginx/sites-available/storesiphon.conf /etc/nginx/sites-enabled/
sudo certbot --nginx -d storesiphon.com -d www.storesiphon.com
sudo cp deploy/supervisor/storesiphon-worker.conf /etc/supervisor/conf.d/ && sudo supervisorctl reread && sudo supervisorctl update
sudo cp deploy/cron/storesiphon /etc/cron.d/storesiphon
```

## 3. Deploy

```bash
./deploy/deploy.sh main
```

The script builds a new release, runs migrations, caches config, routes and views, switches the `current` symlink, restarts the workers and runs `deploy/smoke-test.sh`. Run the smoke test against staging first:

```bash
./deploy/smoke-test.sh https://staging.storesiphon.com
```

## 4. Payments (BTCPay)

1. Use a managed BTCPay host (D6) or your own server. Create the store and connect a **watch-only** wallet (xpub). Use **testnet** on staging.
2. Use on-chain only (D7), with the invoice expiry at 30 minutes.
3. Create an API key with only `btcpay.store.canviewinvoices` and `btcpay.store.canmodifyinvoices`.
4. Create a webhook to `https://storesiphon.com/webhooks/btcpay` for all invoice events, with automatic redelivery on.
5. Put the URL, key, store id and webhook secret in the shared `.env`. Never commit them, and never paste them in chat or tickets.
6. Test end to end on testnet:
   - Buy Starter, pay with a testnet wallet, and watch the invoice go to "Paid" after 1 confirmation.
   - Check that `storesiphon:health` shows nothing to review.

## 5. Monitoring

| What | How |
|---|---|
| Uptime | Point an external monitor at `https://storesiphon.com/up`. It fails when the database or cache is down. |
| Operations | Run `php artisan storesiphon:health` every 5 minutes from cron and alert on a non-zero exit. It checks the database, cache, worker heartbeat, scheduler heartbeat, queue backlog, failed jobs, BTCPay configuration and the payment review queue. |
| Errors | Optional: add Sentry (`composer require sentry/sentry-laravel`, set `SENTRY_LARAVEL_DSN`). Secrets are redacted from logs by `App\Logging\RedactSecrets`. |
| Admin | Review flagged invoices at `/admin/payments`. Admins must have two-factor authentication turned on. |

Make the first admin on the server:

```bash
php artisan tinker --execute 'App\Models\User::where("email","you@…")->first()->forceFill(["role"=>"admin"])->save();'
```

## 6. Backups

- `deploy/backup.sh` runs nightly from cron. It dumps the database, compresses and encrypts it with `age`, and pushes it to S3-compatible storage in the EU, keeping 30 days.
- Test a restore every month.
- Export files are short-lived and not backed up.

## 7. Before launch checklist

- [ ] Legal review of `/legal/terms`, `/legal/privacy`, `/legal/refunds`, with every `[placeholder]` filled in.
- [ ] Turnstile keys set; registration, try-it and opt-out show the widget.
- [ ] BTCPay testnet purchase passes; then switch to mainnet keys.
- [ ] `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HTTPS only, `.env` returns 404.
- [ ] Smoke test is green; `storesiphon:health` is green.
- [ ] Backups run and a restore was tested.
- [ ] Trademark search for "StoreSiphon" (brand guide §10).
