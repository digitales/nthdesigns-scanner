# Prospect enrichment — Design Spec

**Date:** 2026-05-28  
**Status:** Implemented  
**Scope:** Operator manual edits to prospect contact/profile fields, private note log, GBP rescore on save, automatic site audit when `website_url` changes, manual public report regeneration.

**Approach:** Focused `ProspectEnrichmentService` + `prospect_notes` table + prospect detail UI. Reuse existing audit pipeline (`AuditSiteJob` → `CombineScoresJob`). Suppress auto-report only for operator-triggered audits via `suppress_auto_report` flag.

---

## Goal

Some prospects from Google Places lack a website or phone number. Operators research contact details manually and need to:

1. Edit **business name**, **phone**, **website URL**, and **address** on the prospect record.
2. Add **private notes** (timestamped log, not visible on public reports).
3. When a website is added or changed, **automatically re-run the site audit** (for `accessibility_only` / `combined` searches).
4. **Recalculate GBP weakness scores** immediately when contact fields change.
5. **Regenerate the public report manually** after the audit completes (no auto-report on operator-triggered audit completion).

---

## Decisions

| Topic | Decision |
|-------|----------|
| Editable fields | `business_name`, `phone`, `website_url`, `address` |
| Notes | Private log — add-only entries, newest first; never on public report or outreach |
| Website URL change | Any change (including empty → URL, URL A → URL B) triggers audit reset + `AuditSiteJob` when scan type requires a11y |
| Report after operator audit | Manual only — `suppress_auto_report` prevents `CombineScoresJob` from dispatching `GenerateProspectReportJob` |
| GBP scores | Recalculate immediately on every successful profile save |
| UI location | Prospect detail page (`/prospects/{id}`) only for v1 |
| Notes v1 | Add-only (no edit/delete of entries) |
| Concurrent audit | Reject profile save if `audit_status === 'pending'` |

---

## Architecture

### New components

| Component | Responsibility |
|-----------|----------------|
| `prospect_notes` migration + `ProspectNote` model | Store private operator notes |
| `ProspectEnrichmentService` | Validate/normalize input, persist fields, GBP rescore, detect website change, reset audit fields, set `suppress_auto_report`, dispatch `AuditSiteJob` |
| `GbpScoringService::scoreProspect(Prospect $prospect)` (or equivalent) | Overlay saved `phone` / `website_url` onto `raw_gbp_payload` before scoring |
| `UpdateProspectRequest` | Validation rules |
| `ProspectController@update` | PATCH handler |
| `ProspectNoteController@store` | POST note handler |
| `CombineScoresJob` | Skip `GenerateProspectReportJob` when `suppress_auto_report` is true; clear flag after combine |
| `Prospect/Show.jsx` | Editable profile card, notes card, audit/report status hints |

### Unchanged (reused)

- `AuditSiteJob`, `CombineScoresJob`, `GenerateProspectReportJob`, `CaptureScreenshotJob`
- Existing `POST /prospects/{prospect}/report` for manual report regeneration
- `ProspectPolicy::update` (same ownership as `view`)

---

## Data model

### `prospect_notes`

```
id
prospect_id   FK → prospects, cascade on delete
user_id       FK → users
body          text (max 5000)
created_at
updated_at
```

Index: `(prospect_id, created_at)` for listing.

### `prospects` — new column

```
suppress_auto_report   boolean, default false
```

Set `true` when an operator save changes `website_url` and dispatches `AuditSiteJob`. Cleared to `false` in `CombineScoresJob` after combine (whether or not report was generated).

---

## API

### `PATCH /prospects/{prospect}`

**Body (all optional, at least one required):**

| Field | Rules |
|-------|--------|
| `business_name` | required if present; string, max 255 |
| `phone` | nullable; string, max 50 |
| `website_url` | nullable; valid URL; normalize to `https://` scheme |
| `address` | nullable; string, max 500 |

**Authorization:** `ProspectPolicy::update`

**Flow (`ProspectEnrichmentService::update`):**

1. If `audit_status === 'pending'`, abort with validation error (409 or 422): audit in progress.
2. Persist allowed fields.
3. **GBP rescore:** Build scoring payload from `raw_gbp_payload` with operator `phone` / `website_url` overlaid; run `GbpScoringService::score($payload, $search->benchmark_snapshot, $search->city)` (same as `ScorePlaceJob`). Update `gbp_score`, `gbp_flags`.
4. **Re-combine:** Run `CombineScoresService` with current `a11y_score` / flags (may be stale until audit finishes) → update `combined_score`, `dominant_angle`.
5. **Website URL changed** (normalized comparison vs previous value):
   - If scan `scan_type` ∈ `accessibility_only`, `combined` **and** new URL non-empty:
     - Reset audit fields (match `scanner:backfill-audits` execute step): `audit_status` → `pending`, `raw_a11y_payload` → null, `raw_lighthouse_payload` → null, `a11y_score` → 0, `a11y_flags` → null, `performance_score` → 0
     - `suppress_auto_report` → `true`
     - `AuditSiteJob::dispatch($prospect)`
   - If URL cleared: no audit dispatch; GBP rescore already reflects “No website listed”
