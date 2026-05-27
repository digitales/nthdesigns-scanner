# nthdesigns Prospect Scanner

Internal tool to discover local businesses via Google Places, score GBP and website accessibility weakness, generate shareable audit reports, and draft outreach emails with Claude.

## Stack

- Laravel 13, Breeze (Inertia + React), Horizon, Redis, PostgreSQL
- Google Places API (New), Playwright + axe-core, Lighthouse (optional), OpenRouter (Anthropic models)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# PostgreSQL
createdb nth_scanner
php artisan migrate

# Frontend
npm install
npm run build

# Audit scripts (Playwright)
cd scripts && npm install && npx playwright install chromium && cd ..

# Storage for report screenshots
php artisan storage:link
```

Configure `.env`:

| Variable | Purpose |
|----------|---------|
| `GOOGLE_PLACES_API_KEY` | Business discovery + benchmarks |
| `OPENROUTER_API_KEY` | Outreach email generation (Anthropic models via OpenRouter) |
| `OPENROUTER_MODEL` | Model slug, e.g. `anthropic/claude-sonnet-4` |
| `QUEUE_CONNECTION` | `database` for local dev (no Redis); `redis` in production with Horizon |
| `REDIS_CLIENT=predis` | Redis client when using `QUEUE_CONNECTION=redis` (Predis is bundled; use `phpredis` only if the PHP extension is installed) |
| `AUDIT_SCRIPT_PATH` | Path to `scripts/audit.js` |
| `REPORT_BOOKING_URL` | CTA on public reports |
| `REPORT_EXPIRY_DAYS` | Report link expiry (default 30) |

## Running

```bash
composer dev           # serve, queue worker, logs, Vite (uses QUEUE_CONNECTION from .env)
# or manually:
php artisan queue:work --queue=scraping,auditing --timeout=180
php artisan serve
npm run dev
```

With `QUEUE_CONNECTION=redis`, run `php artisan horizon` instead of `queue:work` for multi-process workers and the `/horizon` dashboard. Horizon does not process `database` queues.

## Workflow

1. **Search** (`/search`) — niche + city + scan type (GBP only, accessibility only, or combined).
2. **Results** (`/searches/{id}`) — scored prospects; click a business for detail.
3. **Prospect** (`/prospects/{id}`) — generate public report, generate outreach email, track sent/response.
4. **Public report** (`/r/{token}`) — shareable audit for the prospect (no login).
5. **Horizon** (`/horizon`) — queue monitoring (auth required).

## Tests

```bash
php artisan test
```
