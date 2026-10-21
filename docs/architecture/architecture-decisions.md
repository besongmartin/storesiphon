# StoreSiphon — Architecture Decisions v1.0

Status: **Approved 2026-10-21** (D3 revised: MariaDB) · 2026-10-21 · Inputs: [Phase 1 report](../research/phase-1-reference-report.md), [brand guide](../../brand/README.md) (approved), [design system](../../brand/design-system.md)

## 1. Decision record

| # | Decision | Choice | Main reason | Revisit when |
|---|---|---|---|---|
| D1 | Backend | **Laravel 12** (PHP ≥ 8.2) | Fits the existing PHP/Composer setup. Queues, auth, policies, scheduler and Horizon are first-party | — |
| D2 | Frontend | **Livewire 3 + Blade + Tailwind** (brand tokens), Alpine for small interactions | Server-rendered dashboards, tables and forms with little JavaScript | Product table needs SPA-grade interaction |
| D3 | Database | **MySQL-family: MariaDB** (changed 2026-10-21 at the user's request; was PostgreSQL 17). Local: XAMPP MariaDB 10.4 via Laravel's `mariadb` driver; production: MariaDB 11.4 LTS (or MySQL 8.4) | Already bundled with XAMPP; `JSON` columns for raw payloads; large catalogues handled with batched upserts and per-job indexes instead of Postgres partitioning | Very large catalogues need partitioning |
| D4 | Local development | **Native Windows**: PHP 8.2 (XAMPP), XAMPP MariaDB, `php artisan serve` + Vite, **database** queue driver, local-disk storage, log mailer | Nothing new to install | Horizon or Redis behaviour must be tested locally (then WSL2) |
| D5 | Production | **Linux VPS in the EU** (e.g. Hetzner/DigitalOcean): Nginx, PHP-FPM 8.3+, MariaDB 11.4 LTS, **Redis + Horizon** under Supervisor, S3-compatible storage (Hetzner/R2), TLS | GDPR-friendly, low cost, full control | Scale beyond one app + one worker box |
| D6 | Bitcoin | **BTCPay Server on a managed host first** (non-custodial, xpub wallet), Greenfield API, accessed through a provider adapter | No custody, no node to run on day 1. Moving to self-hosting later needs only a config change | Volume or privacy justifies self-hosting |
| D7 | Lightning | **On-chain only** in MVP | Simpler wallet and accounting | Customer demand; BTCPay supports it without app changes |
| D8 | Confirmation policy | **1 confirmation** activates the plan (admin-configurable; `detected` never grants access by default) | Standard for low-value SaaS | Fraud observed |
| D9 | Billing model | **Prepaid time-boxed plans** (30 / 365 days) with renewal reminders and a fresh invoice | Bitcoin has no auto-debit | — |
| D10 | Plans (admin-editable data) | **Free**: 25 products per export, 3 stores/day · **Starter** ≈ $19 / 30 days: 5,000 products/month · **Pro** ≈ $49 / 30 days: 50,000 products/month + Shopify/WooCommerce formats + API later | Clear upgrade path | Pricing research |
| D11 | Anonymous preview | **Capped**: preflight + first ~10 products shown without an account (no export), behind a captcha + per-IP limits | Lowers the signup barrier; the reference gates everything | Abuse levels |
| D12 | Browser rendering | **Deferred**. MVP = Store API → `?rest_route=` → sitemaps → JSON-LD/HTML | Cost and abuse surface | Real stores need it |
| D13 | Crawling ethics | **Respect access controls**: robots.txt and `Crawl-delay`, identifiable user agent, hard stop on 401/403, challenge pages and login walls; public opt-out for store owners | Legal and ethical position; differentiates from the reference | — |
| D14 | Brand | **Siphon S** (approved), tokens from `brand/tokens/tokens.css` | — | — |

Defaults applied, which can still be changed:
- **Jurisdiction:** legal pages are drafted as templates and must be reviewed by a lawyer before launch.
- **Data retention:** extracted data and exports are kept for 30 days (Free 7 days).
- **Captcha:** Cloudflare Turnstile (privacy-friendlier).
- **Errors:** Sentry.

## 2. System overview

```
               ┌──────────────────────────── Nginx (TLS, rate limits) ────────────────────────────┐
 Browser ─────▶│ Laravel web (PHP-FPM): Livewire UI · Auth (Fortify) · Webhook endpoint /webhooks/btcpay │
               └───────────────┬──────────────────────────────┬────────────────────────────────────┘
                               │ dispatch                     │ read/write
                        ┌──────▼───────┐               ┌──────▼────────┐        ┌──────────────────┐
                        │ Redis queues │◀── Horizon ──▶│ MariaDB       │        │ S3 object storage │
                        │ extraction   │   workers     │ users, plans, │        │ exports (signed,  │
                        │ variations   │               │ jobs, products│        │ expiring URLs)    │
                        │ exports      │               │ invoices,     │        └──────────────────┘
                        │ payments     │               │ audit log     │
                        └──────┬───────┘               └───────────────┘
                               │ SafeHttpClient (SSRF guard, robots, per-host token bucket)
                               ▼
                     Public WooCommerce stores             BTCPay (managed host) ◀── Greenfield API / webhooks
```

Locally: same app, `QUEUE_CONNECTION=database`, `php artisan queue:work`, files on the local disk.

## 3. Application modules (`app/Domain/*`)

| Module | Responsibility | Key classes |
|---|---|---|
| **Fetching** | The only way to reach the internet: URL validation, DNS resolve-once + IP pinning, private/reserved range block (v4/v6), redirect re-validation (≤ 5), timeouts, size caps, robots.txt cache, per-host token bucket (≤ 2 concurrent, crawl-delay), retries with backoff + `Retry-After` | `SafeHttpClient`, `UrlGuard`, `RobotsPolicy`, `HostThrottle` |
| **Extraction** | Preflight (WooCommerce detection, method availability, estimated count, robots) and a strategy ladder with per-item provenance | `Preflight`, `StoreApiStrategy`, `RestRouteStrategy`, `SitemapStrategy`, `StructuredDataParser`, `HtmlVariationParser` |
| **Normalization** | Raw payload → canonical product/variation; minor units → decimals; HTML sanitize (allow-list); dedupe; content hash | `ProductNormalizer`, `PriceNormalizer`, `DescriptionSanitizer` |
| **Jobs** | Job lifecycle `queued → preflight → running → succeeded / partial / failed / blocked / cancelled`; page checkpoints; cancel flag; progress counters; quota reservation and settlement | `ExtractionJob` (model), `RunExtraction`, `FetchCatalogPage`, `FetchVariations`, `FinalizeJob` |
| **Export** | Streamed writers: CSV (per-variant rows, UTF-8 BOM, formula-injection escaping), XLSX, JSON; Shopify and WooCommerce CSV (P1) with golden-file tests | `ExportWriter` interface, `CsvExporter`, `XlsxExporter`, `JsonExporter`, `ShopifyCsvExporter`, `WooCsvExporter` |
| **Billing** | Plans, subscriptions (time-boxed periods), quotas/usage, invoices, payment state machine, reconciliation | `Plan`, `Subscription`, `UsageLedger`, `Invoice`, `PaymentEvent`, `PaymentProvider` interface, `BtcPayProvider`, `ReconcileInvoices` |
| **Accounts** | Fortify auth, email verification, password reset, 2FA (required for admins), settings, account deletion | Fortify actions, policies |
| **Admin** | Users (suspend), jobs (kill), plans editor, payments reconciliation + manual mark with reason, domain opt-out/block list, audit log viewer | Livewire admin pages, `AuditLogger` |
| **Compliance** | Opt-out requests, retention purge, legal pages | `PurgeExpiredData` (scheduled), `OptOutRequest` |

## 4. Core data model (first cut)

| Table | Key columns |
|---|---|
| `users` | id, name, email, password (argon2id), role (user/admin), email_verified_at, two_factor_*, suspended_at |
| `plans` | id, slug, name, price_usd, period_days, product_quota, stores_per_day, export_formats (json), is_active, sort |
| `subscriptions` | id, user_id, plan_id, starts_at, ends_at, source_invoice_id, status |
| `usage_ledger` | id, user_id, period_start, extraction_job_id, products_reserved, products_used, created_at |
| `stores` | id, normalized_host, base_url, platform_detected, methods_available (json), robots_checked_at, opted_out_at |
| `extraction_jobs` | id (ulid), user_id (nullable for anonymous preview), store_id, status, options (json), method_used, total_estimated, products_found, variations_found, pages_done, warnings (json), error_code, cancel_requested_at, started_at, finished_at, expires_at |
| `products` (indexed by job; JSON columns) | id, extraction_job_id, external_id, parent_external_id, type, name, slug, sku, regular_price, sale_price, price, currency, stock_status, stock_quantity, categories/tags/attributes/images (json), description_html, description_text, permalink, extraction_method, content_hash, raw (json, optional) |
| `exports` | id, extraction_job_id, format, options (json), status, disk, path, size_bytes, row_count, expires_at |
| `invoices` | id (uuid = order reference), user_id, plan_id, provider, provider_invoice_id (unique), fiat_amount, fiat_currency, btc_amount, rate, rate_source, rate_at, expires_at, status (pending/detected/confirming/confirmed/expired/underpaid/overpaid/failed/refunded), amount_paid_btc, confirmations, settled_at |
| `payment_events` | id, provider, provider_event_id (unique), invoice_id, type, payload (redacted json), signature_valid, processed_at |
| `audit_logs` | id, actor_id, action, subject_type, subject_id, before (json), after (json), ip, created_at |
| `opt_outs` | id, host, contact_email, verified_at, reason |

## 5. Critical flows

**Extraction**
1. The form is validated and `UrlGuard` checks the URL.
2. The quota is reserved, and `RunExtraction` is queued. If the preflight shows the store isn't WooCommerce, the job fails with a reason and the quota is released.
3. Catalogue pages are fetched sequentially per host. Each page is a checkpointed job, and products are upserted in batches.
4. Variations are fetched (throttled per host).
5. `FinalizeJob` settles usage to the actual product count and notifies the user.
6. Progress is shown in the UI by Livewire polling every 2 s; SSE comes later.

**Payment**
1. Checkout creates an `Invoice` and the matching BTCPay invoice (rate snapshot stored), then shows the payment page.
2. The BTCPay webhook verifies `BTCPay-Sig` (HMAC-SHA256 of the raw body), inserts a `payment_event` (unique ID, so duplicates become no-ops) and returns 200.
3. `ProcessPaymentEvent` re-fetches the invoice from the Greenfield API and moves its status forward only.
4. On `confirmed`, one DB transaction creates or extends the `subscription` and writes an audit log.
5. `ReconcileInvoices` runs every 5 minutes for open invoices.
6. Mismatches go to an admin queue.
7. The browser's return URL never grants access.

## 6. Security baseline

These come from Phase 1 §9 and are now binding:
- **Networking:** SSRF guard, robots.txt, per-host throttle.
- **No proxying:** raw HTML is never returned to users.
- **Web hardening:** CSP (self-hosted fonts/scripts), CORS limited to its own origin, CSRF, login throttling, and owner-scoped policies on every job, export and invoice.
- **Secrets:** stored in `.env` / the server secret store, never logged (a redaction processor in Monolog).
- **Accounts:** admin 2FA.
- **Retention:** purges run on a schedule.
- **Exports:** escape CSV formula injection.

## 7. Testing strategy

| Type | Coverage |
|---|---|
| Unit (Pest) | Normalizers and exporters, with golden files for each format (incl. variations, sale-price rules, BOM, formula escaping) |
| Fetching | Tests against **recorded fixtures**: Store API JSON, `rest_route`, sitemaps, JSON-LD, HTML samples, blocked/403/challenge pages. Tests never hit live stores |
| SSRF | Private IPs, IPv6, redirects to internal hosts, DNS rebinding simulation |
| Billing | BTCPay webhook fixtures (valid/invalid signature, duplicate, out-of-order, underpaid/overpaid/expired). Optional end-to-end on BTCPay testnet/regtest |
| Feature | Livewire component tests for the job form, progress, preview table, checkout status |
| CI | GitHub Actions on Ubuntu with MariaDB 10.4 (local parity) and 11.4 LTS (production target) |

## 8. Phased build plan (acceptance criteria per phase)

| Phase | Scope | Done when |
|---|---|---|
| **0 · Foundations** | Laravel 12 skeleton in this folder, MariaDB connection, Tailwind with brand tokens, Fortify auth, base layout (marketing + app shell) using brand assets, Pest, CI | `php artisan test` is green; a user can register, verify their email (log mailer), log in and see an empty dashboard in light/dark |
| **1 · Safe fetcher** | `SafeHttpClient` and friends | The SSRF test matrix is blocked; crawl-delay is honoured within ±10 %; 429 + `Retry-After` is respected |
| **2 · Extraction engine** | Preflight, strategies, normalizer | Fixture suite passes: simple/variable/sale/out-of-stock, Store API disabled → sitemap fallback, non-WooCommerce → clear failure (no quota), 403/challenge → `blocked` |
| **3 · Jobs, preview, exports** | Background jobs, progress, cancel, preview table, CSV/XLSX/JSON, anonymous preview | A 5,000-product fixture finishes within the memory cap; cancel takes effect within one page; per-variant CSV opens correctly in Excel; users can't see each other's data |
| **4 · Bitcoin billing** | Plans, checkout, BTCPay adapter, webhooks, reconciliation, invoices, admin payments | Invalid signature → 401 and no change; duplicate webhook is a no-op; a redirect alone grants nothing; 1 confirmation grants exactly one period; mismatches are queued for admin; no secrets in logs |
| **5 · Launch hardening** | Quotas, Turnstile, opt-out, retention purge, legal templates, monitoring, deploy to the EU VPS | Opt-out domains are refused; expired data is purged; a staging deploy passes a smoke test |
| **6 · Post-MVP** | Shopify/WooCommerce exports, public API + keys, reviews, scheduling, Playwright worker, Lightning | Defined per feature |

## 9. What I'll need from you, and when

| When | What |
|---|---|
| Phase 0 | Done: databases `storesiphon` / `storesiphon_test` and user `storesiphon` created on XAMPP MariaDB |
| Phase 4 | A BTCPay managed-host account and a store with a **testnet** wallet, created by you; I'll never handle your keys. You put the API key and webhook secret into `.env` yourself |
| Phase 5 | VPS/provider choice and domain DNS access, done by you. A lawyer's review of the legal pages |

Approval of this document unlocks Phase 0. Development starts only after your go-ahead.
