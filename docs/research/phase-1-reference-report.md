# StoreSiphon — Phase 1 Reference Inspection and Product Specification

Reference: https://woocommerce-scraper.com/ · Inspected 2026-10-21 · Research only, no implementation.

Evidence labels: **[O] Observed** (seen in page/network/DOM) · **[D] Derived** (statically read from markup, headers or the published spec) · **[I] Inferred** (reasoning, not verified) · **[U] Unknown**.

Tools actually used: Playwright MCP, Chrome DevTools MCP, REA (`capture_browser_scenario`, evidence `ev_aa8a668a…`), Claude in Chrome (user's own signed-in session, with permission), curl for robots.txt. robots.txt: `User-agent: *`, `Crawl-delay: 10` — all anonymous page loads were spaced ≥10 s.

---

## 1. Executive summary

- The reference is a PHP (Symfony-style: Webpack Encore `/build/`, `/login_check`, `_csrf_token`) web app with a 3-step wizard (Search → Categories → Products), a Chrome extension, a reviews scraper and a public REST API spec with async jobs. Payments are Stripe card only — no Bitcoin.
- Signed out, the demo is gated: Search only calls `/me` and shows a sign-in prompt.
- Signed in, the web app is a thin proxy around the target store's **WooCommerce Store API**: raw Store API JSON is sent to the browser, which filters, previews and **builds export files client-side**. Free-plan limits (5-product export, "Export all" premium gate) are enforced in the browser.
- Loading is a blocking synchronous request per page of 20 products (observed 58 s / 43 s / 8.6 s / 18 s for a 42-product store with variations) behind a "do not close the window" modal — no progress, no cancel, no resume.
- Exports: WooCommerce importer CSV (parent + variation rows), Shopify CSV (row per variant), and a flat CSV/Excel (one row per parent; variation values lost). Defects observed: sale/compare-at price filled with the regular price, HTML kept in descriptions, no BOM.
- Inconsistencies: homepage Free ($0) / Premium ($59.99/mo) vs. a "Developer plan" at $79/mo in the API guide; API docs say test keys 20 req/min, the dashboard says 10 req/min / 100 calls / 10 products per response; job status `succeeded` vs `completed`.
- Do not copy: FAQ claims it works on "protected" websites; the "Privacy Policy" link points to a security page; ToS lacks an acceptable-use clause; wildcard CORS on all responses.

## 2. Pages inspected

| URL | Purpose | Method |
|---|---|---|
| `/robots.txt` | `Crawl-delay: 10`, no disallows | curl |
| `/` | Landing: features, guides, pricing, testimonials, FAQ, newsletter | Playwright |
| `/terms-of-service` | Terms (Apr 2022): accounts, links, amendments; no automated-access clause | Playwright |
| `/scrape` | Product scraper wizard | Playwright, DevTools, REA, Chrome (signed in) |
| `/product-reviews-scrape` | Reviews scraper (product URL input) | Playwright |
| `/developers`, `/developers/reference`, `/openapi.yaml` | API docs (Scalar) and OpenAPI 3.1 spec | Playwright |
| `/guides` + 4 guides (API guide, automate exports, REST vs scraping, export to CSV) | Documentation | Playwright |
| `/refund-policy`, `/security-policy`, `/contact`, `/login`, `/register`, `/sitemap.xml` | Policies, forms (read only, never submitted) | Playwright |
| `/profile`, `/profile/api-keys` | Signed-in account pages (user's own account) | Claude in Chrome |

Not visited: remaining guides, "indeed-job-scraper" page, the Chrome extension, `/subscribe` (checkout — blocked as a transaction).

## 3. Verified user journeys

1. Signed out: `/scrape` → enter domain → Search → `GET /me` returns `{}` → sign-in prompt. No scrape request. [O ×3 tools]
2. Signed in: `GET /can-scraping?url=` `{result:true}` → `GET /woo/initialize?url=` `{success:true}` (also for non-WooCommerce sites) → `GET /woo/categories?url=` (raw Store API category objects) → "Display all products" → `GET /woo/products?url=&page=N` sequentially until an empty page; response `{limit:20,page,categories,products[]}` with full Store API product objects. [O]
3. Non-WooCommerce domain while signed in: wizard silently stays on step 1, no error. [O]
4. Product step: total count, Back, "Reload latest products" (ignores cached data → server-side cache [D]), FREE EXPORT (5), format select (WooCommerce/WordPress, Shopify, CSV/Excel), EXPORT ALL, search, type/category filters, 5 rows per page, per-row "View on site" / "Export product". [O]
5. FREE EXPORT: no network request; CSV built in-browser as a Blob. EXPORT ALL (free plan): inline "Premium Feature" banner, no network request. [O]
6. Profile: name, email, plan, expiration date; links to API Keys, Change Password, Sign Out. No job history, usage meter, or invoices. [O]
7. API Keys: create test key (name, mode); sandbox usage meter; live keys require the Developer plan. [O]

## 4. Reference feature inventory

| Feature | Evidence | Source | Confidence | Note for StoreSiphon |
|---|---|---|---|---|
| Domain → categories → products wizard | O | `/scrape` | High | Add a preflight store check step |
| Demo requires an account | O | `/me` gate | High | Consider capped anonymous preview |
| Free: 5 products, 3 sites/day; Premium $59.99/mo | O | Homepage | High | Single source of pricing truth |
| Developer API plan $79/mo, 100k calls | O | API guide, API Keys page | Medium | — |
| Exports: WooCommerce CSV, Shopify CSV, CSV/Excel | O | Signed-in exports | High | Fix price/variation defects; add XLSX, JSON |
| Variations: Woo parent+variation rows; Shopify row per variant; flat CSV loses them | O | Exports of a variable-product store | High | Per-variant rows by default |
| Category/type filters, search, client pagination | O | Product step | High | Server-side pagination |
| Fallback Store API → `?rest_route=` → sitemap + JSON-LD | O (docs) / D (spec) | API guide, `openapi.yaml` | Documented, untested | Adopt and extend |
| API: sync products/categories/reviews, async jobs (queued/running/succeeded/partial/failed), webhooks, usage | D | `openapi.yaml` | High | Phase 6 |
| Rate limits: live 120/min; `X-RateLimit-*`, 429 + `Retry-After` | O/D | Guide, spec | High | — |
| Card payments via Stripe | O | Stripe.js on every page | High | Bitcoin required for StoreSiphon |
| Claims to work on "protected" sites | O | FAQ | — | Reject: respect blocks |
| Captcha on register/contact | O | Forms | High | — |

## 5. Browser / network findings

Apache; HSTS; `PHPSESSID` (Secure, HttpOnly, SameSite=Lax); `Access-Control-Allow-Origin: *` with `*` methods/headers on every response incl. `/me`; Tailwind + daisyUI markup; GA4 + GTM; Stripe.js loaded site-wide; Scalar API reference loaded from jsDelivr `@latest` (unpinned). The products request does not go through `window.fetch` overrides (app keeps its own reference).

## 6. Unknowns

Fallback behaviour on stores with the Store API disabled; paid-plan behaviour and checkout; reviews scraper; API behaviour; Chrome extension.

## 7. StoreSiphon product requirements (summary)

- **Extraction ladder:** Store API → `?rest_route=` → WP REST `/wp/v2/product` (supplementary) → XML sitemaps (discovery) → JSON-LD/microdata → HTML (`data-product_variations`) → headless browser (opt-in, last resort, never to defeat challenges). Hard stop (`blocked`) on 401/403, CAPTCHA/challenge, robots disallow, or login walls.
- **Normalized model:** ids, slug, type, SKU, regular/sale/price as decimals from minor units, currency, stock status/quantity/backorder, categories, tags, brands, attributes, images, descriptions (HTML + sanitized text), rating, permalink, weight/dimensions, variations (own SKU, attributes, prices, stock, image), extraction method per field, warnings, content hash.
- **Jobs:** background, checkpointed, cancellable; states `queued → preflight → running → succeeded | partial | failed | blocked | cancelled`; per-host token bucket ≤2 concurrent, honour `Crawl-delay`/`Retry-After`; streamed exports to object storage.
- **Exports:** CSV (per-variant rows default), XLSX, JSON (MVP); Shopify and WooCommerce CSV (P1) with golden-file tests (no false sale flags; Shopify ≤3 options); UTF-8 with BOM; CSV formula-injection escaping.
- **Server-side enforcement** of all plan limits and export generation.

## 8. Bitcoin payment architecture (summary)

Evaluate BTCPay Server (self-hosted or managed host; non-custodial; Greenfield API; HMAC `BTCPay-Sig`; statuses New/Processing/Settled/Expired/Invalid with additionalStatus PaidPartial/PaidOver/PaidLate) first; hosted processors (OpenNode, BitPay, NOWPayments — verify from official docs) as alternatives. Coinbase Commerce is ruled out (retired into Coinbase Business; native BTC dropped per secondary sources).

Design: provider adapter; `payment_invoices` (unique reference, fiat amount, BTC amount, rate snapshot, expiry, status pending/detected/confirming/confirmed/expired/underpaid/overpaid/failed/refunded); append-only `payment_events` (unique provider event id → idempotency); verify HMAC on raw body → store → 200 → worker re-fetches the invoice from the provider API → forward-only transition → grant access only on confirmed, in a transaction; reconciliation cron; admin mismatch queue and audit log; secrets only server-side and redacted from logs; prepaid time-boxed plans recommended.

## 9. Security requirements (summary)

SSRF guard (http/https, ports 80/443, resolve-once + pinned IP, block private/loopback/link-local/CGNAT/metadata ranges v4/v6, re-validate every redirect); robots.txt + crawl-delay; identifiable UA; timeouts and response caps; per-plan quotas; domain opt-out list; never return raw HTML (no proxy use); Argon2id, CSRF, throttling, owner-scoped authorization, admin 2FA; sanitized descriptions; CSP; CORS restricted to own origin; bounded log retention; real privacy policy, ToS with acceptable use, Bitcoin refund policy.

## 10. Recommended stack (pending approval)

Laravel 12 (PHP 8.3+), Livewire or Inertia+Vue, Tailwind; PostgreSQL 16; Redis + Horizon; Guzzle with SSRF-safe transport + DomCrawler + JSON-LD parser; separate Node Playwright service (later); S3-compatible storage; Fortify/Sanctum; BTCPay Greenfield via provider adapter; Docker Compose/Sail; Sentry; Pest with recorded store fixtures and BTCPay regtest.

## 11. MVP and roadmap

MVP: auth + legal pages → preflight + Store API/rest_route/sitemap+JSON-LD/HTML extraction → background jobs with progress/cancel → preview table → CSV/XLSX/JSON → quotas + Bitcoin time-boxed plans with verified webhooks + reconciliation → minimal admin.
Phases: 0 Foundations · 1 Safe fetcher · 2 Extraction engine · 3 Jobs & UI · 4 Bitcoin billing · 5 Launch hardening · 6 API, Shopify/Woo exports, reviews, scheduling, browser worker.

## 12. Open decisions

Backend framework; database; Bitcoin route (self-hosted/managed BTCPay vs hosted processor, Lightning); confirmation policy; billing model; plan limits and prices; anonymous preview; browser rendering in v1; ethical policy confirmation; jurisdiction; hosting budget/region.
