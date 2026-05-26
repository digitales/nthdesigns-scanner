# Plan completion — Design Spec

**Date:** 2026-05-26  
**Status:** Approved — pending spec file review  
**Scope:** Close remaining gaps from `docs/concept/nthdesigns-prospect-scanner-plan.md`: Phase 6 hardening, Phase 7 performance signal, plan-faithful scoring, public report polish.

**Approach:** Plan-faithful scoring (single coherent slice). Skip the plan’s “4 weeks internal use” gate for Phase 7.

---

## Goal

Bring the scanner to plan-complete for internal operator use: scoring matches the business plan, performance is a third combined signal without double-counting, outreach and search UI surface slow sites, Phase 6 operational hardening is finished, and public reports meet §2.6 without exposing internal scores.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Combined weights | `combined = round(0.35 × gbp + 0.50 × a11y + 0.15 × performance_weakness)` for `scan_type = combined` (replaces current 50/50 average) |
| `performance_weakness` | `100 - performance_score` at combine time; `0` if Lighthouse performance missing |
| `performance_score` column | Raw Lighthouse 0–100 (higher = better site) — unchanged storage |
| `dominant_angle` | `a11y > 70` → `accessibility`; `gbp > 70` → `gbp`; else `both` (replaces ±15 margin) |
| A11y scoring | Remove Lighthouse performance bonuses from `A11yScoringService` (avoid double-count with Phase 7) |
| Phase 7 outreach | Secondary sentence in email when `performance_score > 0 && performance_score < 30`; never the opener |
| Phase 7 prerequisite | Skipped — implement now |
| Settings page | Keep (already built); supersedes operator-ui spec “out of scope” |
| Historical prospects | Re-score on next `CombineScoresJob` only; no backfill migration |
| Horizon | `HORIZON_ALLOWED_EMAILS` env (comma-separated); `viewHorizon` gate; local may allow all authenticated users |

---

## Scoring

### `CombineScoresService`

**`gbp_only`**

- `combined_score = gbp_score`
- `dominant_angle = gbp`

**`accessibility_only`**

- `combined_score = a11y_score`
- `dominant_angle = accessibility`

**`combined`**

```php
$perfWeakness = $prospect->performance_score > 0
    ? 100 - (int) $prospect->performance_score
    : 0;

$combined = (int) round(
    ($gbp * 0.35) + ($a11y * 0.50) + ($perfWeakness * 0.15)
);

// dominant_angle — performance does not affect angle
if ($a11y > 70) {
    $dominant = 'accessibility';
} elseif ($gbp > 70) {
    $dominant = 'gbp';
} else {
    $dominant = 'both';
}
```

Extract `performanceWeakness()` as a private method for unit testing.

### `A11yScoringService`

Remove the block that adds score/flags when Lighthouse `performance < 50` or `< 70`. Keep axe violation scoring and optional Lighthouse **accessibility** audit flag (`lhA11y < 70`).

---

## Phase 7 — UI and outreach

### Search results (`Search/Show.jsx`)

When `scan_type !== 'gbp_only'`, add **Perf** column showing raw `performance_score`:

- Red text/bg tint: `< 50`
- Amber: `50–69`
- Green: `≥ 70`
- Em dash when `0` or audit skipped/failed without score

`SearchController` already passes `performance_score`; no API change required.

### Outreach (`OutreachEmailGeneratorService`)

In `buildUserPrompt()`, after existing lines, when `performance_score > 0 && performance_score < 30`:

```
Add exactly one secondary sentence (not the opening) noting their site scored {N}/100 on Google's performance benchmark and that slow load times affect rankings and bounce rate.
```

Applies regardless of `pitch_angle`. `dominant_angle` / pitch template selection unchanged.

---

## Phase 6 — Hardening

