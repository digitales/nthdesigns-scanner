# Prospect Detail — Site Audit (Accessibility + Lighthouse) — Design Spec

**Date:** 2026-05-27  
**Status:** Approved (brainstorming)  
**Screen:** C — `/prospects/{id}` (`Prospect/Show.jsx`)

---

## Goal

Show **operator-complete** site audit data on the prospect detail page: axe violation summary, top issues with copy and screenshots, the **full violation list**, pass/incomplete counts, Lighthouse metrics (performance, accessibility, SEO), and audit metadata. Reuse the same shaping logic as the public report so operator and client views stay aligned.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Depth | **Operator-complete (B):** summary + top 5 + all violations + pass/incomplete + Lighthouse + metadata |
| Layout | **Keep weakness flags + add section below (B):** short `a11y_flags` unchanged; new **Site audit** card underneath |
| Visibility | **Hide when empty (A):** section only when `audit_status === 'complete'` and audit payload exists |
| Failed / pending audits | No Site audit section; operators rely on scores and summary flags only |
| Backend approach | **Reuse `ReportBuilderService`** — new `buildOperatorAudit(Prospect)`; do not send raw JSON to the frontend |
| UI reuse | Extract `ViolationCard` and `LighthouseDial` from `Report/Public.jsx` into shared audit components |
| Raw payload expiry | If payloads purged, treat as no audit — section hidden |

---

## UX

### Page structure

1. Score cards (Combined, GBP, Accessibility) — unchanged  
2. **Weakness flags** card (GBP + short a11y flags) — unchanged  
3. **Site audit** card — **new**, conditional  
4. Outreach emails — unchanged  
5. Right sidebar — unchanged  

### Site audit card

Rendered only when:

- `prospect.audit_status === 'complete'`, and  
- `raw_a11y_payload` is present and non-empty (or lighthouse data exists in stored payloads).

Subsections (top to bottom, operator density — not public-report marketing layout):

| Subsection | Content |
|------------|---------|
| **Header** | Title “Site audit”; audited date; audited URL (truncated, links externally) |
| **Summary** | `SevChip` counts (critical / serious / moderate / minor); micro line: “{pass_count} passes · {incomplete_count} incomplete checks” |
| **Lighthouse** | Dials for performance, accessibility, SEO; `best_practices` only if present in stored data |
| **Priority issues** | Up to 5 `ViolationCard` entries (same content as public report: WCAG, user impact, fix hint, screenshot when stored) |
| **All violations** | Compact table: impact, rule id, description, WCAG tag, node count; sorted critical → minor |

**All violations table behaviour:**

- Default: show all rows sorted by impact.  
- If more than 15 rows: add a simple filter toggle “Hide moderate & minor” (default off) to reduce scroll.  

**Performance score:** Show `prospect.performance_score` near Lighthouse when Lighthouse performance dial is absent or differs (micro label “Scanner score”) — avoids confusion when Lighthouse CLI was unavailable.

### Styling

- Use existing operator tokens: `Card`, `eyebrow`, `SevChip`, `scoreBand` / dial styles from public report.  
- Denser padding than `/r/{token}`; no full-width 80px marketing sections.

### Out of scope (v1)

- Re-run audit / retry button on this page  
- Audit timeline grid from design prototype (discovered → GBP scored → …)  
- Tabbed or accordion layout (layout C)  
- Inline axe node selectors or HTML snippets in the full list  
- Best practices Lighthouse score unless already in stored payload  
- Error panel for `audit_status === 'failed'` (hidden per visibility rule)

---

## Data & API

### Backend

**`ReportBuilderService`**

Add `buildOperatorAudit(Prospect $prospect): ?array`:

- Returns `null` when audit should not display (not complete, missing payload, or purged data).  
- Otherwise returns shaped array (see contract below).

Reuse existing methods:

- `summarizeViolations($a11yPayload)`  
- `extractTopViolations($a11yPayload, 5)`  
- `extractLighthouse($lighthousePayload, $a11yPayload)`  

Add:

- `extractAllViolations(array $payload): array` — same per-violation shape as `extractTopViolations`, no limit, sorted by impact (critical → minor).

