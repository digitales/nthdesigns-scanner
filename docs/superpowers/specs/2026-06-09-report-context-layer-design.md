# Public report context layer — Design Spec

**Date:** 2026-06-09  
**Status:** Approved

---

## Goal

Make the public audit report (`/r/{token}`) feel like a helpful partner from the first scroll. Scores, severity counts, and Lighthouse dials should read as business meaning — not internal metrics. The 30-minute call becomes **scoping and relationship** (effort, cost, timeline, fit), not explaining what the report withheld.

## Problem

Prospects see three abstract signal layers today:

1. **Severity chips** — "4 critical · 11 serious · 8 moderate" with no business translation
2. **Overall grade** — letter grade + generic label ("Needs attention") without tying accessibility, GBP, and performance together
3. **Lighthouse dials** — numeric scores with one performance paragraph; other dials unexplained

Violation cards below the fold already carry strong plain-English copy. The gap is the summary layer: it feels like a scoreboard, which makes the CTA ("walk you through every fix") sound like a bait-and-switch.

## Approach (approved)

**Hybrid:** executive summary layer (Approach A) with:

- A **headline finding** sentence upfront (from Approach C)
- **Three dimension summaries** in plain English — no `/100` weakness scores on the public page (from Approach B)
- Existing violation, GBP comparison, and Lighthouse sections kept; Lighthouse gets per-dial interpretation
- **CTA reframed** for scoping, not explanation

## Non-goals

- AI-generated report copy (must be deterministic and snapshot-stable)
- Showing operator weakness scores (`/100`) to prospects
- Compliance/legal framing (EAA) on the report — reserved for the call if raised
- Changing operator prospect detail UI
- Regenerating existing reports automatically (snapshots stay as stored; new logic applies on next report generation)

---

## Information architecture

Replace the current "Overall grade" block content. Section order stays:

```
Header (business name, URL, date)
→ Summary (NEW: headline + grade + context layer)     ← this spec
→ Section 1 · Accessibility (violations)              ← unchanged structure
→ Section 2 · Google Business Profile (comparison)    ← unchanged
→ Section 3 · Site performance (Lighthouse)           ← add dial captions
→ Next step (booking CTA)                             ← reframe copy
→ Footer
```

### Summary section layout

```
┌─────────────────────────────────────────────────────────────┐
│ KEY FINDING (eyebrow)                                       │
│ One sentence — business impact across available dimensions  │
├─────────────────────────────────────────────────────────────┤
│  D          Needs attention                                 │
│             Short lede (issue count + city context)         │
│             [4 likely blocking enquiries] [11 serious] …    │
├─────────────────────────────────────────────────────────────┤
│  WHAT WE FOUND (eyebrow)                                    │
│  Accessibility · High risk — …                              │
│  Google profile · Behind local leader — …                   │
│  Site speed · Slow on mobile — …                            │
│  (only rows for dimensions present in this report)          │
└─────────────────────────────────────────────────────────────┘
```

Grade letter remains visible but secondary to the headline finding.

---

## Data model

New object stored in `report_data` at generation time:

```php
'report_context' => [
    'headline' => string,
    'severity_labels' => [
        ['level' => 'critical', 'count' => 4, 'label' => '4 likely blocking enquiries'],
        ['level' => 'serious', 'count' => 11, 'label' => '11 serious'],
        // ...
    ],
    'dimensions' => [
        [
            'key' => 'accessibility',
            'title' => 'Accessibility',
            'risk' => 'high',           // high | moderate | low — drives colour only
            'summary' => 'Forms and images may fail for screen reader users',
        ],
        [
            'key' => 'gbp',
            'title' => 'Google profile',
            'risk' => 'moderate',
            'summary' => 'Behind local leader on reviews (12 vs 89)',
        ],
        [
            'key' => 'performance',
            'title' => 'Site speed',
            'risk' => 'high',
            'summary' => 'Slow on mobile — visitors may leave before the page loads',
        ],
    ],
    'lighthouse_captions' => [
        'performance' => 'Below 50 — Google starts penalising mobile search',
        'accessibility' => 'Automated check only; manual audit may find more',
        'seo' => 'Below 50 — harder to rank for local searches',
        'best_practices' => 'Below 50 — security or browser compatibility gaps',
    ],
]
```

`PublicReportResource` exposes `report_context` alongside existing fields. Frontend falls back gracefully when absent (older snapshots): current grade + chips layout.

### Snapshot fields added to `report_data`

Also persist scores used for context generation (not displayed as `/100` on public page):

```php
'gbp_score' => int,
'a11y_score' => int,
'scan_type' => string,
```

These may already be inferable but storing them keeps context reproducible from the snapshot alone.

---

## Backend: `ReportContextBuilder`

New service: `App\Services\Reports\ReportContextBuilder`.

**Input:** prospect scores, `violation_summary`, `comparison`, `benchmark`, `lighthouse`, `scan_type`, `city`, `niche`.

**Output:** `report_context` array above.

Called from `ReportBuilderService::build()` after violation summary and comparison are computed.

### Headline generation (rules-based)

Build 1–2 clauses from the strongest available signals, joined with " — while" or " — and":

| Signal present | Clause template |
|----------------|-----------------|
| `critical > 0` | "Your site has {critical} issue(s) that may stop visitors from completing a booking or enquiry" |
| `critical == 0` and `total > 0` | "Your site has {total} accessibility issues worth addressing" |
| GBP benchmark + review gap | "your Google profile trails the top {niche} in {city} on reviews ({you} vs {them})" |
| GBP benchmark + photo gap (no review gap) | "your Google profile has far fewer photos than the top practice in {city}" |
| `performance_score > 0` and `< 50` | "the site loads slowly on mobile" |

