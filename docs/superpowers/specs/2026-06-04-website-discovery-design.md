# Website discovery (no GBP website) — Design Spec

**Date:** 2026-06-04  
**Status:** Implemented  
**Scope:** Automatically discover a prospect’s website via Google Programmable Search when GBP has no `websiteUri`, for `accessibility_only` / `combined` scans. Record URL provenance, re-score GBP, queue site audit. Show source on prospect detail.

**Approach:** `WebsiteDiscoveryService` + **Brave Search API** (default) or legacy `GoogleCustomSearchService`, called inline from `ScorePlaceJob` after initial GBP scoring. New prospect columns for `website_url_source` and discovery metadata. Fail-open on API errors.

---

## Goal

Many discovery prospects have no website on their Google Business Profile. Today the scanner only assesses GBP (reviews, photos, etc.) and skips site audits. Operators want the tool to **search by company name** to find a likely website, **auto-apply** it, **record where the URL came from**, re-score GBP, and run the normal audit pipeline—without manual research for every no-website listing.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Apply URL | Auto-apply on confident match; re-score GBP; queue `AuditSiteJob` |
| When | Inline in `ScorePlaceJob`, after create/update, before `dispatchNextStep` |
| Provider | **Brave Search API** (default); optional legacy Google CSE via `WEBSITE_DISCOVERY_PROVIDER=google_cse` |
| Scope | Only when `scan_type` is `accessibility_only` or `combined` **and** GBP payload has no `websiteUri` |
| Matching | Two-tier: **high** = name + city signals; **medium** = name token in domain/title only |
| GBP after discovery | Overlay discovered URL for rescoring; add flag **“Website not listed on Google profile”** when source is `google_cse` |
| `raw_gbp_payload` | Never mutated (listing still has no `websiteUri`) |
| UI | Prospect detail Profile card: source label + confidence for discovered URLs |
| Search results table | No change in v1 |
| Failure | Fail-open: no URL, no search failure |

---

## Architecture

### New components

| Component | Responsibility |
|-----------|----------------|
| `GoogleCustomSearchService` | Call `https://www.googleapis.com/customsearch/v1`; return up to N normalised `{url, title, snippet}` |
| `WebsiteDiscoveryService` | Build query from prospect + search; filter blocklist; apply two-tier matcher; return `{url, confidence}` or `null` |
| Migration | `website_url_source`, `website_discovery_confidence`, `website_discovered_at` on `prospects` |
| `ScorePlaceJob` (extend) | Invoke discovery when eligible; update prospect; overlay + rescore; then existing `dispatchNextStep` |
| `ProspectEnrichmentService` (extend) | On operator website edit: `website_url_source = operator`, clear discovery fields |
| `Prospect/Show.jsx` (extend) | Display source label and confidence under Website |
| Config | `scanner.website_discovery_*`, env `GOOGLE_CSE_KEY`, `GOOGLE_CSE_CX` |

### Reused

| Component | Use |
|-----------|-----|
| `WebsiteUrlNormalizer` | Canonical stored URL (`https://{host}`) |
| `GbpScoringService::overlayProspectFields` | Overlay discovered URL before rescore |
| `GbpScoringService::isWeakWebsiteHost` | Expose or extract shared blocklist for CSE candidates (Facebook, Yelp, Linktree, etc.) |
| `AuditSiteJob` / `CombineScoresJob` | Unchanged; dispatched when `website_url` present after discovery |

### Backfill command

`php artisan scanner:backfill-websites` — dry-run lists eligible prospects; `--execute` runs discovery (API quota per row). `--no-audit` skips `AuditSiteJob`; default with `--execute` queues audits. Options: `--search=`, `--prospect=`, `--limit=`, `--delay=`.

### Not in scope

- MCP tools for discovery
- Search results table chips
- Writing discovered URL back to Google Places
- SerpAPI / Bing alternatives

---

## Data model

### `prospects` — new columns

```
website_url_source           enum('gbp','google_cse','operator'), default 'gbp'
website_discovery_confidence enum('high','medium') nullable
website_discovered_at        timestamp nullable
```

### Source semantics

| Situation | `website_url_source` | `website_discovery_confidence` | `website_discovered_at` |
|-----------|----------------------|--------------------------------|-------------------------|
| URL from Places `websiteUri` at score time | `gbp` | `null` | `null` |
| CSE auto-apply | `google_cse` | `high` or `medium` | `now()` |
| Operator saves profile with website change | `operator` | cleared | cleared |
| No website / no match | — | — | — |

Existing rows backfill: `website_url_source = 'gbp'` where `website_url` is not null; otherwise default `gbp` with null URL.

---

## Flow (`ScorePlaceJob`)

```
getPlaceDetails
→ extractFields + score (initial)
→ updateOrCreate prospect (website_url_source = gbp if URL from Places)
→ IF eligible for discovery:
      result = WebsiteDiscoveryService::discover(prospect, search)
      IF result:
          set website_url, source=google_cse, confidence, discovered_at
          overlay URL on payload → rescore GBP
          append gbp flag "Website not listed on Google profile" if not already present
          persist gbp_score, gbp_flags, website fields
→ dispatchNextStep (AuditSiteJob when URL present + scan type needs a11y)
```

**Eligibility**

```php
in_array($search->scan_type, ['accessibility_only', 'combined'], true)
&& empty($payload['websiteUri'])
&& config enabled && CSE credentials present
```

---

## Google Custom Search

### Request

