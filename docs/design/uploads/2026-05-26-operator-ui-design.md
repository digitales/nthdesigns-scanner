# Operator UI — Design Spec

**Date:** 2026-05-26  
**Status:** Draft — pending review  
**Scope:** Complete `/saved`, `/reports`, `/outreach`, and CSV export before outreach automation or public-report changes.

---

## Goal

Give nthdesigns operators a full internal workflow to browse prospects, monitor report engagement, queue outreach targets, batch-generate emails, and export lists — without changing the audit pipeline, auto-report generation, or public report page in this slice.

---

## Decisions

| Topic | Decision |
|---|---|
| UI structure | Plan-faithful: three routes (`/saved`, `/reports`, `/outreach`) |
| Outreach queue persistence | `outreach_selections` table (per user, survives refresh) |
| Warm lead definition | **B — strict:** report `viewed_at` set, latest outreach `sent_at` set, `response_received` false |
| Settings page | Out of scope; agency name + CPC on outreach form only (request-scoped) |
| Report before outreach | Manual report generation remains; batch generate skips prospects without a report |
| AI integration | Keep existing `AnthropicService` (no Laravel AI SDK migration in this slice) |

---

## Warm lead query

A prospect is **warm** when all of the following hold:

1. Has a `prospect_reports` row with `viewed_at IS NOT NULL`
2. Has at least one `outreach_emails` row with `sent_at IS NOT NULL`
3. No `outreach_emails` row for that prospect has `response_received = true`

Use the **latest** outreach email by `created_at` for sent/response checks when displaying status on list rows.

**Reports dashboard “warm” badge:** `viewed_at` within the last 7 days (engagement signal, independent of outreach sent).

---

## Data model

### `outreach_selections`

```
id
user_id          → users, cascade delete
prospect_id      → prospects, cascade delete
created_at
updated_at

UNIQUE (user_id, prospect_id)
INDEX (user_id)
```

Prospect must belong to a search owned by the user (enforced in controller/policy).

### `exports`

```
id
user_id          → users
search_id        → searches, nullable (null = cross-search export)
filename         → string, e.g. prospects-2026-05-26.csv
row_count        → unsigned integer
created_at
updated_at
```

---

## Routes

| Method | Path | Action |
|---|---|---|
| GET | `/saved` | List prospects + filters + warm panel |
| GET | `/reports` | List reports + filters |
| GET | `/outreach` | Outreach workspace |
| POST | `/outreach/selections` | Add prospect(s) to queue `{ prospect_ids: [] }` |
| DELETE | `/outreach/selections/{prospect}` | Remove one |
| DELETE | `/outreach/selections` | Clear all (optional convenience) |
| POST | `/outreach/generate` | Batch dispatch outreach jobs |
| POST | `/exports` | Stream CSV from current saved filters |

All routes: `auth` middleware. Authorization via existing `ProspectPolicy` / `SearchPolicy` patterns.

**Redirect:** `GET /` → `/search` for authenticated users; guests keep Welcome.

---

## Controllers & queries

### `SavedProspectController@index`

- Base: `Prospect::query()` joined to `searches` where `searches.user_id = auth()->id()`
- Eager load: `search`, `report`, latest `outreachEmails` (limit 1)
- Default sort: `combined_score` DESC
- Filters (query params):

| Param | Type | Behaviour |
|---|---|---|
| `from`, `to` | date | Filter `prospects.created_at` |
| `niche` | string | `searches.niche` LIKE |
| `city` | string | `searches.city` LIKE |
| `scan_type` | enum | Exact match on `searches.scan_type` |
| `min_score` | int | `combined_score >=` |
| `dominant_angle` | enum | Exact match |
| `warm` | bool | Apply warm-lead scope (definition above) |

- **Warm panel:** When `warm` is not exclusively true, show up to 10 warm prospects at top (same query scope).
- Inertia props: `prospects`, `filters`, `warmLeads`, `meta` (counts).

### `ReportDashboardController@index`

- Base: `ProspectReport::query()` for reports whose `prospect.search.user_id = auth()->id()`
- Eager load: `prospect.search`, latest outreach email
- Default sort: `viewed_at` DESC NULLS LAST, then `created_at` DESC
- Filters: `niche`, `viewed` (bool), `warm` (viewed in last 7 days)

Columns passed to frontend: business_name, niche, city, token, public_url, view_count, viewed_at, viewer_ip, created_at, is_warm_badge (7-day rule), has_outreach_sent, response_received.

### `OutreachController`

**`index`**

- Load `outreach_selections` with prospect, search, report, latest outreach
- Props: `selection`, `defaults` (empty agency name, pitch `auto`)

