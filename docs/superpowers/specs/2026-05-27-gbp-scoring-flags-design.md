# GBP scoring flags expansion — Design Spec

**Date:** 2026-05-27  
**Status:** Approved (awaiting implementation plan)  
**Scope:** Extend `GbpScoringService` with additional absolute weakness flags, relative benchmark flags, and per-search benchmark caching. Every flag affects `gbp_score` and outreach/report copy.

**Approach:** Layered scorers (`scoreAbsolute` + `scoreRelative`) merged in `GbpScoringService::score()`.

---

## Goal

Improve prospect ranking and outreach specificity by:

1. Scoring signals already available in the Places Details payload but not yet flagged (phone, photo tiers, rating tiers, weak website hosts, business status).
2. Adding competitive weakness flags by comparing each prospect to the top-ranked listing for the same niche + city (one benchmark per search).
3. Keeping review recency out of scope (no `reviews` field mask or extra API calls).

Flags remain human-readable strings consumed by `OutreachEmailGeneratorService` and the operator/report UI.

---

## Constraints (from brainstorming)

| Topic | Decision |
|-------|----------|
| Flag purpose | Every flag adds points to `gbp_score` **and** appears in `gbp_flags` copy |
| Data sources | Places Details payload + one `getTopRankedInNiche()` snapshot per search |
| Review recency | **Out of scope** — no “no reviews in 6+ months” until a future paid `reviews` mask/call is approved |
| Score semantics | Higher `gbp_score` = weaker GBP = better prospect; cap at 100 via `min($score, 100)` |
| Combined score | No change to `CombineScoresService` weights — only `gbp_score` input changes |

---

## Architecture

### Benchmark lifecycle

1. `ScrapeProspectsJob` calls `GooglePlacesService::getTopRankedInNiche($niche, $city, $country)` once after place discovery.
2. Normalise the raw place into a snapshot and persist on `searches.benchmark_snapshot` (JSON, nullable).
3. Each `ScorePlaceJob` loads the search’s snapshot and passes it to `GbpScoringService::score($payload, ?array $benchmark)`.
4. `GenerateProspectReportJob` may continue to call `getTopRankedInNiche()` independently for report freshness; scoring uses the search snapshot for consistency across prospects in one run.

### Snapshot shape

```php
[
    'place_id'        => string,
    'name'            => string,
    'review_count'    => int,
    'photo_count'     => int,
    'rating'          => float|null,
    'has_description' => bool,
    'hours_complete'  => bool,
]
```

Normalisation logic should match `ReportBuilderService` benchmark extraction (consider a shared private helper or small `BenchmarkNormalizer` class to avoid drift).

### Scoring API

```php
// GbpScoringService
public function score(array $payload, ?array $benchmark = null): array
// Returns ['score' => int, 'flags' => string[]]

private function scoreAbsolute(array $payload): array
private function scoreRelative(array $payload, array $benchmark, string $city): array
private function mergeScores(array $absolute, array $relative): array
```

### Field mask addition

Add to `GooglePlacesService::getPlaceDetails()` field mask:

- `businessStatus`

`nationalPhoneNumber` and `websiteUri` are already requested.

---

## Scoring rubric

### Mutual exclusion

Within each **dimension**, only the **highest** applicable tier applies:

- Reviews (absolute tiers)
- Photos (absolute tiers)
- Rating (absolute tiers)
- Website (no URL vs weak host — weak host only when URL present)
- Description (absolute vs relative — see below)
- Hours (absolute vs relative — see below)

**Absolute vs relative overlap:** If an absolute flag already fired for description or hours, do **not** add the corresponding relative flag.

### Absolute flags (existing + new)

| Flag | Condition | Points |
|------|-----------|--------|
| Under 20 reviews | `userRatingCount < 20` | 25 |
| Fewer than 50 reviews | `20 ≤ userRatingCount ≤ 50` | 15 |
| No photos uploaded | `photo_count === 0` | 15 |
| Fewer than 5 photos | `1 ≤ photo_count ≤ 4` | 8 |
| Fewer than 10 photos | `5 ≤ photo_count ≤ 9` | 5 |
| No website listed | `websiteUri` empty | 10 |
| No dedicated website | `websiteUri` matches social/free-host allowlist (see below) | 8 |
| Missing business description | `editorialSummary.text` empty | 10 |
| Opening hours not set | `regularOpeningHours.periods` empty | 10 |
| No phone number listed | `nationalPhoneNumber` empty | 8 |
| Rating below 3.5 stars | `rating < 3.5` | 10 |
| Rating below 4 stars | `3.5 ≤ rating < 4.0` | 5 |
| Listing not fully operational | `businessStatus !== 'OPERATIONAL'` | 15 |

**Website allowlist (substring match on host, case-insensitive):**

