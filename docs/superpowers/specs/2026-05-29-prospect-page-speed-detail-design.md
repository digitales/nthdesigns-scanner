# Prospect Detail — Page Speed Score + Breakdown — Design Spec

**Date:** 2026-05-29  
**Status:** Approved (brainstorming)  
**Screen:** C — `/prospects/{id}` (`Prospect/Show.jsx`)

---

## Goal

Show **Lighthouse-native page speed** on the prospect detail page alongside GBP and Accessibility scores, plus an operator-facing breakdown of **Core Web Vitals** and **top Lighthouse opportunities** with high-impact items highlighted.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Score direction | **Lighthouse-native (A):** raw `performance_score` (0–100, higher = faster site) |
| Score card label | **"Page speed"** with `/100` delta |
| Detail depth | **Core Web Vitals + top opportunities (B):** LCP, INP/TBT, CLS, FCP + up to 8 failing audits |
| Layout | **Separate Page speed card (approach 1):** between Weakness flags and Site audit |
| Data capture | **Extend `audit.js`** to extract metrics + opportunities from Lighthouse JSON; store in existing `raw_lighthouse_payload` |
| Backend shaping | **`ReportBuilderService::buildOperatorPageSpeed()`** — do not send raw Lighthouse JSON to the frontend |
| Legacy payloads | Score card shows stored score; detail section hidden with re-run hint when metrics/opportunities absent |
| Duplicate Performance card | **Remove** Performance from `LIGHTHOUSE_METRICS` score grid; keep SEO / Lighthouse a11y / Best practices as secondary cards |
| Public report | **Out of scope (v1)** — operator detail only |

---

## UX

### Page structure (updated)

1. Score cards — Combined · GBP · Accessibility · **Page speed** (+ secondary Lighthouse cards for SEO / a11y / best practices when present)  
2. **Weakness flags** card — unchanged  
3. **Page speed** card — **new**, conditional  
4. **Site audit** card — unchanged (accessibility focus)  
5. Outreach emails — unchanged  
6. Right sidebar — unchanged  

### Score card row

| Card | Value | Styling |
|------|-------|---------|
| Combined | `combined_score` | Unchanged — highlight when ≥ 71 |
| GBP | `gbp_score` | Unchanged — weakness score |
| Accessibility | `a11y_score` | Unchanged — weakness score |
| **Page speed** | `performance_score` | `healthScore` prop — &lt;50 critical, &lt;70 serious, ≥70 positive |

- Show **—** when `performance_score` is 0 or audit is pending/failed.
- Hidden on `gbp_only` scans.
- Remove `Performance` from the `LIGHTHOUSE_METRICS` loop to avoid duplication.

### Page speed detail card

**Title:** "Page speed"

**Visibility:**

| State | Score card | Detail card |
|-------|------------|-------------|
| `gbp_only` scan | Hidden | Hidden |
| Audit pending / failed | — or hidden | Hidden |
| Audit complete, score only (legacy payload) | Shows score | Hidden + micro note: *"Re-run site audit for Core Web Vitals breakdown"* |
| Audit complete, full payload | Shows score | Full section |

#### Subsection 1 — Core Web Vitals

Compact 4-column metric row (2×2 on narrow viewports):

| Metric | Lighthouse audit ID | Notes |
|--------|---------------------|-------|
| LCP | `largest-contentful-paint` | Display `displayValue` (e.g. 3.2 s) |
| INP/TBT | `interaction-to-next-paint` preferred, else `total-blocking-time` | Display ms |
| CLS | `cumulative-layout-shift` | Display unitless value |
| FCP | `first-contentful-paint` | Display `displayValue` |

Each metric includes a **rating** for color:

- `good` → `--color-positive`
- `needs_improvement` → `--color-sev-serious`
- `poor` → `--color-sev-critical`

Micro line: *"Measured via Google Lighthouse · mobile"*

#### Subsection 2 — Opportunities

Eyebrow: **"Opportunities"**

Up to **8** failing audits sorted by estimated savings (ms first, then KiB).

Each row:

| Field | Content |
|-------|---------|
| Title | Audit title |
| Savings | Right-aligned — e.g. "Est. savings 1.2 s" or "340 KiB" |
| Description | One-line description, truncated ~120 chars |

**Highlighting:**

- `highlight: true` when `savings_ms >= 500` — terracotta left border + `--color-sev-critical-soft` background.
- Other failing opportunities use neutral styling.

Empty state: *"No significant opportunities detected"* when all opportunity audits pass.

### Styling

- Reuse operator tokens: `Card`, `eyebrow`, `micro`, severity colors.
- Denser than public report — list/table layout, not marketing sections.
- No Lighthouse dials in this section (score card above is sufficient).

### Out of scope (v1)

- Public report page speed section
- PageSpeed Insights API
- SEO / Best practices detail breakdown
- Diagnostics or passed-audit lists
- Historical perf trend / sparkline

---

## Data & API

### Audit capture (`scripts/audit.js`)

Extend `runLighthouse()` to parse the full Lighthouse JSON before returning.

**Category scores** (unchanged):

```json
{
  "performance": 28,
  "accessibility": 60,
  "seo": 70
}
```

**New — `metrics` object:**

```json
{
  "lcp": { "value_ms": 3200, "display": "3.2 s", "rating": "poor" },
  "inp": { "value_ms": 180, "display": "180 ms", "rating": "good" },
  "cls": { "value": 0.14, "display": "0.14", "rating": "needs_improvement" },
  "fcp": { "value_ms": 1800, "display": "1.8 s", "rating": "needs_improvement" }
}
```

