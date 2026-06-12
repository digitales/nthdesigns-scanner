# CPC benchmarks for outreach

Operational guide for setting, storing, and using cost-per-click (CPC) figures in GBP outreach emails.

CPC is **optional**. It frames the paid-search alternative (“businesses in your category spend £X per click on Google Ads…”) and is only injected when the pitch angle is **GBP** or **Both**. Accessibility-only pitches ignore it.

**Related:** [Google Ads CPC integration](integrations/google-ads-cpc.md) (API setup and fetch).

---

## What gets stored

| Location | Scope | Fields |
|---|---|---|
| `market_cpc_defaults` | Per user + niche + city + country | `cpc_benchmark`, `cpc_source`, `cpc_keywords`, `cpc_geo_target` |
| `searches` | One prospect scan run | Same CPC fields — snapshot for that search |
| `outreach_emails` | One generated email | `cpc_benchmark`, `cpc_source` — audit trail of what was used |

**Keywords** (`cpc_keywords` JSON array) record the seed phrases used for a Google Ads lookup or your Keyword Planner research. They are editable on the search results page and saved with the market default.

---

## How CPC flows into outreach

When outreach emails are generated, CPC is resolved in this order:

1. **Outreach form override** — value entered on `/outreach` before Generate (applies to whole batch)
2. **Search CPC** — `searches.cpc_benchmark` for that prospect’s search
3. **Omit** — no CPC line in the AI prompt

The outreach form **pre-fills** from the queue when all prospects share one search with the same CPC. If the queue spans multiple searches with different CPCs, the field stays empty and each prospect uses its own search value at generate time.

---

## Recommended workflow (avoid unnecessary API fees)

CPC lookup and niche search are **independent**. By default, running a scan does **not** call Google Ads.

```text
1. /search — enter niche + city
2. Fetch CPC (Google Ads or Load saved) OR type CPC manually
3. Run scan — Places API only
4. Review prospects on /searches/{id} — adjust CPC/keywords if needed
5. Generate reports → queue outreach → generate emails
```

| Step | Places API | Google Ads API |
|---|---|---|
| Fetch from Google Ads on `/search` | No | Yes |
| Load saved on `/search` | No | No |
| Run scan | Yes | No |
| Fetch from Google Ads on search results | No | Yes |
| Generate outreach | No | No |

Keep `GOOGLE_ADS_CPC_AUTO_FETCH=false` (default) unless you explicitly want Google Ads to run on every new search.

---

## UI reference

### New search (`/search`)

- **CPC benchmark** — optional; sent with the search if filled manually
- **Load saved** — reads `market_cpc_defaults` for niche + city (no external API)
- **Fetch from Google Ads** — `POST /market-cpc/fetch` (shown when Google Ads is configured)

New searches **without** a manual CPC inherit the market default automatically if one exists.

### Search results (`/searches/{id}`)

- Edit **CPC benchmark** and **seed keywords** (one per line)
- **Save default** — updates this search and `market_cpc_defaults`
- **Fetch from Google Ads** — re-runs lookup for this niche + city (does not re-run Places discovery)

### Outreach (`/outreach`)

- **CPC benchmark** — optional batch override
- **Agency name** — sign-off in generated copy
- **Pitch angle** — Auto / GBP / A11y / Both

Prospects without a report are skipped on generate.

---

## Manual CPC (Keyword Planner)

When Google Ads API is not configured:

1. Open [Keyword Planner](https://ads.google.com) (free with a Google Ads account)
2. Search 3–5 **commercial local** terms, e.g. `private dentist birmingham`
3. Set location to your city; note **top of page bid (high range)** or median CPC
4. Enter the figure on `/search` or search results; add keywords in the seed field
5. Save default

Typical UK local ranges: roughly £3–25/click depending on niche (dental/legal higher, trades lower).

---

## HTTP routes

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/market-cpc/load` | Load saved market default (DB only) |
| `POST` | `/market-cpc/fetch` | Google Ads lookup → save market default |
| `PATCH` | `/searches/{id}/cpc` | Update search + market default |
| `POST` | `/searches/{id}/cpc/fetch` | Google Ads lookup for existing search |

---

## CLI

```bash
# Print lookup result (no save)
php artisan google-ads:cpc "dental practice" Birmingham

# Save to market_cpc_defaults for a user
php artisan google-ads:cpc "dental practice" Birmingham --save --user=1
```

---

## Environment (Google Ads — optional)

See [integrations/google-ads-cpc.md](integrations/google-ads-cpc.md) for full setup.

```env
GOOGLE_ADS_ENABLED=false              # set true when credentials are ready
GOOGLE_ADS_CPC_AUTO_FETCH=false       # recommended: keep false
```

---

## Migrations

CPC-related columns require:

```bash
php artisan migrate
```

Migrations:

- `2026_06_12_120000_add_cpc_benchmark_to_searches_and_outreach_emails`
- `2026_06_12_140000_add_market_cpc_defaults_and_search_cpc_keywords`