`facebook.com`, `fb.com`, `instagram.com`, `linktr.ee`, `tiktok.com`, `twitter.com`, `x.com`, `yelp.`, `wixsite.com`, `square.site`, `godaddysites.com`, `google.site`, `sites.google.com`

“No dedicated website” applies only when a `websiteUri` exists and matches the allowlist, and “No website listed” did not already apply.

**Non-operational listings:** Apply the 15-point flag. Do **not** auto-skip scoring in v1; operator can filter in UI later if needed.

### Relative flags (benchmark required)

Skipped when:

- `$benchmark` is null
- `payload['id'] === $benchmark['place_id']` (prospect is the leader)

| Flag template | Condition | Points |
|---------------|-----------|--------|
| `{n} reviews vs {m} for the top listing in {city}` | `review_gap ≥ 25` OR (`leader_reviews ≥ 20` AND `prospect_reviews < 0.5 × leader_reviews`) | 15 |
| `Fewer photos than top local listing ({n} vs {m})` | `photo_gap ≥ 5` | 10 |
| `No description while top listing in {city} has one` | `!prospect.has_description && benchmark.has_description` | 8 |
| `Hours incomplete vs top listing in {city}` | `!prospect.hours_complete && benchmark.hours_complete` | 8 |
| `Lower rating than top listing in {city} ({p} vs {b})` | `benchmark.rating - prospect.rating ≥ 0.3` (both non-null) | 8 |

`review_gap` and `photo_gap` use the same definitions as `ReportBuilderService` gaps.

Relative review rule is **off** when `benchmark.review_count < 20` (leader too weak to compare).

### Score cap

Sum all points from applicable flags, then `min($total, 100)`. No normalised-percentage scoring.

---

## Data model

### Migration: `searches.benchmark_snapshot`

```php
$table->json('benchmark_snapshot')->nullable();
```

No prospect schema changes; `gbp_flags` JSON column already exists.

---

## Job changes

### `ScrapeProspectsJob`

After `searchByNicheAndCity`, before dispatching `ScorePlaceJob`:

```php
$benchmarkPlace = $places->getTopRankedInNiche(...);
$this->search->update([
    'benchmark_snapshot' => $benchmarkPlace ? $normalizer->fromPlace($benchmarkPlace) : null,
]);
```

Log warning if null; scoring continues with absolute-only flags.

### `ScorePlaceJob`

```php
$scored = $scorer->score($payload, $search->benchmark_snapshot);
```

---

## Error handling

| Case | Behaviour |
|------|-----------|
| Benchmark fetch fails | `benchmark_snapshot = null`; absolute scoring only |
| Prospect is benchmark place | Relative flags skipped |
| Leader has &lt; 20 reviews | Relative review flag skipped |
| Missing `businessStatus` in payload | Treat as operational (no flag) |

---

## Testing

### Unit (`GbpScoringServiceTest`)

- Existing cases updated for new signature `score($payload, $benchmark = null)`.
- Absolute: phone, 5–9 photos, rating 3.5–4.0 tier, allowlist hosts, `businessStatus`.
- Mutual exclusion: description/hours not doubled when absolute already set.
- Relative: fixture benchmark; gap thresholds; skip when prospect is leader.
- Cap: combined flags still `≤ 100`.

### Feature

- `ScrapeProspectsJob` persists `benchmark_snapshot` when Places returns a leader (HTTP fake).

---

## Explicitly deferred (v2)

- Review recency (“no reviews in 6+ months”) — requires `reviews` in field mask or additional API cost.
- `primaryType` vs search niche mismatch detection.
- Vertical Places attributes (delivery, parking, accessibility options).
- Auto-skip non-operational prospects at scrape time.

---

## Files to touch (implementation reference)

| File | Change |
|------|--------|
| `database/migrations/..._add_benchmark_snapshot_to_searches.php` | New column |
| `app/Models/Search.php` | Cast `benchmark_snapshot` as array |
| `app/Services/GbpScoringService.php` | Layered scoring + rubric |
| `app/Services/GooglePlacesService.php` | `businessStatus` in field mask |
| `app/Jobs/ScrapeProspectsJob.php` | Fetch + store benchmark |
| `app/Jobs/ScorePlaceJob.php` | Pass benchmark to scorer |
| `tests/Unit/GbpScoringServiceTest.php` | Expanded coverage |
| Optional: `app/Services/BenchmarkNormalizer.php` | Shared with `ReportBuilderService` |

---

## Success criteria

- Prospects in the same search are ranked using consistent competitive context.
- Outreach emails can cite specific gaps vs the top local listing (city interpolated).
- No additional Places Details calls per prospect (one benchmark call per search only).
- All new flags appear in `gbp_flags` whenever they contribute points to `gbp_score`.