| Item | Implementation |
|------|----------------|
| Purge | Keep `scanner:purge-expired` + daily schedule |
| Rate limit | Keep `SEARCH_RATE_LIMIT_SECONDS` on search create |
| Settings | Keep `/settings` (booking URL, agency name, API health) |
| Horizon | `config/scanner.php` → `horizon_allowed_emails` from `HORIZON_ALLOWED_EMAILS`; `HorizonServiceProvider::gate()` checks allowlist; remove redundant `Horizon::auth()` override or align with gate |
| `.env.example` | Document `HORIZON_ALLOWED_EMAILS`, `SEARCH_RATE_LIMIT_SECONDS`, purge-related vars |
| Smoke tests | Unit/feature tests without real subprocesses (see Testing) |

---

## Public report polish (`/r/{token}`)

### `ReportBuilderService::extractTopViolations()`

Add per violation:

- `user_impact` — plain English (static map by axe `id`, ~10 common rules + generic fallback)
- `fix_hint` — one-line fix (same map)

### `ReportBuilderService::build()` — benchmark comparison

Include in `prospect` snapshot and `benchmark` where available:

- `has_description` (boolean)
- `hours_complete` (boolean)

### `ReportBuilderService::extractLighthouse()`

Add `best_practices` from Lighthouse payload when present.

### `Public.jsx`

- Render `user_impact` and `fix_hint` under each top violation
- GBP panel: show Description and Hours rows for both columns
- Lighthouse grid: Performance, Accessibility, SEO, Best practices (4 dials when data exists)
- When `lighthouse.performance < 30`, show footnote under performance section: slow load affects rankings and bounce rate (no internal weakness scores)

Existing reports keep stored `report_data` until regenerated.

---

## Config

```env
# Comma-separated emails allowed to view Horizon in non-local environments
HORIZON_ALLOWED_EMAILS=you@nthdesigns.co.uk
```

```php
// config/scanner.php
'horizon_allowed_emails' => array_filter(array_map('trim', explode(',', env('HORIZON_ALLOWED_EMAILS', '')))),
```

---

## Testing

| Test | Asserts |
|------|---------|
| `CombineScoresServiceTest` | New weights; `performance_weakness` term; dominant `> 70` thresholds; perf weakness helper |
| `A11yScoringServiceTest` | No score bump for low Lighthouse performance alone |
| `OutreachEmailGeneratorServiceTest` (new) | Prompt contains performance instruction when score 25; absent when 80 |
| `ReportBuilderServiceTest` | `user_impact` / `fix_hint` present; `best_practices` in lighthouse; GBP description/hours in payload |
| Existing | `PurgeExpiredProspectDataTest`, `SearchRateLimitTest`, `SettingsTest` remain green |

---

## Out of scope

- Laravel AI SDK migration
- SaaS billing, multi-user teams, white label
- `report.nthdesigns.co.uk` subdomain
- Bulk re-score migration for existing prospects
- Comprehensive axe rule copy library (expand map later)
- Public report redesign beyond items above

---

## Implementation order

1. `A11yScoringService` — remove perf bonuses + tests
2. `CombineScoresService` — weights, weakness, dominant_angle + tests
3. `OutreachEmailGeneratorService` — performance prompt line + test
4. `Search/Show.jsx` — Perf column
5. `ReportBuilderService` + `Public.jsx` — violation copy, GBP metrics, best practices dial, perf footnote
6. `HorizonServiceProvider` + config + `.env.example`
7. Verify purge schedule and commit any pending Phase 6 files
8. Run full test suite

---

## Success criteria

- Combined search prospects use `0.35/0.50/0.15` formula; slow sites rank higher than before when a11y/gbp similar
- `dominant_angle` follows `> 70` rule; performance never becomes a pitch angle
- Outreach for `performance_score < 30` includes one secondary performance sentence
- Search table shows Perf column for non–GBP-only scans
- Public report shows impact/fix copy, full GBP comparison fields, four Lighthouse dials when data exists
- Horizon restricted by email allowlist in production
- All tests pass
