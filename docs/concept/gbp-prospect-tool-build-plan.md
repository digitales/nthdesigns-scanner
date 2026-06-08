# GBP Prospect Scoring Tool — Laravel Build Plan

## What It Does

A three-section web app that scrapes Google Business Profile data for local businesses by niche and city, scores each listing against a weakness rubric, and generates personalised cold outreach emails via Claude. Target buyer: local SEO freelancers, small agencies, and solo operators running GBP optimisation services.

---

## Tech Stack

| Layer | Choice | Version | Notes |
|---|---|---|---|
| Framework | Laravel | 13 (Mar 2026) | PHP 8.3+ required; zero breaking changes from 12 |
| Frontend | Inertia.js + React | v3 + React 19.2.x | Inertia v3 stable Mar 2026; React 19.2.6 current |
| Styling | Tailwind CSS | v4 | CSS-first config; no tailwind.config.js; Vite plugin replaces PostCSS |
| AI SDK | Laravel AI SDK | stable (Mar 2026) | First-party, ships with Laravel 13; use this over direct HTTP calls |
| Queue | Laravel Horizon + Redis | latest | Places API scrapes are async; Horizon gives visibility |
| Database | PostgreSQL | 16+ | JSON columns for raw Places API responses |
| Auth | Laravel Breeze | latest | Minimal, no bloat |
| Deployment | Laravel Forge + DigitalOcean or Hetzner | - | Cheaper than Vercel for PHP; Forge handles Horizon daemon |

**OpenRouter transport**: Outreach LLM calls use `OpenRouterService` posting to OpenRouter `/chat/completions`. Frontier model selection stays env-driven via `OPENROUTER_MODEL` (e.g. `anthropic/claude-sonnet-4`). This project does not use the Laravel AI SDK.

**Tailwind v4 install note**: No `tailwind.config.js`. Configuration lives in `resources/css/app.css` using `@theme`. Use the official Vite plugin (`@tailwindcss/vite`) rather than PostCSS. New Laravel 13 + Breeze installs scaffold this correctly by default.

---

## Pages / Routes

```
GET  /                  → redirect to /search
GET  /search            → ProspectSearch (niche + city input, results table)
GET  /outreach          → OutreachGenerator (select prospects, generate emails)
GET  /saved             → SavedProspects (export CSV, review history)
GET  /admin/horizon     → Horizon dashboard (guarded by auth)
```

All routes except `/admin/horizon` are accessible post-login. Breeze gives you the auth scaffolding.

---

## Data Models

### `searches`
```
id
user_id
niche          (string)
city           (string)
country        (string, default 'GB')
status         (enum: pending, running, complete, failed)
total_found    (integer, nullable)
created_at
updated_at
```

### `prospects`
```
id
search_id
place_id           (Google Places unique ID, indexed)
business_name
phone              (nullable)
website            (nullable)
address
rating             (decimal 2,1, nullable)
review_count       (integer, default 0)
last_review_date   (date, nullable)
photo_count        (integer, default 0)
has_services       (boolean)
has_description    (boolean)
hours_complete     (boolean)
weakness_score     (integer, 0–100, higher = weaker profile)
weakness_flags     (json)    ← array of specific weaknesses
raw_payload        (json)    ← full Places API response
created_at
updated_at
```

### `outreach_emails`
```
id
prospect_id
user_id
cpc_benchmark      (decimal, nullable)
niche_cpc_source   (string, nullable)  ← 'semrush' | 'manual' | null
email_body         (text)
subject_line       (string)
model_used         (string)            ← claude-sonnet-4-20250514
prompt_tokens      (integer)
completion_tokens  (integer)
created_at
```

### `exports`
```
id
user_id
search_id (nullable)
filename
row_count
created_at
```

---

## Core Services

### `GooglePlacesService`

Wraps the Places API (New) Text Search and Place Details endpoints.

```php
class GooglePlacesService
{
    public function searchByNicheAndCity(string $niche, string $city, string $country = 'GB'): array
    // Returns array of place_ids matching the query

    public function getPlaceDetails(string $placeId): array
    // Returns full place detail payload: name, phone, website, rating,
    // userRatingCount, photos, regularOpeningHours, editorialSummary, types
}
```

**Fields to request in the Places API field mask:**
```
places.id,places.displayName,places.formattedAddress,
places.nationalPhoneNumber,places.websiteUri,places.rating,
places.userRatingCount,places.photos,places.regularOpeningHours,
places.editorialSummary,places.primaryType
```

