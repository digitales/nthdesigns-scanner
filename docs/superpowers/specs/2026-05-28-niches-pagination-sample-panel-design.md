# Niches Index — Pagination, Infinite Scroll & Sample Panel

**Date:** 2026-05-28  
**Status:** Approved (brainstorming) — pending implementation plan  
**Scope:** Improve `/niches` at scale (~6k niche×city rows) with paginated infinite loading, URL-backed page state, sticky chrome, and a side panel showing sampled business names. Extends the niche opportunity scanner (`2026-05-27-niche-opportunity-scanner-design.md`); supersedes that spec’s “no pagination” constraint for the index page only.

**Approach:** Laravel paginator on the existing latest-per-(niche, city) query; Inertia for initial load and filter changes; client-side row merge for infinite scroll; JSON sample endpoint with on-demand backfill via the scraping queue.

---

## Goal

Operators triaging large niche×city lists should be able to:

1. Scroll through results without loading thousands of rows at once.
2. See **where they are** in the list (`Showing 51–100 of 6,312 · Page 2 of 127`) in a bar that stays visible while scrolling.
3. Keep **page title, filters, position bar, and column headers** fixed under the app topbar.
4. Open a **side panel** for a row to see **names from the GBP sample** used to compute aggregates (not the full Places result set).
5. **Share or refresh** at a position via URL (`?city=Leeds&sort=opportunity_score&page=3`).

---

## Decisions (brainstorming)

| Topic | Decision |
|-------|----------|
| Sample names source | Businesses in the **GBP sample** (~5 by default, `niches:scan --sample=N`) |
| Names UI | **Right-hand side panel** on row select |
| Sticky on scroll | **Full stack** above table body: `PageHeader`, `FilterBar`, `ListMetaBar`, `thead` |
| Loading | **Infinite scroll** + **`?page=` in URL** (filters preserved) |
| Legacy rows (no `sample_preview`) | **On-demand backfill** when panel opens (Places via scraping queue) |
| Full result list | **Out of scope** — no listing of all `result_count` places |
| Pagination approach | **Inertia paginator + client merge** (not a separate table API) |

---

## Architecture

```text
GET /niches?city=&sort=&page=
    └── NicheScanController@index
            └── paginate(50) on latest-per-(niche, city) query
            └── Inertia: scans, pagination, cities, filters

Scroll sentinel (client)
    └── router.get next page, only: ['scans', 'pagination']
    └── append scans to client rows[], update URL page=

GET /niches/{nicheScan}/sample
    └── NicheScanSampleController@show
            ├── sample_preview present → 200 JSON
            └── missing → dispatch ScanNicheJob (single combo) → 202 { status: 'loading' }
            └── client polls until 200 or failed

ScanNicheJob / NicheSampleCollector
    └── same Places + scoring loop
    └── persist sample_preview JSON + existing aggregates
```

### New / changed pieces

| Piece | Action |
|-------|--------|
| Migration | Add `sample_preview` JSON nullable to `niche_scans` |
| `App\Services\NicheSampleCollector` | Extract sampling + scoring loop from `ScanNicheJob` |
| `ScanNicheJob` | Delegate to collector; write `sample_preview` on complete |
| `NicheScanController@index` | Paginate; include `id` on each row |
| `NicheScanSampleController` | JSON show + backfill dispatch |
| `resources/js/Pages/Niches/Index.jsx` | Sticky layout, infinite scroll, panel |
| `resources/js/Components/Niches/NicheSamplePanel.jsx` | New panel component |
| `resources/css/components.css` | `.niches-layout`, sticky stack, panel |

---

## Data model

### Column: `sample_preview`

JSON array on `niche_scans`, written when a scan completes (or backfill finishes).

```json
[
  {
    "name": "Joe's Dental",
    "gbp_score": 72,
    "no_website": true,
    "review_count": 5
  }
]
```

| Field | Source |
|-------|--------|
| `name` | Place display name from Places payload |
| `gbp_score` | `GbpScoringService::score($payload, null)['score']` rounded int |
| `no_website` | `websiteUri` empty |
| `review_count` | `userRatingCount ?? 0` |

- Empty sample (`sampled_count === 0`): `sample_preview` = `[]` or `null`; panel shows “No places found”.
- **Not** exposed on the paginated index payload (keeps pages small); panel loads via sample endpoint only.

### Index row payload (paginated)

Existing fields plus `id` (required for panel and sample route).

---

## Backend

### `GET /niches` (Inertia)

- Query unchanged in spirit: latest row per `(niche, city)` by `ran_at`, filters on `city`, sort on `opportunity_score` or `result_count`.
- **`paginate(50)`** instead of `get()`.
- Response props:

```php
'scans' => [...], // current page only
'pagination' => [
    'total' => int,
    'current_page' => int,
    'per_page' => 50,
    'last_page' => int,
],
'cities' => [...],
'filters' => ['city' => ?string, 'sort' => string],
```

- Request validates `page` as positive integer; default `1`.

### `GET /niches/{nicheScan}/sample` (JSON)

- Middleware: `auth`.
- **200:** `{ status: 'ready', niche, city, sampled_count, result_count, ran_at_human, items: sample_preview }`
- **202:** `{ status: 'loading' }` — job dispatched on first request when `sample_preview` is null and row is not already pending for this purpose.
- **422/404:** invalid id.
- On backfill: dispatch `ScanNicheJob` for that row’s `niche`, `niche_query`, `city`, `country`, `sample` (from config/command default, 5), `scan_date` (today Europe/London). Reuses scraping queue.

**Polling:** Client polls every 2s, max 60s; treat `status === 'failed'` on model as terminal error.

**Concurrency:** If `status === 'pending'`, return `202` without duplicate dispatch.

### `NicheSampleCollector`

