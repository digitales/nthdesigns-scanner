# Google Ads CPC integration

> **Status: dormant — not approved for production use (June 2026)**  
> Google rejected a Basic Access application for this tool with: *“Tools that offer only keyword research are not allowed by the Google Ads API Policy.”*  
> **Use [Keyword Planner in the Google Ads UI](../cpc-benchmarks.md) and enter CPC manually** — that is the supported operator workflow.

This document describes optional code that fetches local keyword CPC benchmarks via the [Google Ads API](https://developers.google.com/google-ads/api/docs/start) REST API. It remains in the codebase but should not be enabled unless Google grants API access under a **different permissible use** (e.g. genuine campaign management).

**Operator guide:** [CPC benchmarks for outreach](../cpc-benchmarks.md)

---

## Google Ads API policy (keyword-only tools)

| Use case | API access |
|---|---|
| Keyword research / CPC lookup only | **Not permitted** — use Keyword Planner UI |
| Campaign creation, management, reporting | May qualify for Basic Access |
| Internal tool with keyword planning as one small feature of ads management | Case-by-case |

Reapplying with the same keyword-only use case is unlikely to succeed. Google’s compliance team directed applicants to the **Keyword Tool in the Ads front-end**.

---

## Design principles (codebase)

1. **CPC lookup is separate from niche search** — Places discovery and Google Ads keyword planning are different APIs.
2. **`GOOGLE_ADS_CPC_AUTO_FETCH` defaults to `false`** — no Google Ads call when you click Run scan.
3. **Keywords are recorded** — seed phrases stored in `cpc_keywords` for auditability.
4. **Market defaults persist** — one row per user + niche + city; new searches inherit without re-fetching.

---

## If API access is obtained in future

### Prerequisites

1. **Google Ads account** with billing profile.
2. **Developer token** with **Basic Access** and permissible use **Researching keywords and recommendations** (only if your product qualifies under Google’s policy — not keyword-only tools).
3. **Google Cloud project** with Google Ads API enabled.
4. **OAuth 2.0** refresh token (scope `https://www.googleapis.com/auth/adwords`).
5. **Customer ID** (GBP client account, digits only). MCC: set `GOOGLE_ADS_LOGIN_CUSTOMER_ID`.

Design doc template: [google-ads-api-design-document.md](google-ads-api-design-document.md)

### Environment

```env
GOOGLE_ADS_ENABLED=true
GOOGLE_ADS_CPC_AUTO_FETCH=false

GOOGLE_ADS_API_VERSION=v18
GOOGLE_ADS_DEVELOPER_TOKEN=
GOOGLE_ADS_CUSTOMER_ID=
GOOGLE_ADS_LOGIN_CUSTOMER_ID=    # optional MCC
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_REFRESH_TOKEN=
```

### UI fetch (when enabled)

| Action | Places API | Google Ads API |
|---|---|---|
| Fetch on `/search` (`POST /market-cpc/fetch`) | No | Yes |
| Fetch on search results (`POST /searches/{id}/cpc/fetch`) | No | Yes |
| CLI `google-ads:cpc` | No | Yes |

### CLI

```bash
php artisan google-ads:cpc "dental practice" Birmingham
php artisan google-ads:cpc "dental practice" Birmingham --save --user=1
```

---

## Lookup algorithm (API)

**Seed keywords** (`CpcKeywordSeeder`):

```text
{niche} {city}
{niche} in {city}
local {niche} {city}
best {niche} {city}
{niche} near {city}
```

**CPC value:** median of `averageCpcMicros` (fallback: top-of-page bid micros) from `generateKeywordIdeas`.

---

## Database

| Store | Purpose |
|---|---|
| `market_cpc_defaults` | Reusable default per user + niche + city |
| `searches.cpc_*` | Snapshot for one search run |
| `outreach_emails.cpc_*` | Audit trail at email generation |

Manual saves set `cpc_source = manual`. API fetch sets `cpc_source = google_ads`.

---

## Architecture

| Class | Role |
|---|---|
| `GoogleAdsAccessTokenProvider` | OAuth refresh → cached access token |
| `GoogleAdsClient` | REST client |
| `GoogleAdsGeoTargetResolver` | City → geo target constant |
| `CpcKeywordSeeder` | Niche + city → seed keywords |
| `GoogleAdsKeywordPlanService` | `generateKeywordIdeas` → CPC result |
| `MarketCpcLookupService` | Fetch + save to market defaults |
| `MarketCpcController` | `/market-cpc/load` and `/fetch` |
| `FetchSearchCpcJob` | Async fetch for a search row |
| `CpcBenchmarkResolver` | Resolve CPC at outreach generate time |

Uses the REST API directly (no `googleads/google-ads-php` dependency).

---

## OAuth setup (reference)

If credentials are needed for a future approved use case:

1. Enable **Google Ads API** in Google Cloud Console.
2. OAuth consent screen → scope `https://www.googleapis.com/auth/adwords` → add test users.
3. Create **Web application** OAuth client with redirect URI `https://developers.google.com/oauthplayground`.
4. [OAuth Playground](https://developers.google.com/oauthplayground/) → authorise adwords scope → exchange for refresh token.

**Do not** paste the Playground redirect URL into the scope field — scope is `https://www.googleapis.com/auth/adwords`.

Account structure for Keyword Planner (manual path): **Manager (MCC)** for developer token; **client account** for Planner and `GOOGLE_ADS_CUSTOMER_ID`.

---

## Troubleshooting

| Symptom | Check |
|---|---|
| Basic Access rejected (keyword-only) | Expected — use Keyword Planner; see [cpc-benchmarks.md](../cpc-benchmarks.md) |
| Keyword Planner “No account” | Open client account, not MCC; link accounts; add billing |
| Fetch button hidden | `GOOGLE_ADS_ENABLED=false` (default) or incomplete OAuth |
| CPC not in outreach | Pitch angle is A11y-only, or no CPC on search |
