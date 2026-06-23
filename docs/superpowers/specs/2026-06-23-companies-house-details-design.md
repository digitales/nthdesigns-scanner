# Companies House details — Design Spec

**Date:** 2026-06-23  
**Status:** Approved  
**Scope:** Extend operator-facing Companies House reporting on the prospect page with on-demand filing history, officers, financial figures (where extractable), and outreach talking points.

**Approach:** Single on-demand details job (Approach A from brainstorming).

---

## Goal

The current Companies House integration provides a qualification signal: match status, summary, and risk flags (dissolved, overdue accounts, charges, incorporation age). Operators also need:

1. **Qualification** — richer registry context to decide whether to pursue a prospect
2. **Outreach hooks** — concrete talking points from recent company changes
3. **Financial insight** — turnover/profit when filed accounts disclose them

Financial data is intentionally best-effort: most small/micro companies file abridged accounts with no profit and loss. The feature surfaces figures when machine-readable iXBRL tags exist; otherwise it explains why figures are unavailable.

This is operator-only (prospect show page). It does not appear in shareable client reports.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Purpose | Qualification, outreach hooks, internal financial insight |
| Changes coverage | Filing history, officers, financial filing signals — UI shows short summary + CH link |
| Financials | Extract turnover, profit, net assets, employees from latest electronic iXBRL accounts when tags exist |
| Surface | Operator prospect page only |
| Loading | Core check stays fast; **Load details** fetches extended data on demand |
| Caching | Store normalized payload in `companies_house_details`; invalidate when `companies_house_number` changes |
| Partial failure | Activity and officers saved even if financial parsing fails |
| Job persistence | All-or-nothing per job run (no partial save on timeout) |
| Talking points | Rule-based server strings (no LLM), max 5 |

---

## Architecture

```
CompaniesHouseControl.jsx
        │
        ├── POST .../companies-house/check     → CheckCompaniesHouseJob (unchanged)
        │
        └── POST .../companies-house/details   → LoadCompaniesHouseDetailsJob (new)
                    │
                    ▼
        CompaniesHouseDetailsService
                    │
        ┌───────────┼───────────┐
        ▼           ▼           ▼
  filing-history  officers   document API
                                │
                                ▼
                    CompaniesHouseAccountsParser (iXBRL tag extraction)
                                │
                                ▼
              companies_house_details JSON column
```

### New components

| Component | Responsibility |
|-----------|----------------|
| Migration | Add `companies_house_details`, `companies_house_details_loaded_at` to `prospects` |
| `CompaniesHouseDetailsService` | Fetch, normalize, and persist extended CH data |
| `CompaniesHouseAccountsParser` | Download and parse iXBRL accounts for financial line items |
| `CompaniesHouseTalkingPointsGenerator` | Build outreach hook strings from details + existing flags |
| `LoadCompaniesHouseDetailsJob` | Async job (60s timeout, 2 tries, same queue as check) |
| `CompaniesHouseController` (modified) | Add `details` action returning 202 |
| `CompaniesHouseLookupService` (modified) | Clear details columns when company number changes |
| `CompaniesHouseControl.jsx` (modified) | Company details section with Load/Refresh UI |
| `ProspectShowResource` (modified) | Expose details fields to frontend |
| `components.css` (modified) | Styles for snapshot, activity, financials panels |

### Unchanged

- `CheckCompaniesHouseJob` and core match/assess flow
- `raw_companies_house_payload` storage
- Registered company registration flow

---

## Data model

### New columns on `prospects`

| Column | Type | Purpose |
|--------|------|---------|
| `companies_house_details` | JSON, nullable | Normalized extended data (shape below) |
| `companies_house_details_loaded_at` | timestamp, nullable | When details were last fetched |

### Invalidation

When `companies_house_number` changes on recheck or new match, set `companies_house_details` and `companies_house_details_loaded_at` to `null`.

### `companies_house_details` JSON shape

