# Niches Page — Refresh Market Scan — Design Spec

**Date:** 2026-06-08  
**Status:** Implemented  
**Screen:** `/niches` (`Niches/Index.jsx`, `NicheSamplePanel.jsx`)

**Approach:** Dedicated refresh + status JSON endpoints with per-combo client polling. Extract shared dispatch logic from prospect refresh into `DispatchMarketScanRefresh`.

---

## Goal

From the Niches dashboard, let operators refresh the lightweight **market scan** for a single niche+city without visiting Settings or a prospect detail page.

This re-samples Google Business Profiles and updates `/niches` aggregates. It does **not** create a `gbp_only` search or re-scan individual prospects.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Action scope | One `ScanNicheJob` for the row's `niche` + `city` |
| Force re-fetch | Always `force: true` on manual refresh |
| Placement | Table row actions **and** sample panel footer |
| Polling | Per-combo JSON poll every 4s; patch local row state (not full Inertia reload) |
| Pending display | Keep last complete stats visible; show `pending` badge; disable refresh |
| Duplicate dispatch | Block POST when today's row is already `pending` |
| Rate limit | 60s per user + niche + city combo |
| Niche query resolution | `NicheQueryResolver::forLabel($niche)`; fallback `$nicheScan->niche_query` |
| Sample size | `5` (same default as `niches:scan`) |
| Scan date | `Europe/London` calendar date |
| Authorization | Any authenticated user (global market data) |
| Shared dispatch | `DispatchMarketScanRefresh` action; prospect route delegates to it |
| MCP exposure | Out of scope |

---

## UX

### Table row actions

Add **Refresh scan** between Annotate and Run Full Scan.

| State | Label | Disabled |
|-------|-------|----------|
| Idle | Refresh scan | No |
| POST in flight | Queuing… | Yes |
| Scan pending for combo | Scan in progress… | Yes |

While pending:
- Row keeps showing **last complete** stats
- `pending` status badge under niche name (existing non-complete pattern)
- Refresh disabled on all rows sharing that niche+city

### Sample panel footer

```
[Refresh scan]  [Run Full Scan]
```

| Panel state | Behaviour |
|-------------|-----------|
| Idle | Both buttons enabled |
| Refresh queued / pending | Refresh disabled ("Scan in progress…"); body shows "Refreshing market scan…" |
| Complete | Sample list reloads from status `id` |
| Failed | Error hint; Retry reloads sample |

### Polling & auto-update

1. Successful POST adds niche+city key to client `pendingKeys` set
2. Poll `GET /niches/{nicheScan}/status` every **4s** while `pendingKeys` non-empty
3. On `is_pending: false` + `status: complete`: patch matching row in `rows` state; reload sample if panel open for that combo; remove from `pendingKeys`
4. On `status: failed`: patch row with failed badge; remove from `pendingKeys`; re-enable refresh
5. Initial page load: `is_pending` on each scan row from index props (no poll needed unless user triggered refresh)

### Flash messages

| Scenario | Message |
|----------|---------|
| Queued | *"Market scan queued for {niche} in {city}."* |
| Already pending | *"Market scan already in progress."* |
| Rate limited | *"Please wait {n} seconds before refreshing this market scan."* |

---

## Backend

### Routes

```
POST /niches/{nicheScan}/refresh → NicheScanController::refresh   (niches.refresh)
GET  /niches/{nicheScan}/status  → NicheScanController::status    (niches.status)
```

### `DispatchMarketScanRefresh` (new)

`app/Actions/DispatchMarketScanRefresh.php`

```php
final class DispatchMarketScanRefresh
{
    public function __invoke(
        string $niche,
        string $city,
        string $country,
        ?string $nicheQueryFallback = null,
        ?int $userId = null,
    ): DispatchMarketScanRefreshResult;
}
```

**Result variants:**

- `Queued`
- `AlreadyPending`
- `RateLimited(int $seconds)`

**Logic:**

1. `scanDate` = `now('Europe/London')->toDateString()`
2. If today's row for `(niche, city)` has `status === pending` → `AlreadyPending`
3. If `$userId` set: rate limit key `niche-scan-refresh:{userId}:{niche}:{city}` — 1 attempt per 60s
4. Resolve `nicheQuery` = `NicheQueryResolver::forLabel($niche)`; if label not in config and fallback provided, use `$nicheQueryFallback`
5. Dispatch:

```php
ScanNicheJob::dispatch(
    niche: $niche,
    nicheQuery: $resolvedQuery,
    city: $city,
    country: $country,
    sample: 5,
    scanDate: $scanDate,
    force: true,
);
```

6. Return `Queued`

### `ProspectController::refreshMarketScan` refactor

Delegate to `DispatchMarketScanRefresh` after existing `view` auth and `direct_url` guard. Map result to same flash messages. Rate limit key unchanged for backward compatibility **or** migrate prospect route to shared `niche-scan-refresh:` key (preferred: single key prefix for both entry points).

