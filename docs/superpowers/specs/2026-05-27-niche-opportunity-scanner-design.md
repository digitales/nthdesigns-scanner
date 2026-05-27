# Niche Opportunity Scanner — Design Spec

**Date:** 2026-05-27  
**Status:** Approved — plan at `docs/superpowers/plans/2026-05-27-niche-opportunity-scanner.md`  
**Scope:** Batch research/triage over niche × city combinations using Google Places discovery, sampled GBP scoring, and a ranked `/niches` dashboard. Does not create `prospect` records or run accessibility audits.

**Approach:** Thin orchestration — `niches:scan` dispatches `ScanNicheJob` per combo on the `scraping` queue; aggregation lives in the job (private helpers).

---

## Goal

Help operators prioritise which **niche + city** pairs are worth a full prospect scan by:

1. Running lightweight sampled GBP weakness analysis across a fixed niche list and default UK cities.
2. Storing aggregate metrics and a composite **opportunity score** (higher = better prospect density).
3. Surfacing results on `/niches` with filters, sort, manual re-run, and one-click jump to a full `gbp_only` search.

---

## Constraints (from brainstorming)

| Topic | Decision |
|-------|----------|
| Run Full Scan scan type | `gbp_only` |
| Same-day upsert date | Calendar date in `Europe/London` via `scan_date` column |
| Niche identity in DB | `niche` (label) + `niche_query` (Places query string) |
| Zero Places results | `status: complete`, counts/aggregates zeroed, `opportunity_score: 0` |
| Niche list storage | `config/niches.php` only — no `niches` DB table |
| Benchmark in scoring | None — `GbpScoringService::score($payload)` absolute only |
| Prospect records | Not created from this flow |
| Accessibility | Not run |
| Pagination | Not in v1 |
| Scoring formula in UI | Hidden — show scores only |
| Data visibility | Global per app (no `user_id` on `niche_scans`) |
| Index rows shown | Latest row per `(niche, city)` by max `ran_at`; all statuses visible |

---

## Architecture

```text
Scheduler (Mon 06:00 Europe/London)
    └── niches:scan
            └── dispatch ScanNicheJob × (niches × cities)
                    └── GooglePlacesService::searchByNicheAndCity(query, city)
                    └── sample N place_ids → getPlaceDetails → GbpScoringService::score
                    └── upsert niche_scans

UI POST /niches/scan → Artisan::queue('niches:scan')
UI GET  /niches      → NicheScanController@index
UI POST /searches    → existing SearchController (Run Full Scan)
```

### Dependencies (existing)

- `App\Services\GooglePlacesService` — `searchByNicheAndCity()`, `getPlaceDetails()`
- `App\Services\GbpScoringService` — `score()` without benchmark
- `App\Support\ScrapingQueue` — `scraping` queue + connection from `config/scanner.php`
- Inertia + React UI kit (`DataTable`, `ScoreBadge`, `scoreBand`, etc.)

---

## Data model

### Migration: `niche_scans`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `niche` | string | Label, e.g. `Dental Practice` |
| `niche_query` | string | Places text query, e.g. `dental practice` |
| `city` | string | |
| `country` | string(2) | default `GB` |
| `scan_date` | date | Calendar day in `Europe/London` |
| `result_count` | unsigned integer | Total place IDs from search |
| `sampled_count` | unsigned integer | Place details successfully scored |
| `avg_gbp_score` | decimal(5,2) | Mean of sample `score` values |
| `pct_no_website` | decimal(5,2) | % of sample with null `websiteUri` |
| `pct_low_reviews` | decimal(5,2) | % of sample with `userRatingCount` < 20 |
| `opportunity_score` | decimal(5,2) | Weighted composite (see Scoring) |
| `status` | enum | `pending`, `complete`, `failed` |
| `ran_at` | timestamp nullable | Set when job finishes (success or zero-result) |
| `created_at`, `updated_at` | timestamps | |

**Unique index:** `(niche, city, scan_date)`

**Model:** `App\Models\NicheScan` with appropriate `$fillable` and casts (`scan_date` → date, decimals → float, `ran_at` → datetime).

### Config: `config/niches.php`

Plain PHP array of 20 entries:

```php
['label' => 'Dental Practice', 'query' => 'dental practice'],
// … Physiotherapist, Solicitor, Accountant, Estate Agent,
// Independent Hotel, Restaurant, Optician, Veterinary Practice,
// Private GP, Osteopath, Chiropractor, Beauty Salon, Barbershop,
// Plumber, Electrician, Architect, Financial Adviser,
// Mortgage Broker, Private Tutor
```

`NicheSeeder` is **not** a second source of truth. Optional: omit seeder or use it only in tests via `config('niches')`.

---

## Scoring

### Per-sample metrics

For each successfully fetched place detail:

- `gbp_score` = `GbpScoringService::score($payload, null)['score']` (0–100, higher = weaker; no benchmark)
- `no_website` = `websiteUri` is null
- `low_reviews` = `(userRatingCount ?? 0) < 20`

### Aggregates

- `avg_gbp_score` = arithmetic mean of sample scores (0 if no samples)
- `pct_no_website` = (count no_website / sampled_count) × 100
- `pct_low_reviews` = (count low_reviews / sampled_count) × 100

### Opportunity score

```text
opportunity_score = (avg_gbp_score × 0.4) + (pct_no_website × 0.35) + (pct_low_reviews × 0.25)
```

Range 0–100. Higher = better niche/city for outreach.

### Zero / partial results