```json
{
  "company_snapshot": {
    "company_type": "ltd",
    "registered_office": "1 High Street, Bristol BS1 4ST",
    "sic_codes": ["86230"],
    "incorporated_on": "2018-03-15",
    "accounts": {
      "next_due": "2026-09-30",
      "last_made_up_to": "2025-03-31",
      "last_type": "micro-entity",
      "overdue": false
    }
  },
  "recent_activity": [
    {
      "date": "2025-06-01",
      "category": "officers",
      "description": "Appointment of director — Jane Smith",
      "type": "AP01"
    }
  ],
  "officers": [
    {
      "name": "Jane Smith",
      "role": "director",
      "appointed_on": "2025-06-01",
      "resigned_on": null
    }
  ],
  "financials": {
    "status": "available",
    "reason": null,
    "period_end": "2025-03-31",
    "filing_date": "2025-06-15",
    "accounts_type": "full",
    "turnover": 450000,
    "profit_before_tax": 62000,
    "net_assets": 120000,
    "employees": 8
  },
  "talking_points": [
    "Director appointed 3 months ago — possible leadership change",
    "Turnover ~£450k in latest filed accounts"
  ],
  "links": {
    "filing_history": "https://find-and-update.company-information.service.gov.uk/company/12345678/filing-history",
    "latest_accounts_document": "https://..."
  }
}
```

Monetary values stored as whole pounds (integers). Null when not extracted.

### Financials status values

| Status | Meaning | UI copy |
|--------|---------|---------|
| `available` | At least one figure parsed | Show figures with period |
| `not_disclosed` | Filing exists but no P&L tags (micro/abridged) | "Accounts filed — turnover/profit not disclosed" |
| `paper_filed` | Latest accounts are paper/scanned | "Latest accounts paper-filed — figures not machine-readable" |
| `parse_failed` | iXBRL downloaded but extraction failed | "Could not extract figures — view document on Companies House" |
| `unavailable` | No accounts filing found | "No filed accounts found" |

---

## API

### New route

```
POST /prospects/{prospect}/companies-house/details
```

- Authorizes `view` on prospect (same as check)
- Returns `202 Accepted` with `{ "message": "Companies House details load queued." }`
- Dispatches `LoadCompaniesHouseDetailsJob`

### ProspectShowResource additions

- `companies_house_details` — object or `null`
- `companies_house_details_loaded_at` — ISO string or `null`

Frontend reloads prospect props after dispatch (same pattern as Recheck button).

---

## Backend: `CompaniesHouseDetailsService`

Called by `LoadCompaniesHouseDetailsJob`. Reuses CH API auth pattern from `CompaniesHouseLookupService`.

### Steps (independent sections — partial API failure allowed)

1. **Guard** — require `companies_house_number`; no-op if missing
2. **Company snapshot** — build from `raw_companies_house_payload` (SIC, registered office, accounts metadata, incorporation date). No extra profile API call when payload exists.
3. **Filing history** — `GET /company/{number}/filing-history?items_per_page=15` → normalize to `recent_activity` (newest first, max 15 stored, UI shows 8)
4. **Officers** — `GET /company/{number}/officers?register_view=true` → active officers only
5. **Financials** — find latest `category: accounts` filing where `paper_filed !== true` and `links.document_metadata` exists; download iXBRL; parse tags
6. **Talking points** — `CompaniesHouseTalkingPointsGenerator` from snapshot, activity, financials, and existing `companies_house_flags`
7. **Links** — filing history URL; latest accounts document URL when available
8. **Persist** — update `companies_house_details` and `companies_house_details_loaded_at`

If step 3 or 4 fails (HTTP error), that array is empty and the job continues. Financials failure sets appropriate `financials.status` without failing the job.

---

## Financial extraction: `CompaniesHouseAccountsParser`

### Find filing

Newest filing where `category === 'accounts'`, `paper_filed !== true`, and `links.document_metadata` is present.