**`storeSelection`**

- Validate `prospect_ids` array; authorize each prospect
- `firstOrCreate` on `(user_id, prospect_id)`

**`destroySelection`**

- Delete row for user + prospect

**`generate`**

- Validate: `agency_name` nullable string max 100, `pitch_angle` in `auto,gbp,accessibility,combined`, `cpc_benchmark` nullable numeric
- For each selected prospect:
  - If no `prospect_report`: skip, collect in `skipped` list
  - Else: `GenerateOutreachEmailJob::dispatch($prospect, $user, $options)` on `auditing` queue
- Redirect back with flash: generated count, skipped count

**Job change:** Extend `GenerateOutreachEmailJob` and `OutreachEmailGeneratorService` to accept optional `pitch_angle` override, `agency_name`, `cpc_benchmark` (only injected into prompt when set).

### `ExportController@store`

- Accept same filter params as `/saved`
- Build query identically
- `StreamedResponse` CSV with headers:

```
business_name, niche, city, country, phone, website_url,
combined_score, gbp_score, a11y_score, dominant_angle,
gbp_flags, a11y_flags, report_url,
outreach_subject, outreach_sent_at, response_received
```

- `flags` columns: semicolon-separated
- Create `Export` record with filename and `row_count`
- Return download response

---

## Frontend pages

### `Saved/Index.jsx`

- Filter form (GET, preserves state in URL)
- Warm leads panel (collapsible on mobile)
- Data table (reuse `Search/Show` table patterns + report/outreach columns)
- Actions: link to prospect, copy report URL, POST add to outreach (Inertia router.post)
- Export button → POST `/exports` with current query string

### `Reports/Index.jsx`

- Filter bar
- Table with copy-link buttons, warm badge, external report link
- Row links to prospect and outreach section if email exists

### `Outreach/Index.jsx`

- Two-column layout (stack on mobile)
- Left: queue list with remove buttons, clear all
- Right: generation form + results
- After generate: poll `only: ['emails', 'selection']` every 5s while any job pending (track via `outreach_emails` created in last minute without body — or flash + manual refresh for v1)
- Email cards: reuse components from `Prospect/Show.jsx` (extract shared `OutreachEmailCard` if duplication exceeds ~40 lines)

### `Search/Show.jsx` changes

- Checkbox column + “Add selected to outreach” / per-row “Add to outreach”
- Optional: show “In outreach” badge if prospect id in user’s selection (pass `outreachProspectIds` from controller)

### `AuthenticatedLayout.jsx`

- Nav: Search | Outreach (badge: selection count) | Saved | Reports
- Dashboard redirect unchanged (`/search`)

---

## Authorization

- `OutreachSelectionPolicy`: user owns selection row
- Reuse `ProspectPolicy::view` before add to queue or export row inclusion
- No cross-user selection access

---

## Error handling

- Add to queue: 403 if prospect not owned; 422 if duplicate (idempotent firstOrCreate — no error)
- Generate all: flash message listing skipped businesses (no report)
- Export: empty result → 422 with message “No prospects match filters”
- CSV streaming errors logged; user sees generic failure flash

---

## Testing

| Test | Type |
|---|---|
| Warm lead scope returns correct prospects | Feature |
| Selection add/remove scoped to user | Feature |
| Export creates record and returns CSV headers | Feature |
| Generate skips prospect without report | Feature |
| Saved filters (min_score, warm) | Feature |

Unit tests not required for Inertia pages; feature tests on controllers/scopes.

---

## Explicitly out of scope

- Auto `GenerateProspectReportJob` after `CombineScoresJob`
- Public report redesign (hide scores, violation screenshots, Lighthouse dials)
- `/settings` page and user preferences persistence
- Rate limiting, scheduled purge, production deploy
- `exports` download history UI (table exists; list page optional later)
- Laravel AI SDK migration

---

## Implementation order

1. Migrations: `outreach_selections`, `exports`
2. Models, policies, warm-lead query scope (`Prospect::scopeWarmLead`)
3. `SavedProspectController` + `Saved/Index.jsx`
4. `ExportController` + export feature test
5. `ReportDashboardController` + `Reports/Index.jsx`
6. Selection endpoints + `Search/Show` checkboxes
7. `OutreachController` + job/service options + `Outreach/Index.jsx`
8. Nav, `/` redirect, shared outreach card component

---

## Success criteria

- Operator can filter all prospects across searches and export CSV matching filters
- Operator can see all reports with view stats and 7-day warm badges
- Operator can build an outreach queue from search results and saved list
- Operator can batch-generate emails only for prospects with reports; others clearly skipped
- Warm leads panel shows only viewed + sent + no response prospects