| Case | Behaviour |
|------|-----------|
| `result_count === 0` | `sampled_count = 0`, aggregates 0 or null as appropriate, `opportunity_score = 0`, `status = complete` |
| Some `getPlaceDetails` null | Score successful payloads only; `sampled_count` = scored count |
| Job exhausted retries | `status = failed`, log error |

---

## Backend

### Command: `niches:scan`

**Signature:** `php artisan niches:scan {--cities=} {--niches=} {--sample=5}`

| Option | Default |
|--------|---------|
| `--cities` | `Birmingham,Manchester,Leeds,Bristol,Edinburgh` |
| `--niches` | all config labels (comma-separated filter) |
| `--sample` | `5` |

**Logic:**

1. Resolve city list and niche entries from config (filter labels case-insensitively when `--niches` set).
2. `scan_date = now('Europe/London')->toDateString()`.
3. Dispatch one `ScanNicheJob` per niche × city with scalar constructor args.
4. Output dispatched job count; exit immediately.

### Job: `ScanNicheJob`

- `ShouldQueue`, `ScrapingQueue::apply($this)`
- `$tries = 3`
- `$backoff = [30, 60, 120]` (seconds)

**Constructor:** `niche`, `niche_query`, `city`, `country`, `sample` (int), `scan_date` (string Y-m-d)

**Handle:**

1. `DB::transaction()` — upsert row `(niche, city, scan_date)` with `status = pending`, preserve label/query/city/country.
2. `searchByNicheAndCity($niche_query, $city, $country)` → store `result_count`.
3. Early complete path if `result_count === 0`.
4. `array_rand` / `Arr::random` sample `min($sample, $result_count)` place IDs.
5. Loop details + score (no benchmark).
6. `DB::transaction()` — write aggregates, `status = complete`, `ran_at = now()`.
7. On failure after retries: set `status = failed` in `failed()` hook if possible.

### Scheduler

`routes/console.php`:

```php
Schedule::command('niches:scan')
    ->weekly()
    ->mondays()
    ->at('06:00')
    ->timezone('Europe/London');
```

### Controller: `NicheScanController`

**`GET /niches` → `index`**

- Subquery: ids of rows with max `ran_at` per `(niche, city)` (include pending/failed for operator visibility).
- Filter: `?city=` optional.
- Sort: `?sort=opportunity_score` (default) or `result_count`, both descending.
- Inertia: `Niches/Index` with `scans`, `cities`, `filters`.

**`POST /niches/scan` → `trigger`**

- `Artisan::queue('niches:scan')` with defaults.
- Redirect back, flash: `Scan queued`.

### Routes

Inside `Route::middleware('auth')`:

- `GET /niches` → `niches.index`
- `POST /niches/scan` → `niches.scan`

---

## Frontend

### Page: `resources/js/Pages/Niches/Index.jsx`

**Toolbar**

- **Run Now** — POST `/niches/scan`
- **City** — Select, GET `/niches?city=`
- **Sort** — Opportunity score | Result count

**Table columns**

| Column | Source |
|--------|--------|
| Niche | `niche` (label) |
| City | `city` |
| Result Count | `result_count` |
| Avg GBP Score | `avg_gbp_score` — `ScoreBadge` |
| No Website % | `pct_no_website` |
| Low Reviews % | `pct_low_reviews` |
| Opportunity Score | `opportunity_score` — `ScoreBadge` |
| Last Scanned | relative from `ran_at` |
| Action | Run Full Scan |

**Badge colours** (reuse `scoreBand` / `ScoreBadge`):

- Red (high): ≥ 71  
- Amber (mid): 41–70  
- Green (low): &lt; 40  

**Run Full Scan** — POST `/searches`:

```json
{
  "niche": "<niche_query>",
  "city": "<city>",
  "country": "<country>",
  "scan_type": "gbp_only"
}
```

Redirects to existing `searches.show` flow.

### Nav

Add to `AppShell`: `{ href: '/niches', label: 'Niches', match: ['niches.index'] }` (placement after Search or before Outreach — implementer’s choice).

---

## What we are not building

- Accessibility audits in niche scan flow
- `Prospect` / `Search` records from niche scans (except explicit Run Full Scan)
- Pagination on `/niches`
- Per-user niche scan history
- Exposing opportunity formula in the UI
- `Bus::batch` progress tracking (v1)

---

## Testing

| Test | Intent |
|------|--------|
| Unit (optional) | Opportunity score from fixed sample inputs |
| Feature: command | Dispatches `ScanNicheJob` count = niches × cities |
| Feature: job | HTTP fake Places responses → upsert `complete` row with expected aggregates |

Use `Http::fake()` consistent with existing `ScrapeProspectsJobTest` patterns.

---

## File checklist (implementation reference)

| File | Action |
|------|--------|
| `database/migrations/*_create_niche_scans_table.php` | create |
| `config/niches.php` | create |
| `app/Models/NicheScan.php` | create |
| `app/Jobs/ScanNicheJob.php` | create |
| `app/Console/Commands/ScanNichesCommand.php` | create (`niches:scan`) |
| `app/Http/Controllers/NicheScanController.php` | create |
| `routes/web.php` | routes |
| `routes/console.php` | schedule |
| `resources/js/Pages/Niches/Index.jsx` | create |
| `resources/js/Components/ui/AppShell.jsx` | nav link |
| `tests/Feature/ScanNicheJobTest.php` | create (recommended) |

---

## Decisions log

| Question | Answer |
|----------|--------|
| Run Full Scan scan type | `gbp_only` |
| Upsert calendar date | `Europe/London` → `scan_date` |
| Niche columns | `niche` + `niche_query` |
| Zero results | `complete`, zeros, `opportunity_score = 0` |
| Implementation shape | Thin orchestration (command + job) |
