# CPC benchmarks for outreach

Operational guide for setting, storing, and using cost-per-click (CPC) figures in GBP outreach emails.

CPC is **optional**. It frames the paid-search alternative (“businesses in your category spend £X per click on Google Ads…”) and is only injected when the pitch angle is **GBP** or **Both**. Accessibility-only pitches ignore it.

---

## Supported approach: Keyword Planner (manual)

**Google does not approve Google Ads API access for keyword-only / CPC lookup tools.** A Basic Access application for this use case was rejected (June 2026) with:

> *Tools that offer only keyword research are not allowed by the Google Ads API Policy.*

Use **[Keyword Planner](https://ads.google.com/aw/keywordplanner)** in the Google Ads UI, then enter the CPC in the scanner. See [Choosing a CPC value](#choosing-a-cpc-value) below.

The in-app **Fetch from Google Ads** button remains in the codebase for operators who obtain API access under a different permissible use in future, but **`GOOGLE_ADS_ENABLED` should stay `false`** for normal operation.

---

## What gets stored

| Location | Scope | Fields |
|---|---|---|
| `market_cpc_defaults` | Per user + niche + city + country | `cpc_benchmark`, `cpc_source`, `cpc_keywords`, `cpc_geo_target` |
| `searches` | One prospect scan run | Same CPC fields — snapshot for that search |
| `outreach_emails` | One generated email | `cpc_benchmark`, `cpc_source` — audit trail of what was used |

**Keywords** (`cpc_keywords` JSON array) record the seed phrases from your Keyword Planner research. They are editable on the search results page and saved with the market default.

**CPC sources:** `manual`, `keyword_planner_csv`, `google_ads`, `market_default`

---

## How CPC flows into outreach

When outreach emails are generated, CPC is resolved in this order:

1. **Outreach form override** — value entered on `/outreach` before Generate (applies to whole batch)
2. **Search CPC** — `searches.cpc_benchmark` for that prospect’s search
3. **Omit** — no CPC line in the AI prompt

The outreach form **pre-fills** from the queue when all prospects share one search with the same CPC. If the queue spans multiple searches with different CPCs, the field stays empty and each prospect uses its own search value at generate time.

---

## Recommended workflow

CPC entry is **independent** of niche search — running a scan never requires Keyword Planner.

```text
1. Keyword Planner — research CPC for niche + city (2–3 min)
2. /search — enter niche + city + CPC (or Load saved from a previous market)
3. Run scan — Places API only
4. /searches/{id} — import Keyword Planner CSV or adjust CPC/keywords → Save default
5. Generate reports → queue outreach → generate emails
```

### Import from Keyword Planner

On the search results page, use **Import from Keyword Planner** to upload the CSV export from Google Ads (Discover new keywords → download). The import:

1. Stores every keyword row that has a **top of page bid (high range)** value
2. Excludes non-commercial terms (graduate, jobs, careers, etc.) when calculating the CPC median
3. Rounds the median to the nearest £0.50
4. Saves immediately to this search and the market default (`cpc_source = keyword_planner_csv`)

Manual entry and **Save default** still work if you prefer to paste keywords or tweak the CPC after import.

| Step | Places API | Keyword Planner |
|---|---|---|
| Keyword Planner research | No | Yes (manual, in Google Ads UI) |
| Load saved on `/search` | No | No (database only) |
| Run scan | Yes | No |
| Save CPC on search results | No | No |
| Generate outreach | No | No |

---

## Keyword Planner setup

Keyword Planner runs against a **client Google Ads account**, not a manager (MCC) account alone.

1. Use a **client account** with billing on file (spend not required).
2. If you use an MCC, link the client under the manager first.
3. Open [Keyword Planner](https://ads.google.com/aw/keywordplanner) while viewing the **client** account.
4. **Discover new keywords** → enter 3–5 **commercial local** terms for your niche.
5. Set **location** to your target city.

---

## Choosing a CPC value

From Keyword Planner results, use the **top of page bid (high range)** column:

1. **Filter to commercial terms** — e.g. for private dental, use `private dental clinic near me`, not `nhs dental near me` (NHS terms are much lower and wrong for private-practice outreach).
2. **Take the median** of the high bids on relevant rows (typically 4–6 keywords).
3. **Round** to one decimal or nearest 50p for outreach copy (e.g. median £6.38 → **£6.50**).

**Example (private dental):**

| Keyword | High bid (£) |
|---|---|
| private dental clinic near me | 6.75 |
| dentist private near me | 6.37 |
| private dental practices | 6.28 |
| private dental clinic | 6.38 |

Median ≈ £6.38 → use **£6.50** in the app.

Typical UK local ranges: roughly £3–25/click depending on niche (dental/legal higher, trades lower).

---

## UI reference

### New search (`/search`)

- **CPC benchmark** — optional; enter from Keyword Planner or leave blank to inherit a saved default
- **Load saved** — reads `market_cpc_defaults` for niche + city (no external API)

New searches **without** a manual CPC inherit the market default automatically if one exists.

### Search results (`/searches/{id}`)

- Edit **CPC benchmark** and **seed keywords** (one per line)
- **Import from Keyword Planner** — upload CSV export; auto-calculates CPC and saves
- **Save default** — updates this search and `market_cpc_defaults` (`cpc_source = manual`)

### Outreach (`/outreach`)

- **CPC benchmark** — optional batch override
- **Agency name** — sign-off in generated copy
- **Pitch angle** — Auto / GBP / A11y / Both

Prospects without a report are skipped on generate.

---

## HTTP routes (manual CPC)

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/market-cpc/load` | Load saved market default (DB only) |
| `PATCH` | `/searches/{id}/cpc` | Update search + market default |
| `POST` | `/searches/{id}/cpc/import` | Import Keyword Planner CSV |

---

## Environment

```env
GOOGLE_ADS_ENABLED=false              # keep false — API not approved for keyword-only use
GOOGLE_ADS_CPC_AUTO_FETCH=false       # keep false
```

Optional API integration (dormant): [integrations/google-ads-cpc.md](integrations/google-ads-cpc.md)

---

## Migrations

```bash
php artisan migrate
```

- `2026_06_12_120000_add_cpc_benchmark_to_searches_and_outreach_emails`
- `2026_06_12_140000_add_market_cpc_defaults_and_search_cpc_keywords`