**Rate limit key (unified):** `niche-scan-refresh:{userId}:{niche}:{city}` for both prospect and niches routes.

### `NicheScanController::refresh`

1. Load `$nicheScan` from route binding
2. Invoke `DispatchMarketScanRefresh` with scan fields + `$request->user()->id`
3. Redirect back with flash (same messages as prospect refresh)

### `NicheScanController::status` (JSON)

Resolution for `(niche, city)` from route model:

| Today's row | `is_pending` | Stats / `id` source |
|-------------|--------------|---------------------|
| `pending` | `true` | Stats from latest **complete** row; `status: pending`; `id` = today's pending row id (for sample poll) |
| `complete` | `false` | Today's row |
| `failed` | `false` | Latest complete stats; `status: failed`; `error_message` from today |
| None | `false` | Latest per combo via `LatestNicheScanQuery` |

Response shape:

```json
{
  "niche": "Plumber",
  "city": "Leeds",
  "id": 42,
  "is_pending": true,
  "status": "pending",
  "result_count": 120,
  "sampled_count": 5,
  "avg_gbp_score": 45.2,
  "pct_no_website": 30.0,
  "pct_low_reviews": 55.0,
  "opportunity_score": 38,
  "ran_at_human": "2 hours ago",
  "error_message": null
}
```

### `NicheScanController::mapScan` enhancement

Add `is_pending`: exists today's London `scan_date` row with `status === pending` for this niche+city.

---

## Frontend

### New hook: `useNicheScanStatusPoll`

`resources/js/hooks/useNicheScanStatusPoll.js`

```js
useNicheScanStatusPoll(pendingKeys, { onUpdate })
```

- `pendingKeys`: `Set` of `"niche|city"` strings
- Every 4s, for each key fetch status from a scan id lookup in local `rows`
- `onUpdate(comboKey, statusPayload)` patches `rows` and `selected` state

Alternative: pass `scanIdByKey` map updated when refresh POST fires.

### `Niches/Index.jsx`

- `refreshScan(row)` — `router.post(`/niches/${row.id}/refresh`, { preserveScroll: true })`
- Track `pendingKeys` and `queuingRowId` state
- `useNicheScanStatusPoll` patches rows by niche+city match (handles id change when new `scan_date` row becomes latest)
- Row action button with states from UX table
- Pass `onRefreshScan` and `isRefreshing` to `NicheSamplePanel`

### `NicheSamplePanel.jsx`

- Accept `onRefreshScan`, `isRefreshing`, `scanPending`
- Footer: secondary Refresh scan button
- When `scanPending` or `isRefreshing`: show "Refreshing market scan…"; skip sample fetch until `is_pending` false
- On poll complete: re-run `loadSample` with updated `scan.id` from parent

---

## Error handling

| Scenario | Behaviour |
|----------|-----------|
| Job fails | Row shows `failed` badge; refresh re-enabled |
| No prior scan | Refresh creates first row; row updates on complete |
| Rate limited | Error flash; button re-enabled after wait |
| Already pending | Info flash; button stays disabled; polling continues |
| Status poll network error | Silent retry next interval; stop after 5 min with toast |

---

## Testing

| Test file | Coverage |
|-----------|----------|
| `tests/Unit/DispatchMarketScanRefreshTest.php` | Queued, already pending, rate limit, query resolution |
| `tests/Feature/NicheScanRefreshTest.php` | POST dispatches job with `force: true`; pending guard; rate limit; flash messages |
| `tests/Feature/NicheScanStatusTest.php` | JSON status for pending/complete/failed/id-change scenarios |
| `tests/Feature/ProspectNicheScanTest.php` | Update rate limit key if changed; still passes via shared action |
| `tests/Feature/NicheScanControllerTest.php` | Index `is_pending` on mapScan (extend existing or new) |

---

## File map

| File | Change |
|------|--------|
| `app/Actions/DispatchMarketScanRefresh.php` | New — shared dispatch + guards |
| `app/Actions/DispatchMarketScanRefreshResult.php` | New — result enum/DTO |
| `app/Http/Controllers/NicheScanController.php` | `refresh()`, `status()`, `is_pending` in `mapScan()` |
| `app/Http/Controllers/ProspectController.php` | Delegate `refreshMarketScan()` to action |
| `routes/web.php` | POST refresh + GET status routes |
| `resources/js/hooks/useNicheScanStatusPoll.js` | New — per-combo polling |
| `resources/js/Pages/Niches/Index.jsx` | Refresh action, pending state, poll integration |
| `resources/js/Components/Niches/NicheSamplePanel.jsx` | Refresh button + pending UX |
| `tests/Unit/DispatchMarketScanRefreshTest.php` | New |
| `tests/Feature/NicheScanRefreshTest.php` | New |
| `tests/Feature/NicheScanStatusTest.php` | New |

---

## Out of scope

- Batch market scan (Settings only)
- Full `gbp_only` prospect search (existing Run Full Scan)
- MCP tool for niche refresh
- Push notifications on scan completion
- Niche name URL filter changes
