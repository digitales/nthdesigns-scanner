# Google Ads CPC integration

Optional integration that fetches local keyword CPC benchmarks via the [Google Ads API](https://developers.google.com/google-ads/api/docs/start) and stores them on `searches.cpc_benchmark` with `cpc_source = google_ads`.

Outreach inherits the value automatically (see search-level CPC).

## Prerequisites

1. **Google Ads account** with billing profile (spend not required for planning metrics).
2. **Developer token** — [Apply in API Center](https://ads.google.com/aw/apicenter). Basic access is enough to start.
3. **Google Cloud project** with the Google Ads API enabled.
4. **OAuth 2.0 credentials** (Desktop or Web) with a **refresh token** authorised for scope:
   ```
   https://www.googleapis.com/auth/adwords
   ```
5. **Customer ID** of the Ads account to query (digits only, no dashes). Use a GBP-denominated account for UK outreach copy.

If you manage client accounts from an MCC, set `GOOGLE_ADS_LOGIN_CUSTOMER_ID` to the manager account ID.

## Environment

```env
GOOGLE_ADS_ENABLED=true
GOOGLE_ADS_CPC_AUTO_FETCH=true   # dispatch FetchSearchCpcJob on new searches without manual CPC
GOOGLE_ADS_DEVELOPER_TOKEN=
GOOGLE_ADS_CUSTOMER_ID=
GOOGLE_ADS_LOGIN_CUSTOMER_ID=    # optional
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_REFRESH_TOKEN=
```

## Obtaining a refresh token

1. Create OAuth credentials in [Google Cloud Console](https://console.cloud.google.com/apis/credentials).
2. Use the [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/) or `google-ads:cpc` after a one-off auth script:
   - Authorise scope `https://www.googleapis.com/auth/adwords`
   - Exchange for refresh token
3. Store the refresh token in `GOOGLE_ADS_REFRESH_TOKEN`.

Google’s official [PHP client setup guide](https://developers.google.com/google-ads/api/docs/client-libs/php/oauth-web) walks through the same flow in more detail.

## Manual lookup

```bash
php artisan google-ads:cpc "dental practice" Birmingham --country=GB
```

Prints seed keywords, resolved geo target, and median CPC (£).

## Automatic lookup

When `GOOGLE_ADS_CPC_AUTO_FETCH=true`, creating a search **without** a manual CPC dispatches `FetchSearchCpcJob` on the `searches` queue. The job:

1. Builds commercial seed keywords from niche + city
2. Resolves geo target (config map or `suggestGeoTargetConstants`)
3. Calls `generateKeywordIdeas`
4. Takes the **median** of `averageCpcMicros`, falling back to top-of-page bid micros
5. Writes `searches.cpc_benchmark` unless already set
6. Upserts **`market_cpc_defaults`** (per user + niche + city) and stores **`cpc_keywords`** on both the search and the market default

Failures are logged and do not block the search pipeline.

## Market defaults and keywords

| Store | Purpose |
|---|---|
| `market_cpc_defaults` | Reusable default for a niche + city — applied to new searches automatically |
| `searches.cpc_*` | Snapshot for this specific search run |
| `cpc_keywords` (JSON) | Seed keywords used for the lookup (editable on search results) |

Saving CPC on the **search results page** updates both the current search and the market default. Yes — **recording keywords is worth it**: it makes manual overrides auditable, shows what the Google Ads fetch used, and helps when revisiting a market months later.

## Optional geo overrides

Add static geo IDs to `config/google_ads.php` under `geo_targets` to skip the suggest call:

```php
'geo_targets' => [
    'birmingham|GB' => 'geoTargetConstants/9041139',
],
```

Find IDs via the API or [Geo targets reference](https://developers.google.com/google-ads/api/data/geotargets).

## Architecture

| Class | Role |
|---|---|
| `GoogleAdsAccessTokenProvider` | OAuth refresh → cached access token |
| `GoogleAdsClient` | REST client (developer token + bearer auth) |
| `GoogleAdsGeoTargetResolver` | City → `geoTargetConstants/{id}` |
| `CpcKeywordSeeder` | Niche + city → seed keyword list |
| `GoogleAdsKeywordPlanService` | Orchestrates lookup, returns £ CPC |
| `FetchSearchCpcJob` | Persists CPC on a `Search` row |

Uses the REST API directly (no `googleads/google-ads-php` dependency).

## Limitations (v1 scaffold)

- Account currency must match outreach market (GBP assumed for UK copy).
- Low-volume niches may return empty CPC metrics — set CPC manually on the search page.
- No UI for OAuth yet; credentials are env-only.
- Rate limits apply per developer token; auto-fetch is one API call per search.
