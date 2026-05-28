# Single-site URL audit — Design Spec

**Date:** 2026-05-28  
**Status:** Approved  
**Scope:** Operator-initiated audit for a specified website URL from the scanner page, with optional Google Business Profile lookup, full prospect pipeline, and redirect to search results.

**Approach:** Extend `searches` with `source` + `submitted_url`; new `DirectUrlScanJob` and `GooglePlacesService::findByWebsiteUrl()`; secondary card on `/search`. Reuse existing audit/report pipeline unchanged.

---

## Goal

Operators sometimes have a specific website URL (from outreach, referral, or manual research) and want the same outcome as a bulk scan with one result:

1. Attempt **Google Business Profile lookup** from the submitted URL.
2. Run **WCAG 2.2 site audit** on the URL.
3. Produce **combined score** and **auto-generated public report**.
4. Land on **`/searches/{id}`** — same results page as area scans.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Outcome | Full prospect record — GBP (when found) + site audit + combined score + report |
| GBP not found | Proceed: `gbp_score=0`, flag `"No GBP match found"`, audit still runs on submitted URL |
| UI placement | Secondary **"Single site audit"** card on `/search`, below area scan form |
| Post-submit redirect | `/searches/{id}` (search results page) |
| Scan type | Always `combined` for direct URL scans |
| GBP benchmark | None — absolute GBP scoring only (`benchmark_snapshot=null`) |
| Data model | `searches.source` enum + nullable `niche`/`city` + `submitted_url` |
| Rate limiting | Shared with area scans (`search-submit:{user_id}`, 30s default) |
| Operator URL vs GBP `websiteUri` | Submitted URL always used for `website_url` and audit; GBP payload scored separately when found |

---

## Architecture

### New components

| Component | Responsibility |
|-----------|----------------|
| Migration: `searches.source`, `searches.submitted_url`, nullable `niche`/`city` | Distinguish discovery vs direct URL scans |
| `WebsiteUrlNormalizer` | Canonical URL + host extraction (shared by controller and GBP lookup) |
| `GooglePlacesService::findByWebsiteUrl(string $url): ?array` | Text search by domain; verify `websiteUri` host match; return full place details |
| `DirectUrlScanJob` | GBP lookup → create prospect → dispatch `AuditSiteJob` |
| `StoreDirectUrlSearchRequest` | Validate `website_url` |
| `SearchController::storeDirectUrl` | Create search, rate limit, dispatch job, redirect |
| `Search/Index.jsx` | Secondary single-site card |
| `Search/Show.jsx` | Header/progress copy when `source=direct_url` |

### Unchanged (reused)

- `AuditSiteJob`, `CombineScoresJob`, `GenerateProspectReportJob`, `CaptureScreenshotJob`
- `GbpScoringService`, `CombineScoresService`, `SearchStatusService`
- `ProspectEnrichmentService` (operator can fix profile after scan)
- Search/prospect policies and outreach flows

---

## User flow

1. Operator opens `/search` and enters a URL in the **Single site audit** card.
2. POST creates a `Search` with `source=direct_url`, `submitted_url`, `scan_type=combined`, `total_found=1`.
3. `DirectUrlScanJob` runs:
   - Sets `status=discovering`.
   - Calls `findByWebsiteUrl()`.
   - **Match found:** extract fields, score GBP (absolute only), create prospect with real `place_id`, operator URL on `website_url`.
   - **No match:** create prospect with synthetic `place_id`, derived business name, operator URL, `gbp_score=0`, flag `"No GBP match found"`.
   - Dispatch `AuditSiteJob`.
4. Existing chain: `AuditSiteJob` → `CombineScoresJob` → `GenerateProspectReportJob`.
5. Redirect to `/searches/{id}`; page polls while `status` is running.

---

## Data model

### `searches` — new/changed columns

```
source          enum('discovery', 'direct_url'), default 'discovery'
submitted_url   string, nullable
niche           string, nullable (required when source=discovery)
city            string, nullable (required when source=discovery)
```

Existing discovery rows: `source=discovery`, `niche`/`city` populated, `submitted_url=null`.

Direct URL rows: `source=direct_url`, `submitted_url` set, `niche`/`city` null, `scan_type=combined`, `total_found=1`, `benchmark_snapshot=null`.

### `prospects` — no schema change

| Case | `place_id` | Notes |
|------|------------|--------|
| GBP match | Real Places `id` | Same shape as `ScorePlaceJob` output |
| No match | `direct:{sha256(normalized_url)}` | Stable synthetic ID; unique per search |