The New Places API (v1) uses field masks in the `X-Goog-FieldMask` header. Budget roughly $17 per 1,000 Place Details calls at the standard tier. Cache place detail responses in the `raw_payload` column so you are not re-fetching for the same place_id.

### `ProspectScoringService`

Applies the weakness rubric to a raw Places API payload. Returns a score (0–100) and a `weakness_flags` array.

```php
class ProspectScoringService
{
    public function score(array $placePayload): ProspectScore
    // Returns: score (int), flags (array of strings)
}
```

**Scoring rubric (total 100 points — higher = weaker = better prospect):**

| Signal | Points | Condition |
|---|---|---|
| Review count | 25 | < 20 reviews |
| Review count partial | 15 | 20–50 reviews |
| No recent activity | 20 | Last review > 6 months ago (infer from recency of reviews if exposed) |
| No photos | 15 | photo_count === 0 |
| Photos minimal | 8 | photo_count < 5 |
| No website | 10 | websiteUri null |
| No description | 10 | editorialSummary null or empty |
| Hours incomplete | 10 | regularOpeningHours null |
| Low rating | 10 | rating < 3.5 |

Flags are human-readable strings, e.g. `"Under 20 reviews"`, `"No photos uploaded"`, `"Missing business description"`. These are used directly in the outreach prompt.

### `OutreachGeneratorService`

Uses the Laravel 13 AI SDK (`use Illuminate\AI\Facades\AI`) rather than raw HTTP. Single method for outreach generation.

```php
class OutreachGeneratorService
{
    public function generateOutreachEmail(
        Prospect $prospect,
        string $nicheContext,
        ?float $cpcBenchmark = null
    ): OutreachResult
    // Returns: subject, body, prompt_tokens, completion_tokens
}
```

Configure in `config/ai.php`:
```php
'default' => 'anthropic',
'providers' => [
    'anthropic' => [
        'model' => 'claude-sonnet-4-20250514',
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],
],
```

**System prompt template:**

```
You are an outreach specialist writing cold emails for a GBP optimisation agency.
Write a short, direct email to a local business owner.
- Under 100 words
- No marketing fluff
- Lead with a specific weakness from their Google Business Profile
- Make the value proposition concrete (better visibility, not "dominate Google")
- If a CPC benchmark is provided, use it to frame the cost of paid alternatives
- Sound like a person, not a template
- Do not use phrases like "I hope this email finds you well"
```

User message includes: business name, niche, city, weakness flags array, CPC benchmark (if available), and agency pricing placeholder.

---

## Job Architecture

Two queued jobs, both on the `scraping` queue.

### `ScrapeProspectsJob`

Dispatched when a search is created.

1. Calls `GooglePlacesService::searchByNicheAndCity()` — returns up to 60 results (paginated with `pageToken` up to 3 pages)
2. For each place_id, dispatches `ScorePlaceJob` individually
3. Updates `searches.status = 'running'`

### `ScorePlaceJob`

Dispatched per place_id.

1. Calls `GooglePlacesService::getPlaceDetails()`
2. Calls `ProspectScoringService::score()`
3. Creates or updates `Prospect` record
4. On completion of all jobs for a search, updates `searches.status = 'complete'` (use a job chain or a `ScrapeProspectsJob::then()` callback that counts remaining)

**Failure handling:** Both jobs implement `shouldRetry` with 3 attempts and exponential backoff. Failed jobs land in the `failed_jobs` table. Horizon surfaces them visually.

---

## Frontend Pages (React via Inertia)

### `/search` — ProspectSearch

- Form: niche (text), city (text), country (select, default GB)
- On submit: POST `/searches`, redirects to `/searches/{id}` polling page
- Results view: table sorted by `weakness_score` desc
  - Columns: Business Name, Score (coloured badge), Review Count, Weaknesses (tag list), Phone, Website
  - Row actions: "Add to outreach", "View on Maps" (links to `maps.google.com/?cid=...`)
- Polling: use Inertia's `router.reload()` on an interval while `search.status !== 'complete'`

### `/outreach` — OutreachGenerator

- Left panel: selected prospects list (carried via session or a `outreach_selections` pivot)
- Right panel: email generation form
  - Niche context field (pre-filled from search)
  - Optional CPC benchmark input (manual, with a note that Claude cannot reliably generate this)
  - "Generate All" button: dispatches per-prospect Anthropic calls (these are fast enough to be synchronous; no queue needed unless doing 20+ at once)
- Output: card per prospect showing subject + body, with copy button and edit-in-place

