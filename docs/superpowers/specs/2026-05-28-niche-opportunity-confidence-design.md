# Niche Opportunity Score — Low Result Count Confidence — Design Spec

**Date:** 2026-05-28  
**Status:** Approved (brainstorming)  
**Scope:** Penalise `opportunity_score` when Google Places returns too few results for a niche × city pair, so single-business samples no longer rank as top opportunities. Includes one-off backfill of existing `niche_scans` rows.

**Parent spec:** `docs/superpowers/specs/2026-05-27-niche-opportunity-scanner-design.md`

**Approach:** Extend `ScanNicheJob::opportunityScore()` with a tiered multiplier based on `result_count`; add `niches:recalculate-scores` command for backfill. No schema or UI changes in v1.

---

## Problem

The opportunity score formula weights GBP weakness and sample percentages:

```text
raw = (avg_gbp_score × 0.4) + (pct_no_website × 0.35) + (pct_low_reviews × 0.25)
```

With **one** Places result, the sample is a single business. Percentages become 100% or 0%, producing scores of 80–88 even though the pair has no meaningful **prospect density**. Zero results already yield `opportunity_score = 0`; one result does not.

Example from production data:

| Niche | City | Results | Raw score | Issue |
|-------|------|---------|-----------|-------|
| Spark | Gloucester | 1 | 88 | One weak listing, not a market |
| Osteopath | Grimsby | 1 | 81 | Same |
| General Contractor | Burnley | 54 | 84 | Legitimate high-density signal |

---

## Goal

Align stored and displayed `opportunity_score` with the original intent (“better prospect density”) by penalising niche × city pairs where Places returns too few results to represent a market.

---

## Decisions (from brainstorming)

| Topic | Decision |
|-------|----------|
| Strategy | Penalise score when `result_count` is below minimum confidence |
| Minimum for full confidence | **3** results |
| Penalty shape | **Tiered** (not linear scale or hard cap) |
| Penalty basis | `result_count` from Places search, not `sampled_count` |
| Existing rows | **One-off backfill** via artisan command |
| UI | No change — adjusted score only; no badge or tooltip in v1 |
| Raw score storage | Not stored separately — recompute from aggregates on backfill |

---

## Scoring

### Raw score (unchanged)

```text
raw = (avg_gbp_score × 0.4) + (pct_no_website × 0.35) + (pct_low_reviews × 0.25)
```

Range 0–100. Computed only when `sampled_count > 0`. When `sampled_count === 0`, `opportunity_score = 0` regardless of `result_count` (existing behaviour).

### Tiered adjustment

Applied after raw score is computed, using `result_count`:

| `result_count` | Adjusted `opportunity_score` |
|----------------|------------------------------|
| 0 | 0 |
| 1 | 0 |
| 2 | `raw × 0.5` |
| ≥ 3 | `raw` |

Rounded to 2 decimal places (existing convention).

### Implementation

Extend `ScanNicheJob::opportunityScore()`:

```php
public static function opportunityScore(
    float $avgGbp,
    float $pctNoWebsite,
    float $pctLowReviews,
    int $resultCount,
): float
{
    if ($resultCount <= 1) {
        return 0.0;
    }

    $raw = ($avgGbp * 0.4) + ($pctNoWebsite * 0.35) + ($pctLowReviews * 0.25);

    if ($resultCount === 2) {
        $raw *= 0.5;
    }

    return round($raw, 2);
}
```

### Call sites

1. **`NicheSampleCollector::collect()`** — pass `$resultCount` when calling `opportunityScore()`.
2. **`niches:recalculate-scores`** — recompute from stored aggregates (see Backfill).

No other consumers of `opportunityScore()` expected; grep before merge.

---

## Backfill

### Command

**Signature:** `php artisan niches:recalculate-scores {--dry-run}`

**Logic:**

1. Query `niche_scans` where `status = 'complete'`.
2. For each row, call `ScanNicheJob::opportunityScore($avg_gbp_score, $pct_no_website, $pct_low_reviews, $result_count)`.
3. If `--dry-run`, print niche, city, old score, new score, and summary counts; do not write.
4. Otherwise, update `opportunity_score` only. Leave aggregates and `sample_preview` unchanged.

**Properties:**

- Idempotent — safe to re-run.
- Run once after deploy; new scans use updated logic automatically.
- Rows with `sampled_count = 0` already have `opportunity_score = 0`; backfill preserves 0.

---

## UI

No frontend changes. The `/niches` table continues to display `opportunity_score`. Penalised rows sort lower by default (sort by opportunity desc). Operators see lower numbers for thin markets without additional labelling in v1.

---

## Testing

### Unit / static method tests

Add cases to existing test coverage for `opportunityScore()`:

| `result_count` | Expected behaviour |
|----------------|-------------------|
| 0 | 0 |
| 1 | 0 (even if raw inputs would yield high score) |
| 2 | half of raw |
| 3+ | full raw |

Use fixed aggregate inputs so expected outputs are deterministic.

### Feature tests

1. **`ScanNicheJobTest`** — HTTP fake returning 1 place → `opportunity_score = 0` after job completes.
2. **`ScanNicheJobTest`** (or new test) — 2 places, weak sample → score equals half of raw formula.
3. **`RecalculateNicheScoresCommandTest`** (new) — seed rows with known aggregates and `result_count`; assert command updates scores; assert `--dry-run` makes no DB changes.

---

## Out of scope

- Filtering or hiding low-`result_count` rows in the UI.
- Storing `raw_opportunity_score` in a separate column.
- Changing niche query/config quality (e.g. broad bootstrap queries like `"spark"`).
- Re-running Places API for backfill — aggregates already stored are sufficient.

---

## Rollout

1. Deploy code with updated `opportunityScore()` and backfill command.
2. Run `php artisan niches:recalculate-scores --dry-run` and review diff summary.
3. Run `php artisan niches:recalculate-scores`.
4. Verify `/niches` sort — former 1-result high scores should no longer appear near the top.