- INP preferred over TBT when `interaction-to-next-paint` exists; fall back to `total-blocking-time`.
- `rating` from Lighthouse audit score: `>= 0.9` → good, `>= 0.5` → needs_improvement, else poor.
- Omit individual metrics when the audit is missing from the report.

**New — `opportunities` array** (max 8):

```json
[
  {
    "id": "unused-javascript",
    "title": "Reduce unused JavaScript",
    "description": "Remove unused JavaScript to reduce bytes consumed by network activity.",
    "savings_ms": 1200,
    "savings_display": "Est. savings 1.2 s"
  }
]
```

Selection rules:

- Include audits where `score !== null && score < 0.9` and (`details.type === 'opportunity'` or `scoreDisplayMode === 'metricSavings'`).
- Sort by `overallSavingsMs` descending; KiB-only opportunities after ms-based ones.
- Cap at 8 in the script to bound payload size.

Stored in existing `raw_lighthouse_payload` JSON column — **no migration**.

### Backend

**`ReportBuilderService::buildOperatorPageSpeed(Prospect $prospect): ?array`**

Returns `null` when the detail section should not render. Otherwise:

```php
[
    'audited_at'    => string,   // ISO8601
    'url'           => string,
    'metrics'       => [
        'lcp' => ['display' => string, 'rating' => string]|null,
        'inp' => ['display' => string, 'rating' => string]|null,
        'cls' => ['display' => string, 'rating' => string]|null,
        'fcp' => ['display' => string, 'rating' => string]|null,
    ],
    'opportunities' => list<[
        'id'              => string,
        'title'           => string,
        'description'     => string,
        'savings_display' => string,
        'savings_ms'      => int,
        'highlight'       => bool,  // savings_ms >= 500
    ]>,
    'has_detail'    => bool,     // false when only category scores stored (legacy)
]
```

- `audited_at` / `url`: same source as `buildOperatorAudit` (completed accessibility job or `prospect.updated_at`; payload URL or `website_url`).
- `has_detail: false` when payload lacks `metrics` and `opportunities` — frontend shows re-run hint under score card only.
- `highlight` computed in PHP from `savings_ms >= 500`.

**`ProspectController::show`**

Add Inertia prop:

```php
'pageSpeed' => $reportBuilder->buildOperatorPageSpeed($prospect),
```

Frontend: render `PageSpeedSection` when `pageSpeed !== null`.

### Frontend files

| File | Role |
|------|------|
| `resources/js/Components/audit/PageSpeedSection.jsx` | **New** — CWV row + opportunities list |
| `resources/js/Pages/Prospect/Show.jsx` | Page speed score card; remove Performance from `LIGHTHOUSE_METRICS`; render `PageSpeedSection` |
| `resources/js/Components/ui/ScoreCard.jsx` | Reuse `healthScore` prop — no change required |

Optional: extract shared `healthScoreColor()` util from inline `PerfScore` in `Search/Show.jsx`.

---

## Edge cases

| Condition | Behaviour |
|-----------|-----------|
| Lighthouse unavailable (`null`) | Score card **—**; detail hidden |
| Legacy prospects (score only, no metrics) | Score card shows value; detail hidden + re-run hint |
| `raw_lighthouse_payload` purged | Both hidden |
| Re-audit in progress | Score card shows last score until new audit completes |
| `gbp_only` search | Both hidden |
| Audit complete, zero opportunities | Detail shown with CWV + empty-state message |
| Missing individual CWV metric | Omit that metric cell; show remaining metrics |

### Backfill

Existing `scanner:backfill-audits --execute` re-runs audits and populates new fields after deploy. No separate backfill command.

---

## Testing

### Unit / script

- Lighthouse JSON fixture → `audit.js` extracts correct metrics and opportunities (order, cap, ratings).
- `ReportBuilderServiceTest`: `buildOperatorPageSpeed` with full payload, legacy score-only payload, null payload, highlight threshold.

### Feature

- `ProspectShowTest`: Inertia includes `pageSpeed` with expected shape when complete audit has full lighthouse payload; null when pending/failed/gbp_only.

### Manual

- Prospect with complete audit: Page speed score card + detail section visible between flags and Site audit.
- Legacy prospect (score only): score card visible, re-run hint shown, no detail section.
- Pending/failed prospect: score card shows **—**; no detail section.

---

## Implementation notes

- Extend `audit.js` first — without new payload fields the detail section cannot render.
- Keep shaped arrays small; never send raw Lighthouse JSON to Inertia.
- `performance_score` on `prospects` remains the canonical score for combined scoring, search table, and outreach — unchanged.
- After Fly/browser-service deploy, run `scanner:backfill-audits --execute` to populate metrics for existing prospects.

---

## References

- `scripts/audit.js` — Lighthouse capture (extend here)
- `app/Services/ReportBuilderService.php` — add `buildOperatorPageSpeed`
- `app/Jobs/AuditSiteJob.php` — stores `raw_lighthouse_payload`, `performance_score`
- `resources/js/Pages/Prospect/Show.jsx` — score cards + section placement
- `resources/js/Pages/Search/Show.jsx` — `PerfScore` health color convention
- `docs/design/design_handoff_prospect_scanner/README.md` — Perf column convention break
- `docs/superpowers/specs/2026-05-28-production-lighthouse-performance-design.md` — Lighthouse infra on Fly
