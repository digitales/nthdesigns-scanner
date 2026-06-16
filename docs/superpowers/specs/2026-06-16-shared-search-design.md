# Shared Search Results — Design Spec

**Date:** 2026-06-16  
**Status:** Approved  
**Route:** `GET /q/{token}` (public), `POST /searches/{search}/share` (operator)

---

## Goal

Let operators share a point-in-time snapshot of search results with external team members via an unlisted link — read-only, no login, no operator actions.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Content | Full search results table: scores, flags, CMS, CPC (read-only), website URLs, `/r/{token}` report links |
| Excluded | Phone, address, internal IDs, operator notes/tags, outreach/list badges, links to `/prospects/{id}` |
| Lifecycle | Immutable snapshot at share time (like `/s/{token}` list shares) |
| Revoke | `DELETE /shared-searches/{id}` sets `revoked_at` |
| Expiry | Optional `expires_at` (nullable = no expiry) |
| Re-share | Each share creates a new `shared_searches` row |

---

## Architecture

```text
POST /searches/{id}/share
  → SharedSearchSnapshotBuilder
  → shared_searches { token, snapshot }

GET /q/{token}
  → SharedSearch/Show (public, noindex, throttled)
```

---

## Snapshot payload

**Search:** `niche`, `city`, `scan_type`, `source`, `submitted_url`, `total_found`, `prospect_count`, `shared_at`, `cpc_benchmark`, `cpc_keywords`, `cpc_source`

**Per prospect:** `business_name`, `website_url`, scores, `dominant_angle`, `cms_badge`, flags, `audit_status`, `report_url` (if report existed at snapshot)

---

## Operator UX

- **Share** button on `/searches/{id}` when ≥1 prospect
- Flash banner with copy/open/dismiss (same pattern as Lists)

---

## Public UX

- Read-only `CpcBenchmarkPanel` (area searches only)
- Client-side angle + min-score filters
- Expandable weakness flags
- Footer: snapshot disclaimer

---

## Testing

Feature tests in `PublicSharedSearchTest`: snapshot fields, PII exclusion, report URLs, revoke, expiry, authorization.
