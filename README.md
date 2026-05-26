# nthdesigns Prospect Scanner

Internal tool to discover local businesses via Google Places, score GBP and website accessibility weakness, generate shareable audit reports, and draft outreach emails with Claude.

## Stack

- Laravel 13, Breeze (Inertia + React), Horizon, Redis, PostgreSQL
- Google Places API (New), Playwright + axe-core, Lighthouse (optional), Anthropic API

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
| `ANTHROPIC_API_KEY` | Outreach email generation |
| `QUEUE_CONNECTION=redis` | Job queues |
| `AUDIT_SCRIPT_PATH` | Path to `scripts/audit.js` |
| `REPORT_BOOKING_URL` | CTA on public reports |
| `REPORT_EXPIRY_DAYS` | Report link expiry (default 30) |

## Running

```bash
php artisan horizon    # scraping + auditing queues
php artisan serve
npm run dev
```

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