- Endpoint: `GET https://www.googleapis.com/customsearch/v1`
- Params: `key`, `cx`, `q`, `num=5` (configurable)
- Query: `"{business_name}" {search.city}` (city from parent `Search`, required for discovery)

### Response handling

- Map `items[]` to `{url: link, title: title, snippet: snippet}`
- Timeout: `website_discovery_timeout_seconds` (default 8)
- HTTP failure or empty items → return `null` (log `website_discovery.skipped`, reason `api_error` or `no_results`)

### Feature gate

Skip discovery when:

- `scanner.website_discovery_enabled` is false, or
- `GOOGLE_CSE_KEY` or `GOOGLE_CSE_CX` missing

No exception; prospect proceeds without URL.

---

## Two-tier matching (`WebsiteDiscoveryService`)

### Shared filters (all tiers)

1. Require parseable http(s) URL
2. Reject `isWeakWebsiteHost()` (same list as GBP scoring: Facebook, Instagram, Linktree, Yelp, Wix subsite, etc.)
3. Normalise winner with `WebsiteUrlNormalizer` → store `https://{host}`

### Tier: high

First candidate passing filters **and**:

- **Name match:** normalised tokens from `business_name` (strip legal suffixes Ltd/Limited/LLP/&; drop tokens shorter than 3 characters) appear in **domain** or **title** (case-insensitive)
- **Locality match:** `search.city` appears in title, snippet, or URL (path/query), case-insensitive

→ confidence `high`

### Tier: medium

If no high match: first candidate passing filters where any name token with length ≥ 4 appears in domain or title.

→ confidence `medium`

### No match

Log `website_discovery.skipped` with `reason=no_match` and optional `candidates_tried`; leave prospect without URL.

---

## GBP scoring after discovery

1. `overlayProspectFields($raw_gbp_payload, $prospect)` so rescoring sees `websiteUri`
2. `score()` → updates `gbp_score`, `gbp_flags`
3. Remove **“No website listed”** from flags if overlay supplies a website (weak-host rules still apply)
4. **Always append** **“Website not listed on Google profile”** when `website_url_source === 'google_cse'` (dedupe if present)

`raw_gbp_payload` column unchanged.

---

## Operator UI

**Location:** `Prospect/Show.jsx` Profile card, Website row.

| `website_url_source` | Display |
|----------------------|---------|
| `gbp` | Link only (no extra label — default) |
| `google_cse` | Link + micro: **Found via web search** · **High confidence** or **Medium confidence** |
| `operator` | Link + micro: **Edited manually** |

Expose fields on existing prospect Inertia payload (controller or resource). No search table changes.

---

## Enrichment interaction

`ProspectEnrichmentService::update()` when `website_url` changes after normalisation:

- Set `website_url_source = 'operator'`
- Set `website_discovery_confidence = null`, `website_discovered_at = null`
- Existing behaviour: GBP rescore, audit queue, `suppress_auto_report` unchanged

---

## Configuration

`config/scanner.php`:

```php
'website_discovery_enabled' => env('WEBSITE_DISCOVERY_ENABLED', true),
'website_discovery_timeout_seconds' => (int) env('WEBSITE_DISCOVERY_TIMEOUT', 8),
'website_discovery_num_results' => (int) env('WEBSITE_DISCOVERY_NUM_RESULTS', 5),
```

`config/services.php`:

```php
'google_cse' => [
    'key' => env('GOOGLE_CSE_KEY'),
    'cx'  => env('GOOGLE_CSE_CX'),
],
```

Document setup in deployment docs: create Programmable Search Engine (web search), restrict to general web, add billing if needed. Rough cost: one query per no-website prospect on combined/a11y searches (~25 max per operator search).

---

## Observability

| Event | Level | Fields |
|-------|-------|--------|
| `website_discovery.matched` | info | `search_id`, `place_id`, `confidence`, `host` |
| `website_discovery.skipped` | info | `search_id`, `place_id`, `reason` (`disabled`, `api_error`, `no_results`, `no_match`) |

---

## Testing

### Unit

- `WebsiteDiscoveryService`: high match (name + city), medium (name only), blocklist rejection, no match
- `GoogleCustomSearchService`: HTTP fake success/failure/timeout

### Feature

- `ScorePlaceJob`: no `websiteUri`, CSE returns match → prospect has URL, `google_cse` source, `AuditSiteJob` pushed
- `ScorePlaceJob`: CSE failure → no URL, no audit, search still completes
- `ScorePlaceJob`: GBP already has website → no CSE HTTP call (Http::fake assert)
- `ProspectEnrichmentService`: edit URL → `operator` source, confidence cleared

---

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| Wrong business (common name) | Two-tier matching; city required for high tier |
| Directory/social false positive | Shared weak-host blocklist |
| CSE latency on search queue | Timeout + fail-open; optional disable flag |
| API cost | Only no-website + a11y/combined; ~1 query per such prospect |
| GBP score looks “fixed” while listing is weak | Explicit “not on Google profile” flag for outreach |

---

## Implementation checklist

1. Migration + model fillable/casts
2. `GoogleCustomSearchService` + `WebsiteDiscoveryService`
3. Expose `isWeakWebsiteHost` for reuse (public method or `WeakWebsiteHost` helper)
4. `ScorePlaceJob` integration
5. `ProspectEnrichmentService` source reset
6. Inertia prospect payload + `Prospect/Show.jsx`
7. Config/env + deployment doc snippet
8. Tests
