# nthdesigns Prospect Scanner

Internal tool to discover local businesses via Google Places, score GBP and website accessibility weakness, generate shareable audit reports, and draft outreach emails with Claude.

## Stack

- Laravel 13, Breeze (Inertia + React), PostgreSQL (database queue in production on Laravel Cloud)
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
| `QUEUE_CONNECTION` | `database` (default) — jobs in Postgres; use `queue:work` locally and on Laravel Cloud |
| `DB_QUEUE_RETRY_AFTER` | Set to `250` in production (must exceed audit job timeout) |
| `REDIS_CLIENT=predis` | Only if you switch cache/queue to Redis; not required for database queues |
| `AUDIT_SCRIPT_PATH` | Path to `scripts/audit.js` |
| `REPORT_BOOKING_URL` | CTA on public reports |
| `REPORT_EXPIRY_DAYS` | Report link expiry (default 30) |

## Running

```bash
composer dev           # serve, queue worker, logs, Vite (uses QUEUE_CONNECTION from .env)
# or manually:
php artisan queue:work --queue=searches,niches,auditing --timeout=240
php artisan serve
npm run dev
```

Production on [Laravel Cloud](docs/deployment/laravel-cloud.md) uses `QUEUE_CONNECTION=database` and `php artisan queue:work` on a worker cluster (not Horizon). Audits and screenshots run on a separate [Fly.io browser service](scripts/browser-service/README.md) (`AUDIT_SERVICE_URL`) — Playwright does not run on Cloud workers.

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
