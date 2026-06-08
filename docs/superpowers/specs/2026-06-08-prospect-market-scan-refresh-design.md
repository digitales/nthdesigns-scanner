# Prospect Detail — Refresh Market Scan — Design Spec

**Date:** 2026-06-08  
**Status:** Approved  
**Screen:** C — `/prospects/{id}` (`Prospect/Show.jsx`)

**Approach:** Prospect-scoped action — expose latest niche scan context on show; `POST` dispatches a single `ScanNicheJob` with `force: true` for the prospect's search niche + city.

---

## Goal

From the prospect detail page, let operators see the latest **market triage** data for that prospect's niche + city and refresh it without visiting Settings or re-running the full prospect discovery search.

This updates `/niches` aggregates only. It does **not** re-scan the individual prospect or create a new `gbp_only` search.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Action scope | One `ScanNicheJob` for `search.niche` + `search.city` only |
| Force re-fetch | Always `force: true` on manual refresh |
| Card visibility | Area searches only — hidden for `direct_url` |
| Sidebar placement | After **Outreach**, before **Location** |
| Card title | `Market scan` |
| Stats shown | Opportunity score, result count, last run, status |
| Empty state | "No market scan yet" when no row exists for combo |
| Pending UX | Button disabled; label "Scan in progress…"; poll via `useProgressReload` |
| Duplicate dispatch | Block POST when today's row is already `pending` |
| Rate limit | 60s per user + niche + city combo |
| Niche query resolution | Config label match → config `query`; fallback `Str::lower(search.niche)` |
| Sample size | `5` (same default as `niches:scan`) |
| Scan date | `Europe/London` calendar date (same as existing niche scans) |
| Niches link | `/niches?city={city}` (city filter pre-applied) |
| Authorization | Existing prospect `view` policy |
| MCP exposure | Out of scope |

---

## UX

### Market scan card

- **Title:** `Market scan`
- **Context line:** `{niche} · {city}` from search
- **Stats:** Opportunity score (`ScoreBadge`), result count, `ran_at` humanized
- **Status:** `Status` badge for `pending` / `complete` / `failed`; failed shows micro error hint when `error_message` present
- **Empty state:** "No market scan yet" when no historical row for this combo
- **Action:** Secondary button **Refresh market scan**
- **Helper text:** "Re-samples Google Business Profiles for this niche and city. Updates the Niches dashboard — does not re-scan this prospect."
- **Link:** `View on Niches` → `marketScan.niches_url`

### Sidebar order

Public report → Outreach → **Market scan** → Location → Profile → …

### Button states

| State | Label | Disabled |
|-------|-------|----------|
| Idle | Refresh market scan | No |
| POST in flight | Queuing… | Yes |
| Scan pending | Scan in progress… | Yes |

### Polling

When `marketScan.is_pending`, call `useProgressReload(true, ['marketScan'])` (4s interval, same as site audit pending).

---

## Backend

### Route

```
POST /prospects/{prospect}/niche-scan → ProspectController::refreshMarketScan
```

Name: `prospects.niche-scan`

### `ProspectController::show`

Add nullable `marketScan` prop. Omit or set `null` when `search.source === 'direct_url'`.

For area searches, load the latest `niche_scans` row for `(search.niche, search.city)` by max `ran_at` (same latest-per-combo logic as `/niches` index).

```php
'marketScan' => [
    'niche' => $search->niche,
    'city' => $search->city,
    'opportunity_score' => $scan?->opportunity_score,
    'result_count' => $scan?->result_count,
    'sampled_count' => $scan?->sampled_count,
    'status' => $scan?->status?->value,
    'ran_at_human' => $scan?->ran_at?->diffForHumans() ?? '—',
    'is_pending' => $todayPending, // today's London scan_date row status === pending
    'error_message' => $scan?->error_message,
    'niches_url' => '/niches?city='.urlencode($search->city),
],
```

`is_pending` is derived from today's row (`scan_date` = London today), not the displayed latest row — so a refresh queued today disables the button even if the displayed stats are from an older complete run until the new run finishes.

### `ProspectController::refreshMarketScan`

1. Authorize `view` on prospect
2. Reject `direct_url` searches with `422`
3. If today's row for `(niche, city)` is `pending`, redirect back with info flash: *"Market scan already in progress."*
4. Rate limit key `prospect-niche-scan:{userId}:{niche}:{city}` — 1 attempt per 60s
5. Resolve `niche_query` via `NicheQueryResolver::forLabel($search->niche)`
6. Dispatch:

```php
ScanNicheJob::dispatch(
    niche: $search->niche,
    nicheQuery: $resolvedQuery,
    city: $search->city,
    country: $search->country,
    sample: 5,
    scanDate: now('Europe/London')->toDateString(),
    force: true,
);
```

7. Redirect back with success flash: *"Market scan queued for {niche} in {city}."*

### `NicheQueryResolver` (new)

`app/Support/NicheQueryResolver.php` — static `forLabel(string $label): string`

1. Match `label` exactly against `config('niches.niches')`
2. Fallback: `Str::lower($label)`

---

## Frontend

**File:** `resources/js/Pages/Prospect/Show.jsx`

- Accept `marketScan` prop
- Render card when `marketScan` is truthy
- Reuse `ScoreBadge`, `Status`, `Button`, `Card` from UI kit
- `postAction('marketScan', `/prospects/${prospect.id}/niche-scan`, { preserveScroll: true })`
- Flash success/error via existing banner

---

## Error handling

| Scenario | Behaviour |
|----------|-----------|
| Job fails | Card shows `failed` status; operator clicks Refresh again |
| No prior scan | Empty state; Refresh creates first row |
| Rate limited | Flash error with seconds remaining |
| Already pending | Info flash; button disabled; polling continues |
| Direct URL | Card hidden; POST returns 422 |

---

## Testing

| Test file | Coverage |
|-----------|----------|
| `tests/Feature/ProspectShowTest.php` | Area search includes `marketScan`; direct URL omits it |
| `tests/Feature/ProspectNicheScanTest.php` | POST dispatches job with `force: true`; pending guard; rate limit; 422 for direct URL; config + fallback query resolution |

---

## File map

| File | Change |
|------|--------|
| `app/Http/Controllers/ProspectController.php` | `marketScan` on show; `refreshMarketScan()` |
| `app/Support/NicheQueryResolver.php` | New — config lookup + fallback |
| `routes/web.php` | POST route |
| `resources/js/Pages/Prospect/Show.jsx` | Market scan sidebar card |
| `tests/Feature/ProspectShowTest.php` | Prop assertions |
| `tests/Feature/ProspectNicheScanTest.php` | POST behaviour |

---

## Out of scope

- Niche name filter on `/niches` URL
- Full-matrix scan from prospect page (Settings only)
- Re-running prospect discovery search (`gbp_only` search creation)
- MCP tool for this action
- Push notifications on scan completion
