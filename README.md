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
| `GOOGLE_ADS_*` | Dormant — Google does not approve keyword-only API use; CPC via Keyword Planner — see [docs/cpc-benchmarks.md](docs/cpc-benchmarks.md) |

## Documentation

| Doc | Contents |
|-----|----------|
| [docs/cpc-benchmarks.md](docs/cpc-benchmarks.md) | CPC workflow via Keyword Planner, market defaults, outreach inheritance |
| [docs/integrations/google-ads-cpc.md](docs/integrations/google-ads-cpc.md) | Dormant Google Ads API integration (not approved for keyword-only use) |
| [docs/integrations/google-ads-api-design-document.md](docs/integrations/google-ads-api-design-document.md) | Design doc for Basic Access application (export to PDF) |
| [docs/deployment/laravel-cloud.md](docs/deployment/laravel-cloud.md) | Production deploy, queues, Fly browser service |
| [docs/niches.md](docs/niches.md) | Niche opportunity scanner |

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

1. **Search** (`/search`) — niche + city + scan type. Set CPC from Keyword Planner or **Load saved** — see [CPC benchmarks](docs/cpc-benchmarks.md).
2. **Results** (`/searches/{id}`) — scored prospects; edit CPC and seed keywords → **Save default**; click a business for detail.
3. **Prospect** (`/prospects/{id}`) — generate public report, add to outreach queue, track sent/response.
4. **Outreach** (`/outreach`) — batch-generate emails; CPC pre-fills from search or market default.
5. **Public report** (`/r/{token}`) — shareable audit for the prospect (no login).
6. **Horizon** (`/horizon`) — queue monitoring (auth required).

## Tests

```bash
php artisan test
```