### Download

Follow `document_metadata` link via Document API with same API key auth. Request iXBRL (`.xhtml`) format.

### Parse

Tag-based extraction from iXBRL inline elements (`ix:nonFraction`, `ix:nonNumeric`). No full XBRL engine dependency. Match common UK GAAP/FRS taxonomy names (first match wins):

| Field | Tag name candidates |
|-------|---------------------|
| Turnover | `TurnoverRevenue`, `Revenue`, `Turnover` |
| Profit | `ProfitLossOnOrdinaryActivitiesBeforeTax`, `ProfitLoss` |
| Net assets | `NetAssetsLiabilities`, `TotalAssetsLessCurrentLiabilities` |
| Employees | `AverageNumberEmployeesDuringPeriod`, `NumberEmployees` |
| Period end | `PeriodEnd`, balance sheet date tags |

If document downloads but no recognised tags found → `status: not_disclosed`.

---

## Talking points: `CompaniesHouseTalkingPointsGenerator`

Rule-based strings, max 5, no duplicates:

- Director appointed or resigned within 12 months
- Accounts overdue (from existing flags)
- Registered charges (from existing flags)
- Turnover/profit available with formatted figures
- Recently incorporated (< 365 days)

Prefer actionable outreach angles over restating raw flags.

---

## Error handling

| Failure | Behaviour |
|---------|-----------|
| CH API 429/5xx on one endpoint | Log warning; that section empty; job completes |
| No company number | Job no-ops |
| Document download fails | `financials.status = parse_failed` |
| Job timeout (60s) | Retry once; no partial save — all-or-nothing per run |
| API key missing | Details button hidden; same as existing check behaviour |

---

## UI (prospect page)

Extends existing **Companies House** card in `CompaniesHouseControl.jsx`.

### New section: Company details

Appears below **Registry check** when `companies_house_status` is `matched` or `caution`. Hidden for `no_match` and `dissolved`.

### Panels

1. **Snapshot** — company type, SIC, registered office, incorporation date, accounts due dates
2. **Latest accounts** — turnover, profit, net assets, employees when `financials.status === available`; otherwise status message
3. **Recent activity** — up to 8 events; link to full filing history on Companies House
4. **Officers** — active directors/secretaries; collapse with expand if more than 3
5. **Outreach hooks** — `talking_points` when non-empty

### States

| State | UI |
|-------|-----|
| Matched/caution, no details | **Load details** button + hint |
| Loading | Button disabled, "Loading details…" |
| Loaded | Full panels; **Refresh details** link |
| Stale (number changed) | Treat as not loaded |

### Formatting

- Money: `£450k` / `£1.2m` for large figures; `£62,000` below £100k
- Dates: same locale pattern as existing card
- Activity: one line — date · description
- External links open in new tab

### CSS

New BEM classes under existing `companies-house-*` namespace in `components.css`.

---

## Testing

### Unit tests

- `CompaniesHouseAccountsParser` — fixture iXBRL snippets (full accounts, micro-entity, malformed)
- `CompaniesHouseTalkingPointsGenerator` — rule combinations
- Details invalidation when company number changes

### Feature tests

- `POST .../details` returns 202 and authorizes correctly
- Job persists expected JSON with faked HTTP responses

### Fixtures

2–3 anonymised iXBRL samples in `tests/fixtures/companies-house/`.

---

## Out of scope

- Client-facing report section
- Third-party financial enrichment (Creditsafe, Endole, etc.)
- Historical financial trends (multi-year comparison)
- PSC (Persons with Significant Control) detail panel — may add later if officers panel proves insufficient
- Automatic details load on check — always on-demand

---

## Job config

```php
#[Tries(2)]
#[Timeout(60)]
class LoadCompaniesHouseDetailsJob implements ShouldQueue
{
    // Same queue connection as CheckCompaniesHouseJob (SearchQueue)
}
```