6. If scan `scan_type` is `gbp_only`: rescore only, no audit dispatch regardless of URL.

**Response:** Redirect back (Inertia) with flash success; include `audit_queued: true` in flash when audit dispatched.

### `POST /prospects/{prospect}/notes`

**Body:** `{ body: string }` — required, max 5000

**Authorization:** `ProspectPolicy::view` (same owner)

Creates `ProspectNote` with `user_id` = auth user. Redirect back with flash.

### Report regeneration

No new endpoint. Existing:

```
POST /prospects/{prospect}/report → GenerateProspectReportJob
```

Operator uses **Regenerate report** after audit reaches `complete` or `skipped`.

---

## `CombineScoresJob` change

After successful combine, before dispatching report:

```php
if ($prospect->suppress_auto_report) {
    $prospect->update(['suppress_auto_report' => false]);
    // do not dispatch GenerateProspectReportJob
} elseif (in_array($prospect->audit_status, ['complete', 'skipped'], true)) {
    GenerateProspectReportJob::dispatch($prospect);
}
```

Normal scan pipeline: `suppress_auto_report` is false throughout → behaviour unchanged.

---

## Operator UI (`Prospect/Show.jsx`)

### Profile card — “Edit details”

- Fields: business name, phone, website, address (show empty inputs when null).
- Save via `PATCH` (Inertia `router.patch`).
- On success with audit queued: flash / inline status **“Site audit queued…”**

### Private notes card

- Header: **Private notes** + micro copy: not included on public reports.
- List entries: body, author display name, `created_at` relative time; **newest first**.
- Textarea + **Add note** → `POST /prospects/{id}/notes`.

### Public report card (existing, enhanced states)

| Condition | UI |
|-----------|------|
| `audit_status === 'pending'` after operator save | “Site audit in progress…” — soften or disable regenerate until complete |
| `audit_status === 'complete'` and report exists | **Regenerate report** (existing) |
| Audit just finished, `suppress_auto_report` was used | Hint: “Site audit complete — regenerate report to include results.” |
| `audit_status === 'failed'` | Error hint; operator can fix URL and save again to re-queue |

`SiteAuditSection` unchanged: visible when `audit_status === 'complete'` and payload present.

---

## Edge cases

| Case | Behaviour |
|------|-----------|
| `gbp_only` search, website added | GBP rescore only; no `AuditSiteJob` |
| Save while `audit_status === 'pending'` | Validation error; no partial update |
| Invalid URL | 422; no dispatch |
| Website URL unchanged on save | No audit reset/dispatch; GBP rescore still runs if other fields changed |
| Normal scan completes | `suppress_auto_report` false → auto report as today |
| Operator-triggered audit completes | Combine runs; flag cleared; **no** auto report |
| Prospect deleted | Notes cascade delete |
| Public `/r/{token}` | `report_data` and API never include notes |
| Outreach email generation | Notes not passed to Claude |

---

## Testing

### Feature tests

- `PATCH` updates fields; owner-only authorization.
- Adding phone removes “No phone number listed” from `gbp_flags` after rescore.
- Adding website removes “No website listed”; dispatches `AuditSiteJob` (Queue::fake) for combined search.
- Website change clears a11y payloads and sets `audit_status` pending.
- `suppress_auto_report` prevents `GenerateProspectReportJob` after operator audit combine; manual `POST .../report` still works.
- `PATCH` rejected when `audit_status === 'pending'`.
- `POST .../notes` creates entry; listed on show page props; other user cannot access.

### Unit tests

- `GbpScoringService::scoreProspect` (or overlay helper) respects manual `website_url` / `phone` over stale payload fields.

---

## Out of scope (v1)

- Inline edit from search results table
- Edit/delete existing notes
- Notes in outreach or public reports
- Auto-regenerate report after operator audit
- Re-fetching Places API on save
- Bulk enrichment

---

## Implementation order (suggested)

1. Migration: `prospect_notes`, `suppress_auto_report`
2. `ProspectNote` model + relations
3. `GbpScoringService` prospect overlay + `ProspectEnrichmentService`
4. `CombineScoresJob` suppress flag
5. Controllers, routes, form requests, policies
6. Inertia props + `Prospect/Show.jsx` UI
7. Tests