Shared service used by `ScanNicheJob`:

1. `searchByNicheAndCity` → place IDs
2. Random sample of N IDs
3. For each: `getPlaceDetails` → score → build preview item + aggregate counters
4. Return `{ metrics: [...], sample_preview: [...] }` for job to persist

Keeps backfill and scheduled scans identical.

---

## Frontend

### Layout

```text
┌─────────────────────────────────────────────┬──────────────┐
│ STICKY (top: 52px, below app-topbar)        │ NicheSample  │
│  PageHeader + FilterBar + ListMetaBar       │ Panel 360px  │
│  + table thead                              │              │
├─────────────────────────────────────────────┤              │
│ Scroll: tbody + sentinel + loading row      │              │
└─────────────────────────────────────────────┴──────────────┘
```

- CSS class `niches-layout` (flex); main column flex-1; panel when `selectedId` set.
- Sticky stack: `position: sticky; top: 52px; z-index: 40; background: var(--color-paper)`.
- Table: single `ptable`; `thead` in sticky region; tbody in scroll container (or sticky `thead` within one scroll parent — implementer chooses simplest alignment).

### ListMetaBar

- Text: **`Showing {from}–{to} of {total} · Page {current} of {last}`**
- `from` / `to` derived from **loaded** row count and `pagination.per_page`, not only current Inertia page (e.g. after loading pages 1–2, show 1–100).
- Updates on append and on deep-link hydration.

### Infinite scroll

- `IntersectionObserver` on sentinel at tbody bottom.
- Guard: `!loading && current_page < last_page`.
- On trigger: `router.get('/niches', { ...filters, page: nextPage }, { preserveState: true, preserveScroll: true, only: ['scans', 'pagination'] })`.
- Append `scans` to React state; update URL `page` (use `replace: true` to limit history noise).
- **Filter/sort change:** clear accumulated rows, `page=1`, close panel, full navigation.

### Deep link `?page=N` (N > 1)

On mount: parallel fetch pages `1..N` (N sequential requests acceptable for v1 if simpler), merge rows, set meta from last response, scroll to first row of page N (`(N-1) * per_page` index).

### Row interaction

- Row click → select, open panel, `tr.selected`.
- **Run Full Scan** button: `stopPropagation` — existing POST `/searches` unchanged.

### `NicheSamplePanel`

| Section | Content |
|---------|---------|
| Header | Niche, city, opportunity `ScoreBadge`, close |
| Subhead | `Sampled {sampled_count} of {result_count} places · {ran_at_human}` |
| Body | List: name, GBP `ScoreBadge`, chips (no website, low reviews if &lt; 20) |
| Footer | Run Full Scan |

| State | UI |
|-------|-----|
| Loading | Spinner, “Fetching sample…” |
| Ready | Item list |
| Failed | Error + Retry (re-GET sample) |
| Empty | “No places found in this market” |

**Mobile (&lt;1024px):** panel as full-width overlay/sheet below sticky stack; close returns to table-only.

### Panel data flow

1. `GET /niches/{id}/sample`
2. `200` → render
3. `202` → poll until `200` or failed/timeout
4. Switching rows cancels in-flight poll, fetches new id

---

## Edge cases

| Case | Behaviour |
|------|-----------|
| Zero index results | Existing `EmptyState`; no scroll, no panel |
| Last page | Disable sentinel; meta shows final range |
| Pending row | Panel may poll; aggregates may update when job completes |
| Rapid scroll | Single in-flight page request |
| Filter with panel open | Close panel, reset rows, page 1 |
| Job failure | Panel error + Retry; row `status: failed` |
| `sample_preview` write | All-or-nothing on job complete (no partial array in v1) |

---

## Security

- Sample route: authenticated, `nicheScan` exists.
- Same global visibility as index (no `user_id` on `niche_scans`).

---

## Out of scope (v1)

- Load-earlier / scroll-up pagination above deep-linked page
- Increasing default `--sample` in UI (CLI flag only)
- Full Places result list for `result_count`
- Exposing opportunity formula in UI
- Browser/E2E tests

---

## Testing

| Test | Intent |
|------|--------|
| `NicheScanControllerTest`: paginated index | 60 rows → page 1 count 50, `pagination.total` 60, `last_page` 2 |
| `NicheScanControllerTest`: filters | City filter reduces total; sort order |
| `NicheScanSampleControllerTest`: 200 | Row with `sample_preview` returns items |
| `NicheScanSampleControllerTest`: 202 | Missing preview dispatches job once |
| `ScanNicheJobTest` (or collector unit) | `Http::fake()` → `sample_preview` length matches `sampled_count`, names present |

---

## File checklist

| File | Action |
|------|--------|
| `database/migrations/*_add_sample_preview_to_niche_scans.php` | create |
| `app/Services/NicheSampleCollector.php` | create |
| `app/Jobs/ScanNicheJob.php` | refactor to collector; persist `sample_preview` |
| `app/Http/Controllers/NicheScanController.php` | paginate; add `id` |
| `app/Http/Controllers/NicheScanSampleController.php` | create |
| `routes/web.php` | sample route |
| `app/Models/NicheScan.php` | fillable + cast `sample_preview` as array |
| `resources/js/Pages/Niches/Index.jsx` | layout, scroll, merge state |
| `resources/js/Components/Niches/NicheSamplePanel.jsx` | create |
| `resources/css/components.css` | niches layout + sticky |
| `tests/Feature/NicheScanControllerTest.php` | extend |
| `tests/Feature/NicheScanSampleControllerTest.php` | create |

---

## Relation to prior spec

`2026-05-27-niche-opportunity-scanner-design.md` listed “Pagination on `/niches`” as out of scope. This document adds that capability and `sample_preview` storage without changing the core scan formula, queue, or Run Full Scan behaviour.