No-match prospect fields:

- `business_name`: humanised domain (e.g. `birminghamdentalpractice.co.uk` → readable label)
- `website_url`: normalised submitted URL
- `gbp_score`: `0`
- `gbp_flags`: `['No GBP match found']`
- `raw_gbp_payload`: `null`

---

## GBP lookup (`findByWebsiteUrl`)

Google Places has **no reverse lookup by URL**. Implementation:

1. Normalise submitted URL → extract host (strip `www.`).
2. `POST places:searchText` with `textQuery` = host (single page, up to 20 results).
3. Field mask includes `places.id`, `places.websiteUri`, `places.displayName`.
4. Select first result where normalised `websiteUri` host equals submitted host.
5. On match: `getPlaceDetails(placeId)` and return payload.
6. On no match or Places API error: return `null` (log warning on API error; do not fail scan).

**Limitations (v1):** franchise/chain ambiguity (first host match wins); shared domains; social-only listings. Operator can correct via prospect enrichment after scan.

When GBP is found, overlay operator `website_url` onto payload before scoring (same pattern as `GbpScoringService::overlayProspectFields`).

---

## URL normalisation

Accept:

- `example.com`
- `https://example.com`
- `http://www.example.com/path`

Store canonical form: `https://{host}` (path stripped for host matching; audit uses full normalised URL including path if provided).

Reject non-http(s) schemes (`javascript:`, `ftp:`, etc.).

---

## Routes

```
POST /searches/direct  →  SearchController@storeDirectUrl  (name: searches.store-direct)
```

Discovery `POST /searches` unchanged.

Validation on `storeDirectUrl`:

- `website_url`: required, string, valid URL, max 2048

---

## Frontend

### `Search/Index.jsx`

- Secondary card below area scan parameters: **"Single site audit"**
- URL input with optional `https://` prefix in UI
- Hint: GBP lookup attempted; audit ~90 seconds
- Separate `useForm` POST to `/searches/direct`
- Same rate-limit error display as area scan

### `Search/Show.jsx`

When `search.source === 'direct_url'`:

- Page title / eyebrow: submitted URL hostname (not `niche in city`)
- Progress: singular copy ("Auditing website…")
- Single-row table behaviour unchanged

### Recent searches sidebar

Direct scans: show hostname + "Single site" instead of niche/city.

---

## Error handling

| Failure | Behaviour |
|---------|-----------|
| Invalid URL | 422 on `website_url` |
| Rate limit | Same message as area scan |
| Places API error | Log warning; no-match path |
| Candidates but no host match | No-match path |
| `AuditSiteJob` failure | Existing failed audit handling |
| Audit driver `skip` | Existing skip path; report still generated |

Duplicate URL submits: allowed (new search each time), same as re-running area scans.

---

## Search status lifecycle

`DirectUrlScanJob`:

1. `status=discovering`
2. Create prospect, ensure `total_found=1`
3. Dispatch `AuditSiteJob`

`SearchStatusService` (unchanged logic):

- `discovering` → `auditing` when prospect exists and audit pending
- `auditing` → `complete` when single prospect audit finishes

---

## Testing

| Layer | Cases |
|-------|--------|
| Unit — `WebsiteUrlNormalizer` | hosts, www strip, scheme defaulting, invalid schemes |
| Unit — `findByWebsiteUrl` | mocked HTTP: exact match, no match, API failure → null |
| Feature — `POST /searches/direct` | creates `direct_url` search, dispatches job, redirects |
| Feature — `DirectUrlScanJob` | GBP found → real `place_id` + scores; not found → synthetic ID + flag + audit queued |
| Feature — rate limit | shared limiter blocks within window |
| Feature — pipeline (fake audit driver) | search `complete`, report job dispatched |

---

## Out of scope (v1)

- Public/unauthenticated URL audit (homepage `AuditWidget` remains demo)
- Operator pick-from-multiple GBP matches
- Niche/city benchmark for direct scans
- URL deduplication per user
- Separate rate limit for direct vs area scans

---

## Alternatives considered

| Approach | Verdict |
|----------|---------|
| Synthetic niche/city (no migration) | Rejected — pollutes recent searches and headers |
| Separate `DirectAudit` entity | Rejected — duplicates pipeline; conflicts with redirect to `searches.show` |
| Block when no GBP match | Rejected — operator chose proceed-with-audit |
| **Explicit `source` on searches** | **Chosen** |
