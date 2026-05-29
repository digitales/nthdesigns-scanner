# Niches opportunity scanner

Operational reference for how niche×city batch scanning works, what is stored, and how Google Places API usage scales.

**Related:** [Laravel Cloud deployment — queue split](deployment/laravel-cloud.md#why-separate-searches-and-niches), design specs under `docs/superpowers/specs/2026-05-27-niche-opportunity-scanner-design.md`.

---

## Purpose

The niches feature is **market triage**, not prospect discovery:

1. Sample a handful of Google Business Profiles per niche×city.
2. Score GBP weakness (absolute scoring only — no benchmark competitor).
3. Store **aggregates** and an **opportunity score** so operators can rank markets before running a full `gbp_only` search.

It does **not** create `prospects`, `searches` (except when the operator clicks through), or run site audits.

---

## How it runs

### Triggers

| Trigger | What happens |
|---------|----------------|
| **Settings UI** | `POST /settings/niches/scan` → `Artisan::queue('niches:scan', [--force]?)` |
| **Settings UI** | `POST /settings/niches/bootstrap` → `Artisan::queue('niches:bootstrap', --no-interaction --force)` |
| **CLI** | `php artisan niches:scan {--cities=} {--niches=} {--sample=5} {--force}` |
| **CLI** | `php artisan niches:bootstrap {--min-results=5} {--dry-run} {--force}` |
| **Sample panel backfill** | `GET /niches/{id}/sample` may dispatch a **single** `ScanNicheJob` if `sample_preview` is null (see below) |

### Fan-out

`ScanNichesCommand` reads `config('niches.niches')` and cities from `config('niches.cities')` (or `--cities` / `--niches` overrides). For each combination it dispatches one `ScanNicheJob` onto the **`niches`** queue (`App\Support\NicheQueue`).

Current config scale (as of bootstrap on 2026-05-27):

- **53** niches
- **119** cities  
- **6,307** jobs per unattended full run (`53 × 119`)

Default cities in the command only apply when `config('niches.cities')` is empty (fallback: Birmingham, Manchester, Leeds, Bristol, Edinburgh). With the shipped config, **all 119 cities** are used.

### Per-job pipeline

```text
ScanNicheJob
  → upsert niche_scans (status: pending) for (niche, city, scan_date)
  → NicheSampleCollector::collect()
        → GooglePlacesService::searchByNicheAndCity(query, city, country)
        → random sample of place IDs (size = min(--sample, result_count))
        → foreach ID: getPlaceDetails() → GbpScoringService::score($payload, null)
        → compute aggregates + opportunity_score + sample_preview
  → update niche_scans (status: complete, ran_at)
```

On failure after retries: `status = failed` (`ScanNicheJob::failed()`). Job uses **3 tries**, backoff **30 / 60 / 120** seconds.

### Queue isolation

Niche jobs use queue `niches` and connection `NICHE_QUEUE_CONNECTION` (default: same Postgres `jobs` table as searches). Operator searches use `searches`. This prevents a full niche batch from blocking interactive search work. See `docs/deployment/laravel-cloud.md`.

---

## One-time bootstrap (`niches:bootstrap`)

**Not scheduled.** Used to generate or refresh `config/niches.php`.

| Step | Source | API cost |
|------|--------|----------|
| Cities | ONS ArcGIS major towns + supplementary devolved cities (HTTP, free) | 0 |
| Niche candidates | Google Places type taxonomy HTML scrape (HTTP, free) | 0 |
| Validation | `searchByNicheAndCity($query, 'Birmingham')` per candidate | **1 Text Search per candidate** (pagination still applies) |

Options: `--min-results=5`, `--dry-run`. If `GOOGLE_PLACES_API_KEY` is unset, validation is skipped and all candidates are kept.

`primary_type` on each niche entry is **metadata only** — `niches:scan` and `searchByNicheAndCity` use the text `query` only.

After bootstrap, operators are expected to **edit `config/niches.php` manually** rather than re-run bootstrap (which can overwrite the file).

---

## Google Places API usage

### Endpoints per job

| Call | Method | Field mask (billing driver) | When |
|------|--------|----------------------------|------|
| **Text Search (New)** | `POST …/places:searchText` | `places.id,nextPageToken` only | 1–3× per job (pagination) |
| **Place Details (New)** | `GET …/places/{id}` | `id`, `displayName`, `formattedAddress`, `nationalPhoneNumber`, `websiteUri`, `rating`, `userRatingCount`, `photos`, `regularOpeningHours`, `editorialSummary`, `primaryType`, `businessStatus` | Once per sampled place (default **5**) |

Search query shape: `"{niche_query} in {city}, {country}"` with `maxResultCount: 20`, up to **3 pages** (max **60** place IDs). Pages 2–3 require `sleep(2)` before using `nextPageToken` (Google requirement).

### SKU / cost notes

Billing is **per request**, at the **highest SKU** implied by the field mask ([Places usage and billing](https://developers.google.com/maps/documentation/places/web-service/usage-and-billing)):

- Niche **search** requests are cheap: IDs-only Text Search.
- Niche **detail** requests are heavier: fields like `websiteUri`, `userRatingCount`, `regularOpeningHours`, and `editorialSummary` push toward **Enterprise** (or higher) Place Details SKUs — not Essentials.

Check live prices in Google Cloud Console → Billing → SKU pricing. Credits/tiers change over time.

### Rough call volume formulas

Let:

- `N` = number of niches scanned  
- `C` = number of cities  
- `S` = sample size (`--sample`, default 5)  
- `P` = search pages used (1–3, depends on result volume)

**Per `ScanNicheJob`:**

```text
text_searches  = P          (typically 1; up to 3 if 20+ results and token present)
place_details  = min(S, result_count)   (often 5; fewer if sparse market)
total_calls    = P + place_details
```

**Full `niches:scan` (no filters):**

```text
jobs           = N × C
text_searches  ≈ jobs × 1.2        (assume ~20% of combos need 2+ pages)
place_details  ≈ jobs × S          (if most markets have ≥ S results)
```

**Example (current config, sample=5):**

| Scenario | Text Search | Place Details | Total |
|----------|-------------|---------------|-------|
| 6,307 jobs, 1 page + 5 details each | ~6,307 | ~31,535 | **~37,842** |
| Same, 20% need 3 pages | ~8,800 | ~31,535 | **~40,335** |
| Manual run: 5 cities × 53 niches | ~265 | ~1,325 | **~1,590** |

**Retries:** a failed job can repeat the **entire** collector (search + all details) up to 3 times.

**Bootstrap validation:** one Text Search per taxonomy candidate kept in the loop (often hundreds before filtering down to ~53 niches).

### What is *not* cached

There is no Redis/DB cache of Places responses for niche scans. Re-running `niches:scan` on the same day **upserts** the same `(niche, city, scan_date)` row, sets `pending`, and **calls the API again**.

---

## Data collected and stored

### Database: `niche_scans`

One row per **niche label + city + calendar day** (`scan_date` in `Europe/London`). Unique index: `(niche, city, scan_date)`.

| Column | Meaning |
|--------|---------|
| `niche` | Display label from config (e.g. `Dental Clinic`) |
| `niche_query` | Text passed to Places (e.g. `dental clinic`) |
| `city`, `country` | Location (default country `GB`) |
| `scan_date` | London calendar date for the run |
| `result_count` | Count of unique place IDs from Text Search (up to 60) |
| `sampled_count` | Place Details successfully fetched and scored |
| `avg_gbp_score` | Mean absolute GBP weakness score (0–100, higher = weaker) |
| `pct_no_website` | % of sample with empty `websiteUri` |
| `pct_low_reviews` | % of sample with `userRatingCount` < 20 |
| `opportunity_score` | Weighted composite (see below) |
| `sample_preview` | JSON array of sampled businesses (see below) |
| `status` | `pending` \| `complete` \| `failed` |
| `ran_at` | When the job finished |

**Not stored:** full Places payloads, phone numbers, addresses, photos, or individual prospect records. Only aggregates + a small preview.

### `sample_preview` JSON shape

Up to `sampled_count` objects:

```json
{
  "name": "Business name",
  "gbp_score": 42,
  "no_website": true,
  "review_count": 8
}
```

Populated in `NicheSampleCollector`; shown in the UI sample panel (`GET /niches/{id}/sample`).

### Config: `config/niches.php`

| Key | Purpose |
|-----|---------|
| `sample_size` | Used by sample-panel **backfill** only (not by `niches:scan` — that uses `--sample`) |
| `niches[]` | `label`, `query`, `primary_type` |
| `cities[]` | City name strings |

### UI (`/niches`)

- Lists **latest** row per `(niche, city)` by `ran_at` (window function), paginated, sortable by `opportunity_score` or `result_count`.
- **Manage niches** toggles ignored/included niches.
- **Run Full Scan** creates a normal `gbp_only` search via `POST /searches` (separate pipeline — additional Places usage per prospect).

Catalog refresh and market scan are on **Settings** only (`/settings` → Niche maintenance).

---

## Scoring

### Per-place (in memory only)

`GbpScoringService::score($payload, null)` — absolute weakness flags (reviews, photos, website, hours, description, etc.). No benchmark competitor for niche scans.

### Aggregates

- `avg_gbp_score` — mean of sample scores  
- `pct_no_website`, `pct_low_reviews` — percentages over **successful** samples only  

### Opportunity score

```text
raw = (avg_gbp_score × 0.4) + (pct_no_website × 0.35) + (pct_low_reviews × 0.25)

if result_count <= 1  → 0
if result_count == 2  → raw × 0.5
else                  → raw
```

Higher score = more attractive market for outreach. Recompute stored scores without API calls: `php artisan niches:recalculate-scores`.

---

## Optimisation ideas

Ordered by impact vs effort for **Places spend**:

### 1. Shrink the matrix (highest leverage)

- Trim `config/niches.cities` to a strategic subset (e.g. 20 cities) for weekly runs; keep full list for occasional audits.
- Remove low-value or duplicate niches (current list includes noisy taxonomy matches like `span`, `spark`, `school`).
- Use CLI filters:  
  `php artisan niches:scan --cities="Birmingham,Manchester,Leeds" --niches="Dentist,Plumber"`

### 2. Lower sample size

- `--sample=3` cuts Place Details ~40% with linear loss of statistical confidence.
- Align `config('niches.sample_size')` with `--sample` if you want the sample panel backfill to match.

### 3. Skip unchanged work (not implemented)

- Before dispatching, skip jobs where `status = complete` and `scan_date = today` (or same week).
- Optional: skip if `result_count` and scores unchanged and `ran_at` within N days.

### 4. Reduce Text Search pagination cost

- `result_count` only needs total IDs; sampling only needs the first page unless you want a wider random pool.
- **Option A:** cap at **1 page** (20 IDs) for niche scans — saves up to 2 Text Search calls per job when markets are large (trade-off: `result_count` max 20, sample bias toward top results).
- **Option B:** keep pagination for `result_count` but draw samples only from page 1 (cheaper details, still report 60 cap — misleading unless documented).

### 5. Slim Place Details field mask

- Add a dedicated method e.g. `getPlaceDetailsForNicheSample()` requesting only fields `GbpScoringService` needs: reviews, photos, website, hours, description, display name.
- Dropping unused fields (e.g. `formattedAddress`, `nationalPhoneNumber`, `primaryType`, `businessStatus`) may lower the billed SKU tier — verify against [Place Details field SKU table](https://developers.google.com/maps/documentation/places/web-service/place-details).

### 6. Avoid accidental duplicate runs

- Running **Run market scan** from Settings twice on the same day (without `--force` / L0 skip) doubles cost.
- Sample panel dispatches a **new** job when `sample_preview` is null but status is not `pending` — can re-hit the API for legacy rows.

### 7. Bootstrap discipline

- Run `niches:bootstrap` rarely; prefer hand-editing config.
- Use `--dry-run` and `--min-results` to limit validation searches.

### 8. Operational / infra

- Dedicated `niches` worker with `--sleep=5` (already documented) avoids starving searches; does not reduce API calls.
- Monitor `failed_jobs` — retries multiply spend.
- Track Google Cloud billing by SKU for Text Search vs Place Details separately.

---

## Caching recommendations

There is **no Places caching in the app today** (`GooglePlacesService` always calls the API). Default Laravel cache driver is **`database`** (`CACHE_STORE`), which is fine on Laravel Cloud; Redis is optional if payload volume grows.

Before implementing storage, read the current [Google Maps Platform Service Specific Terms](https://cloud.google.com/maps-platform/terms) — Place **content** (names, ratings, etc.) is generally subject to **refresh and retention limits** (often cited as ~30 days for cached place data). **Place IDs** are stable identifiers and are commonly reused. Design TTLs accordingly and do not treat cache as a permanent CRM.

### Layered model (cheapest first)

Think in four layers. Each layer avoids everything below it for that request.

```text
L0  niche_scans row (aggregates)     → skip entire job
L1  Text Search cache (query+city)   → skip 1–3 searchText calls
L2  Place Details cache (place_id)   → skip N getPlaceDetails calls
L3  Live Google API                   → only on miss or --force
```

| Layer | What to cache | Typical TTL | Saves (per job) | Shared with operator search? |
|-------|----------------|-------------|-----------------|------------------------------|
| **L0** | Completed `niche_scans` for `(niche, city, scan_date)` or rolling week | Until next scheduled run | **All** API calls | No |
| **L1** | Place ID list from `searchByNicheAndCity` | 7–14 days | 1–3 Text Search | **Yes** — same query as `ScrapeProspectsJob` |
| **L2** | Normalised Place Details payload (per field mask) | 7–30 days | Up to `sample` Place Details | **Yes** — same `place_id` in `ScorePlaceJob` |
| **L3** | — | — | — | — |

Implement **L0 + L2 first** for niche scans; add **L1** next; wire **L2/L1 inside `GooglePlacesService`** so operator searches benefit too.

---

### L0 — Use `niche_scans` as an aggregate cache (no new tables)

**Problem:** Re-running `niches:scan` on the same day re-fetches everything even when `status = complete`.

**Approach:**

1. In `ScanNichesCommand`, before dispatching, skip when a row exists with `status = complete` and `scan_date = today` (or `ran_at` within the last 7 days if you want weekly freshness without Monday-only semantics).
2. Add `niches:scan --force` to bypass.
3. In `NicheScanSampleController`, do not dispatch a new job if `sample_preview` is null but a **complete** row exists for the same combo within TTL — run a one-off `niches:scan` for that row only with `--force` if needed.

**Cost impact:** Eliminates duplicate full-matrix runs (UI + cron same day, accidental double-clicks). **~100% savings** on those duplicate runs.

**Trade-off:** Scores age until the next allowed run. Acceptable for triage; document `ran_at` in the UI.

---

### L1 — Text Search result cache

**Key:** deterministic hash of normalised inputs, e.g.

```text
places:search:v1:{sha1(lower(query)|city|country)}
```

**Value:** `{ "place_ids": [...], "fetched_at": "ISO8601" }` (IDs only — matches current cheap field mask).

**TTL:** **7–14 days** for niche batch; shorter (e.g. 24h) if you need fresher `result_count` for fast-moving markets.

**Where:** `GooglePlacesService::searchByNicheAndCity()` — `Cache::remember()` on hit; on miss, call API and store.

**Extras:**

- **Negative cache:** empty `place_ids` for **24h** to avoid repeat billing on junk taxonomy niches (`span`, `spark`).
- **Pagination:** cache the **merged** ID list after all pages (so one cache entry reflects full 1–3 page fetch).
- **Version suffix `v1`:** bump when search parameters change (`maxResultCount`, region, pagination cap).

**Cost impact:** Up to **~6,300 Text Search calls/week** on a full rescan if every combo is cached → **near zero** search spend on repeat runs within TTL.

**Caveat:** `result_count` and ranking drift over time. Refresh weekly (align TTL with Monday cron) or store `fetched_at` and show “search data from …” in UI.

---

### L2 — Place Details cache (highest £/$ per call for niches)

**Key:**

```text
places:details:v1:{place_id}:{field_mask_hash}
```

`field_mask_hash` = hash of the ordered field mask string so niche-slim and full-detail paths do not collide.

**Value:** raw JSON array returned by Google (or a trimmed DTO if you add `getPlaceDetailsForNicheSample()`).

**TTL:** **14–30 days** for niche sampling; **24–72 hours** for operator scoring if you need fresher review counts before outreach.

**Where:** `GooglePlacesService::getPlaceDetails()` — single choke point. `NicheSampleCollector` and `ScorePlaceJob` both benefit.

**Sampling interaction:**

- Cache is per `place_id`, not per niche×city. The same dental practice appearing in “dentist” and “dental clinic” searches in one city pays for details **once** within TTL.
- Random sample still works: draw IDs from L1, resolve each ID through L2 (mostly hits after the first weekly wave).

**Cost impact:** Second full matrix run in the same month: up to **~31,500 Place Details → ~0** if IDs overlap heavily across niches/cities (overlap is **within city**, not across cities — still saves **re-runs** and **operator searches** on known IDs).

**Storage note:** Details payloads are ~2–10 KB JSON. 50k entries ≈ hundreds of MB. Prefer a dedicated table over stuffing `cache` if you keep 30-day retention:

```text
place_detail_cache
  place_id (PK)
  field_mask_hash
  payload (json)
  fetched_at
  expires_at
```

Index `expires_at` for pruning (`scanner:purge-expired` or a new artisan command).

---

### L3 — Optional: persist sampled IDs on `niche_scans`

Add `sampled_place_ids` (json) when a scan completes.

**Use cases:**

- Recompute `opportunity_score` / `avg_gbp_score` from **L2 cache** without re-searching or re-sampling (`niches:recalculate-scores` extended).
- Audit which businesses drove a score.

Does not reduce API cost on first run; enables **cheap replays** after scoring formula changes.

---

### Cross-flow sharing (operator search)

```text
niches:scan  → searchByNicheAndCity("dentist", "Leeds")
                  ↓ L1 cache
operator     → ScrapeProspectsJob same query
                  ↓ L1 hit (no search)
               → ScorePlaceJob per place_id
                  ↓ L2 hit for IDs seen in niche sample
```

Recommend implementing L1/L2 in **`GooglePlacesService` only** (not in `NicheSampleCollector`) so all callers inherit behaviour.

---

### Implementation sketch (Laravel)

```php
// GooglePlacesService — illustrative
public function searchByNicheAndCity(string $niche, string $city, string $country = 'GB'): array
{
    $key = 'places:search:v1:'.hash('sha256', mb_strtolower("{$niche}|{$city}|{$country}"));

    if (! $this->forceRefresh && Cache::has($key)) {
        return Cache::get($key)['place_ids'];
    }

    $placeIds = $this->searchByNicheAndCityFromApi(...);

    Cache::put($key, ['place_ids' => $placeIds, 'fetched_at' => now()], now()->addDays(7));

    return $placeIds;
}
```

- Inject `bool $forceRefresh` via config (`PLACES_CACHE_ENABLED`, `PLACES_CACHE_FORCE`) or request flag on manual scans only.
- Log cache hit/miss counts (`places.cache.hit` / `places.cache.miss`) to Cloud logging for ROI tracking.
- On API failure, **do not** write negative cache except for confirmed empty results (HTTP 200, zero places).

**Config suggestions** (`config/scanner.php`):

| Key | Example | Purpose |
|-----|---------|---------|
| `places_cache_enabled` | `true` | Master switch |
| `places_search_ttl_days` | `7` | L1 |
| `places_details_ttl_days` | `14` | L2 |
| `places_cache_force` | `false` | Env override for debugging |

---

### What not to cache (or cache carefully)

| Data | Recommendation |
|------|----------------|
| Bootstrap Birmingham validation | Cache L1 per `query` during a single bootstrap run; do not cache for months (one-off command). |
| `getTopRankedInNiche` (reports) | Short TTL (1–24h) or no cache — ranking-sensitive. |
| `findByWebsiteUrl` | Cache by normalised host **only after** verified match; short TTL. |
| Full prospect pipeline audits | Unaffected — audits are not Places. |

---

### Invalidation and operations

| Event | Action |
|-------|--------|
| Operator needs fresh GBP data | `niches:scan --force` and/or `PLACES_CACHE_FORCE=true` for one run |
| Scoring formula change | `niches:recalculate-scores` + replay from L2/`sampled_place_ids` — no API |
| Google field mask / SKU change | Bump `v1` → `v2` in cache keys |
| Storage growth | Daily delete `expires_at < now()`; cap rows per `place_id` (latest wins) |
| Wrong stale scores | Lower TTL or show `fetched_at` on `/niches` |

---

### Expected savings (order of magnitude)

Assume full matrix **6,307 jobs**, **5** details each, **~1.2** search pages average.

| Scenario | Text Search | Place Details |
|----------|-------------|---------------|
| No cache, weekly full run | ~7,500 | ~31,500 |
| L0 only (duplicate run same day prevented) | 50% on duplicate day | 50% on duplicate day |
| L0 + L1, second run within 7d | **~0** | unchanged |
| L0 + L1 + L2, second run within 14d | **~0** | **~0** (warm cache) |
| L1 + L2, operator search after niche scan same city/niche | **0** | **~0** for overlapping IDs |

First cold run after deploy still pays full price; savings compound on **re-runs**, **overlapping niches in one city**, and **operator follow-up searches**.

---

### Recommended rollout

1. **L0** — skip dispatch in `ScanNichesCommand` + `--force` (1–2 hours, no schema).
2. **L2** — `getPlaceDetails` cache + TTL config + prune command (medium; biggest detail savings).
3. **L1** — `searchByNicheAndCity` cache + negative empty cache (medium).
4. **Observability** — hit rate metrics in logs.
5. **Optional** — `sampled_place_ids` + replay command for formula changes.

Avoid caching only inside `ScanNicheJob` — centralise in `GooglePlacesService` so `ScrapeProspectsJob` / `ScorePlaceJob` share the same layers.

---

## Implemented optimisations (2026-05-29)

### L0 — Skip complete scans

`niches:scan` skips `(niche, city, scan_date)` rows already `complete` for today unless `--force`.

### L2 — Place Details cache

`GooglePlacesService::getPlaceDetails()` caches payloads in Laravel cache (default: database) for `PLACES_DETAILS_TTL_DAYS` (default 14). Disable with `PLACES_CACHE_ENABLED=false` or bypass with `PLACES_CACHE_FORCE=true`.

### Niche exclusion list

| Mechanism | Behaviour |
|-----------|-----------|
| **Auto (`low_results`)** | After each `ScanNicheJob`, if max `result_count` across latest city scans for that niche is **&lt; `min_result_count`** (default 3), niche is added to `ignored_niches`. |
| **Manual** | Operators toggle via **Manage niches** on `/niches`. |
| **Override** | Including a low-result niche again sets a `niche_inclusion_overrides` row so auto-exclusion does not re-apply. |

Ignored niches are skipped by `niches:scan` (unless `--include-ignored`). UI filter **Ignored → Hidden/Shown** controls table visibility.

**One-time backfill from existing data:**

```bash
php artisan niches:sync-exclusions
```

**Config:** `config/niches.php` → `min_result_count` (default 3).

---

## Commands cheat sheet

```bash
# Full batch (all config niches × cities)
php artisan niches:scan

# Scoped batch
php artisan niches:scan --cities="Edinburgh,Glasgow" --niches="Dentist" --sample=3

# Regenerate config (expensive validation pass)
php artisan niches:bootstrap --min-results=5 --dry-run

# Recompute opportunity_score from DB aggregates (no API)
php artisan niches:recalculate-scores --dry-run
```

---

## Separation from operator search

| | Niche scan | Operator `gbp_only` search |
|--|------------|----------------------------|
| Queue | `niches` | `searches` |
| Jobs | `ScanNicheJob` | `ScrapeProspectsJob` → `ScorePlaceJob` |
| Storage | `niche_scans` aggregates | `searches`, `prospects` |
| Places usage | 1 search + ~5 details per combo | 1 search + 1 detail per prospect (plus scoring pipeline) |
| Purpose | Rank markets | Produce actionable prospect list |

Choosing a row on `/niches` and running **Full Scan** starts the second pipeline and incurs **additional** Places (and possibly audit) cost.