**Scan type rules:**

- `gbp_only` — headline uses GBP clause only (no a11y/perf clauses unless site audit was run and payload exists — follow `effectiveScanType`)
- `accessibility_only` — a11y clauses only
- `combined` — merge up to two clauses; prefer critical a11y + strongest GBP gap

Cap at ~220 characters; truncate secondary clause if needed.

Use **"booking or enquiry"** as default outcome language (niche-neutral). Do not attempt per-niche vocabulary in v1.

### Severity label reframing

| Level | Public label when count > 0 |
|-------|----------------------------|
| critical | `{n} likely blocking enquiries` (singular: enquiry) |
| serious | `{n} serious` |
| moderate | `{n} moderate` |
| minor | omit from public chips (operator-only noise) |

Render as existing `SevChip` components where level maps, but pass `label` override when `report_context.severity_labels` provides one.

### Dimension summaries

Only include dimensions relevant to the report (same visibility rules as existing sections: `hasA11yAuditContent`, benchmark present, `performance_score > 0` or lighthouse performance).

**Accessibility** (`key: accessibility`):

- Risk band from `a11y_score`: ≥71 high, ≥41 moderate, else low
- Summary from top violation rule IDs when available:
  - `color-contrast` → "Text and buttons may be hard to read"
  - `image-alt` → "Images missing descriptions for screen reader users"
  - `label` / `link-name` / `button-name` → "Forms or links may be unusable with assistive tech"
  - Fallback: "{critical} critical and {serious} serious issues detected"

**Google profile** (`key: gbp`):

- Risk from `gbp_score` with same thresholds
- Summary prioritises largest gap from `comparison`:
  - Review gap → "Behind local leader on reviews ({you} vs {them})"
  - Photo gap → "Far fewer photos than {benchmark.name} ({you} vs {them})"
  - Rating gap → "Lower rating than local leader ({you} vs {them})"
  - Fallback: "Several GBP gaps vs the top practice in {city}"

**Site speed** (`key: performance`):

- Risk from `performance_score`: <30 high, <50 moderate, else low
- Summary:
  - `< 30` → "Very slow on mobile — many visitors leave before the page loads"
  - `< 50` → "Slow on mobile — may affect search rankings"
  - else → "Acceptable load times; room to improve"

Risk band maps to existing score colour tokens (`high` → critical/warm, `moderate` → serious, `low` → positive). **Do not show numeric score.**

### Lighthouse captions

Generated once at report build; shown under each dial when score `< 70`. Omit caption when score ≥ 70 (dial colour alone is sufficient).

Performance section intro copy stays; per-dial captions supplement it.

---

## Frontend changes

### `Report/Public.jsx`

1. Replace inner content of the first section (currently `public-report-grade-grid`) with a new `ReportSummarySection` component.
2. Pass `report.report_context`, existing grade fields, `violation_summary`, and `city`.
3. If `report_context` is null (legacy snapshot), render current layout unchanged.

### New `ReportSummarySection.jsx`

- Renders headline (`.public-report-headline`)
- Grade row: letter + `grade_label` + lede (reuse existing lede logic for issue count)
- Severity chips from `severity_labels` when present, else existing chip logic
- Dimension list: `.public-report-dimensions` — title + summary per row, optional risk indicator (left border or dot colour)

### `LighthouseDial.jsx`

Accept optional `caption` prop; render `.lighthouse-dial-caption` micro text below SVG when provided.

### CTA copy (static)

| Element | Current | New |
|---------|---------|-----|
| Eyebrow | Next step | Next step |
| Title | A free 30-minute call to walk you through every fix. | Let's scope what fixing this would involve. |
| Body | No obligation. We'll go through the audit findings… | You've seen the findings above. On a free 30-minute call we'll estimate effort, cost, and timeline — and answer any questions. No obligation. |
| Booking confirmed | …We'll walk through the findings… | …We'll talk through priorities and what a fix would involve… |

Update `SampleReportExcerpt.jsx` on the homepage to match the new summary tone (headline + reframed chips) so marketing preview stays aligned.

---

## CSS

Add to `resources/css/components.css` under public report block:

- `.public-report-headline` — serif, ~22px, line-height 1.35
- `.public-report-dimensions` — grid/list with top border separator
- `.public-report-dimension` — flex row; `.public-report-dimension--high|moderate|low` left accent
- `.lighthouse-dial-caption` — centred micro text, max-width on dial column

Follow existing token usage; no new colour tokens.

---

## Testing

### Unit: `ReportContextBuilderTest`

Cases:

- Combined scan with critical violations + review gap → headline mentions both
- GBP-only scan → no accessibility dimension row
- High performance score → low risk performance summary; no lighthouse caption when ≥70
- Zero violations but lighthouse present → a11y dimension omitted; headline falls back appropriately
- Singular/plural enquiry label

### Feature: `PublicReportSnapshotTest`

- Assert `report_context` present in Inertia payload for newly built reports
- Legacy report without `report_context` still returns 200 with old layout

### Manual

- Generate report for a combined-scan prospect with material violations
- Confirm no `/100` appears in summary section
- Confirm CTA reads as scoping, not teaser
- Mobile layout: dimension list wraps cleanly at 375px width

---

## Rollout

1. Ship backend builder + snapshot fields
2. Ship frontend summary section with fallback
3. Update homepage sample excerpt + CTA copy
4. New reports only — no backfill command in v1

---

## Open questions (resolved)

| Question | Decision |
|----------|----------|
| Show `/100` weakness to prospects? | No — qualitative risk + concrete gaps only |
| Call delivers what? | Scoping (effort/cost/timeline) + relationship |
| AI headline? | No — rules-based for snapshot stability |
| Compliance on report? | Out of scope for v1 |
