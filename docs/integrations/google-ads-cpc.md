# Google Ads CPC integration

Optional integration that fetches local keyword CPC benchmarks via the [Google Ads API](https://developers.google.com/google-ads/api/docs/start) REST API.

Results are stored in **`market_cpc_defaults`** (reusable per niche + city) and on individual **`searches`** when fetched from a search results page. Outreach inherits CPC automatically — see [CPC benchmarks for outreach](../cpc-benchmarks.md).

---

## Design principles

1. **CPC lookup is separate from niche search** — Places discovery and Google Ads keyword planning are different APIs with different costs.
2. **`GOOGLE_ADS_CPC_AUTO_FETCH` defaults to `false`** — no Google Ads call when you click Run scan unless you opt in.
3. **Keywords are recorded** — seed phrases are stored in `cpc_keywords` for auditability and manual review.
4. **Market defaults persist** — one row per user + niche + city; new searches inherit without re-fetching.

---

## Prerequisites

1. **Google Ads account** with billing profile (spend not required for planning metrics).
2. **Developer token** — [Apply in API Center](https://ads.google.com/aw/apicenter). Basic access is enough to start.
3. **Google Cloud project** with the Google Ads API enabled.
4. **OAuth 2.0 credentials** (Desktop or Web) with a **refresh token** authorised for scope:
   ```
   https://www.googleapis.com/auth/adwords
   ```
5. **Customer ID** of the Ads account to query (digits only, no dashes). Use a **GBP-denominated** account for UK outreach copy.

If you manage client accounts from an MCC, set `GOOGLE_ADS_LOGIN_CUSTOMER_ID` to the manager account ID.

---

## Environment

```env
# Recommended production defaults
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

Optional tuning in `config/google_ads.php`:

| Key | Default | Purpose |
|---|---|---|
| `max_seed_keywords` | 5 | Seed phrases built from niche + city |
| `page_size` | 50 | Max keyword ideas read from API |
| `geo_targets` | `[]` | Static `city\|country` → geo constant map |

---

## Obtaining a refresh token

1. Create OAuth credentials in [Google Cloud Console](https://console.cloud.google.com/apis/credentials).
2. Use the [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/):
   - Authorise scope `https://www.googleapis.com/auth/adwords`
   - Exchange for refresh token
3. Store the refresh token in `GOOGLE_ADS_REFRESH_TOKEN`.

Google’s [PHP client OAuth guide](https://developers.google.com/google-ads/api/docs/client-libs/php/oauth-web) covers the same flow in more detail.

---

## How to fetch CPC (without running a search)

### UI — new search page (`/search`)

1. Enter **niche** and **city** (and country).
2. Click **Fetch from Google Ads** — calls `POST /market-cpc/fetch` only.
3. CPC and keywords pre-fill; **no Places API call**, no search row created.
4. Click **Run scan** when ready (Places fees only).

**Load saved** reads a previous market default from the database (free).

### UI — search results (`/searches/{id}`)

**Fetch from Google Ads** re-runs the lookup for that search’s niche + city and updates the search row and market default. Does not re-run prospect discovery.

### CLI

```bash
# Lookup only — prints median CPC and seed keywords
php artisan google-ads:cpc "dental practice" Birmingham --country=GB

# Lookup and persist market default
php artisan google-ads:cpc "dental practice" Birmingham --save --user=1
```

---

## API cost matrix

| Action | Places API | Google Ads API |
|---|---|---|
| Run scan (`POST /searches`) | Yes | No (unless `GOOGLE_ADS_CPC_AUTO_FETCH=true`) |
| Fetch on `/search` (`POST /market-cpc/fetch`) | No | Yes |
| Load saved (`POST /market-cpc/load`) | No | No |
| Fetch on search results (`POST /searches/{id}/cpc/fetch`) | No | Yes |
| CLI `google-ads:cpc` | No | Yes |
| Generate outreach | No | No |

---

## Automatic lookup on search create (opt-in)

When `GOOGLE_ADS_CPC_AUTO_FETCH=true` and `GOOGLE_ADS_ENABLED=true`, creating a search **without** a manual CPC dispatches `FetchSearchCpcJob` on the `searches` queue.

The job:

1. Builds seed keywords via `CpcKeywordSeeder` (e.g. `dental practice Birmingham`, `dental practice in Birmingham`)
2. Resolves geo target (`config/google_ads.php` map or `suggestGeoTargetConstants`)
3. Calls `customers/{id}:generateKeywordIdeas`
4. Computes **median** CPC from `averageCpcMicros`, falling back to top-of-page bid micros
5. Upserts `market_cpc_defaults` and updates the search row with CPC + keywords

Failures are logged; the search pipeline continues.

---

## Lookup algorithm

**Seed keywords** (`CpcKeywordSeeder`):

```text
{niche} {city}
{niche} in {city}
local {niche} {city}
best {niche} {city}
{niche} near {city}
```

For `GB`, also: `{niche} {city} uk`. Capped at `max_seed_keywords` (default 5).

**CPC value:** median of positive micros values across returned keyword ideas. Stored in pounds (account currency assumed GBP for UK operators).

---

## Database

### `market_cpc_defaults`

| Column | Type | Notes |
|---|---|---|
| `user_id` | FK | Per operator |
| `niche`, `city`, `country` | string | Normalised lowercase niche/city |
| `cpc_benchmark` | decimal | £ per click |
| `cpc_source` | string | `google_ads`, `manual`, … |
| `cpc_keywords` | json | Seed keyword array |
| `cpc_geo_target` | string | e.g. `geoTargetConstants/9041139` |

Unique on `(user_id, niche, city, country)`.

### `searches` (CPC columns)

`cpc_benchmark`, `cpc_source`, `cpc_keywords`, `cpc_geo_target` — snapshot for the search run.

### `outreach_emails` (CPC columns)

`cpc_benchmark`, `cpc_source` — what was used when the email was generated.

---

## Architecture

| Class | Role |
|---|---|
| `GoogleAdsAccessTokenProvider` | OAuth refresh → cached access token |
| `GoogleAdsClient` | REST client (developer token + bearer auth) |
| `GoogleAdsGeoTargetResolver` | City → `geoTargetConstants/{id}` |
| `CpcKeywordSeeder` | Niche + city → seed keyword list |
| `GoogleAdsKeywordPlanService` | `generateKeywordIdeas` → `CpcBenchmarkResult` |
| `MarketCpcLookupService` | Fetch + save to market defaults (no Places) |
| `MarketCpcDefaultService` | CRUD + apply default to new searches |
| `MarketCpcController` | `POST /market-cpc/load` and `/fetch` |
| `FetchSearchCpcJob` | Async fetch for a search row |
| `CpcBenchmarkResolver` | Resolve CPC at outreach generate time |

Uses the REST API directly (no `googleads/google-ads-php` dependency).

---

## Optional geo overrides

Add static geo IDs to `config/google_ads.php` to skip the suggest API call:

```php
'geo_targets' => [
    'birmingham|GB' => 'geoTargetConstants/9041139',
],
```

Find IDs via the [Geo targets reference](https://developers.google.com/google-ads/api/data/geotargets).

---

## Limitations

- Account currency should match outreach market (GBP for UK copy).
- Low-volume niches may return empty metrics — set CPC manually.
- No in-app OAuth UI; credentials are env-only.
- Google Ads rate limits apply per developer token.
- `cpc_keywords` on manual save are operator-provided; Google Ads fetch populates them automatically.

---

## Troubleshooting

| Symptom | Check |
|---|---|
| Fetch button hidden | `GOOGLE_ADS_ENABLED=true` and all OAuth/token env vars set |
| “No CPC data returned” | Niche too obscure; try manual Keyword Planner entry |
| Wrong £ amount | Account currency not GBP; or sparse keyword ideas — verify seeds |
| CPC not in outreach | Pitch angle is A11y-only, or no CPC on search and no form override |
| Places charged on CPC fetch | You ran **Run scan** — CPC fetch alone does not call Places |