**`audited_at`:** `audit_jobs` row where `job_type = 'accessibility'` and `status = 'complete'` → `completed_at`; fallback `prospect.updated_at`.

**`ProspectController::show`**

- Eager-load `auditJobs` only when needed, or query latest completed accessibility job.  
- Add top-level Inertia prop:

```php
'audit' => $reportBuilder->buildOperatorAudit($prospect),
```

Frontend: render `Site audit` when `audit !== null`.

### `audit` payload contract

```php
[
    'audited_at'        => string, // ISO8601
    'url'               => string,
    'summary'           => ['critical' => int, 'serious' => int, 'moderate' => int, 'minor' => int, 'total' => int],
    'pass_count'        => int,
    'incomplete_count'  => int,
    'top_violations'    => list<violation>, // max 5
    'all_violations'    => list<violation>, // unlimited
    'lighthouse'        => ['performance' => ?int, 'accessibility' => ?int, 'seo' => ?int, 'best_practices' => ?int],
    'performance_score' => int,
]
```

**Per-violation shape** (unchanged from public report):

```php
[
    'id'             => string,
    'impact'         => string,
    'description'    => string,
    'help'           => ?string,
    'wcag'           => ?string,
    'nodes'          => int,
    'screenshot_url' => ?string,
    'user_impact'    => string,
    'fix_hint'       => string,
]
```

Screenshot URLs come from `violation_screenshots` in `raw_a11y_payload` (already stored on public disk via `ScreenshotStorageService`).

### Frontend files

| File | Role |
|------|------|
| `resources/js/Components/audit/ViolationCard.jsx` | Extracted from `Report/Public.jsx` |
| `resources/js/Components/audit/LighthouseDial.jsx` | Extracted from `Report/Public.jsx` |
| `resources/js/Components/audit/SiteAuditSection.jsx` | Orchestrates prospect Site audit card |
| `resources/js/Components/audit/ViolationsTable.jsx` | Full violation list table |
| `resources/js/Pages/Prospect/Show.jsx` | Render `SiteAuditSection` when `audit` prop set |
| `resources/js/Pages/Report/Public.jsx` | Import shared audit components |

---

## Edge cases

| Condition | Behaviour |
|-----------|-----------|
| `audit_status` pending / running / failed / skipped | No Site audit section |
| No `website_url` | No site audit (typically skipped) — section hidden |
| Complete audit, zero violations | Section shown; summary shows 0 issues; passes/incomplete + Lighthouse still shown |
| Lighthouse `null` (CLI missing) | Lighthouse subsection omitted; axe content still shown |
| No screenshot for rule | `ViolationCard` without image |
| `raw_a11y_payload` purged (`expires_at`) | `buildOperatorAudit` returns null — section hidden |
| GBP-only search | Section hidden if no a11y payload |

---

## Testing

### Unit

- `ReportBuilderServiceTest`: `buildOperatorAudit` returns null when not complete; returns full shape when complete.  
- `extractAllViolations`: impact ordering, screenshot URL mapping, empty violations.  

### Feature

- `ProspectController` show: includes `audit` key when prospect factory has complete audit + payload; omits when pending/failed.  

### Manual

- Open a prospect with complete audit: Site audit visible below flags; public report still renders.  
- Open pending/failed prospect: no Site audit section.  

---

## Implementation notes

- Prefer extracting shared components in the same PR as the prospect page work to avoid drift.  
- Do not increase Inertia payload with raw axe JSON — shaped arrays only.  
- `performance_score` on prospect is already populated by `AuditSiteJob` from Lighthouse when available; display for operator clarity.

---

## References

- `app/Services/ReportBuilderService.php` — violation + lighthouse shaping  
- `app/Jobs/AuditSiteJob.php` — stores `raw_a11y_payload`, `raw_lighthouse_payload`  
- `resources/js/Pages/Report/Public.jsx` — reference UI for violations and Lighthouse  
- `scripts/audit.js` — axe + Lighthouse capture  
- `docs/design/prototype.html` — prospect detail mock (flags + audit timeline; timeline out of scope)