### `/saved` — SavedProspects

- Table of all saved prospects across searches
- Filters: search date, niche, city, min weakness score
- "Export CSV" button: GET `/exports` returns a streamed CSV via Laravel's `StreamedResponse`
- Previous email drafts linked per prospect

---

## External API Setup

### Google Places API (New)

- Enable: Places API (New) in Google Cloud Console
- Billing: required, set a budget alert at £50/month to start
- Key: restrict to your server IP in production
- Env var: `GOOGLE_PLACES_API_KEY`
- Base URL: `https://places.googleapis.com/v1/places`

### Anthropic (via Laravel AI SDK)

- Config: `config/ai.php`, provider set to `anthropic`
- Env var: `ANTHROPIC_API_KEY`
- Model: `claude-sonnet-4-20250514`
- Max tokens: 300 (outreach emails are short; no need for more)

---

## Environment Variables

```env
APP_NAME="GBP Prospector"
APP_URL=https://yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=gbp_prospector
DB_USERNAME=
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

QUEUE_CONNECTION=redis

GOOGLE_PLACES_API_KEY=
ANTHROPIC_API_KEY=

HORIZON_PREFIX=gbp-prospector
```

---

## Artisan Commands

```bash
php artisan make:model Search -m
php artisan make:model Prospect -m
php artisan make:model OutreachEmail -m
php artisan make:model Export -m

php artisan make:job ScrapeProspectsJob
php artisan make:job ScorePlaceJob

php artisan make:service GooglePlacesService     # manual, no artisan generator
php artisan make:service ProspectScoringService
php artisan make:service OutreachGeneratorService  # uses Laravel AI SDK facade

php artisan make:controller SearchController
php artisan make:controller OutreachController
php artisan make:controller ExportController
```

---

## Migrations — key columns

```php
// prospects table — JSON columns for PostgreSQL
$table->json('weakness_flags')->nullable();
$table->json('raw_payload')->nullable();
$table->unsignedSmallInteger('weakness_score')->default(0)->index();

// Index for deduplication
$table->unique(['search_id', 'place_id']);
```

---

## Build Sequence

**Phase 1 — Core data pipeline (days 1–3)**
- Laravel install, Breeze, Inertia + React, Horizon
- Migrations and models
- `GooglePlacesService` with real API calls (test with Postman first)
- `ProspectScoringService` with rubric (pure PHP, easy to unit test)
- `ScrapeProspectsJob` and `ScorePlaceJob`
- Basic `/search` page: form + results table, polling

**Phase 2 — Outreach generation (days 4–5)**
- `OpenRouterService` with outreach prompt
- `/outreach` page: selection, generation, copy/edit
- Session-based prospect selection (simple before adding proper saved lists)

**Phase 3 — Saved prospects and export (day 6)**
- `/saved` page with filters
- CSV export via streamed response
- Email history per prospect

**Phase 4 — Polish and hardening (day 7)**
- Error states in UI (failed scrape, API errors)
- Places API cost monitoring (log field mask usage)
- Rate limiting on search submissions (1 per user per 30 seconds)
- Horizon queue monitoring page behind auth middleware

---

## Cost Modelling (UK market)

| Operation | Cost | Volume |
|---|---|---|
| Text Search (per call) | ~$0.032 | 3 calls per search (pagination) |
| Place Details (per record) | ~$0.017 | ~60 records per search |
| Total per search run | ~$1.12 | |
| Claude outreach (per email) | ~$0.003 | 300 output tokens |
| 10 searches/day | ~$11/day | ~£240/month |

At £300/month per client (the UK-adjusted price from the Derek Gray analysis), you can absorb API costs at 1–3 clients before it needs to be a paid SaaS tier. If you charge for the tool itself, price at £29–49/month per seat and model costs are negligible.

---

## Things to Watch

- **Places API pagination**: the New API returns a `nextPageToken` that requires a short delay (~2 seconds) before use; handle this in the job with `sleep(2)` or a delayed dispatch
- **Review date**: the Places API (New) does not expose individual review dates in the basic detail call without enabling the Reviews API separately; `last_review_date` scoring may need to be dropped or replaced with a review velocity proxy (review count relative to business age, if available)
- **Phone number formatting**: UK numbers come back in E.164 format (`+441234567890`); format for display
- **Duplicate place_ids**: the same business can appear in multiple searches; the `unique(['search_id', 'place_id'])` constraint handles this at the DB level, but your service layer should check before calling Place Details again (use the cached `raw_payload`)
