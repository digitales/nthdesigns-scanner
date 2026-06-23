# Site Search — Design Spec

**Date:** 2026-06-23  
**Status:** Approved  
**Screens:** `AppShell` (top-nav search bar), `/find` (`Find/Index.jsx`)

---

## Goal

Add a **tenant-scoped site search** so the authenticated operator can find prospects and related data across their account from a top-nav search bar, with results on a dedicated page.

This is distinct from **starting a niche/city scan** (`/search`, `Search` model). Site search is **lookup** across existing data.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Entry point | Search bar in `AppShell` top bar (between nav and tools) |
| Interaction | Submit on Enter or button → navigate to results page (no typeahead v1) |
| Results layout | Grouped by type (sections with counts); empty sections hidden |
| Scope | Prospects, scans, lists, tags, notes, reports, bookings |
| Matching | Substring `LIKE` v1; multi-token AND across fields; case-insensitive |
| Ignored prospects | Excluded from **Prospects** section only; notes/reports/bookings still searchable |
| Expired/archived | Still searchable |
| Per-section limit | 10 results |
| Min query length | 2 characters |
| Max query length | 100 characters |
| Backend approach | `SiteSearchProvider` interface; `SqlSiteSearchProvider` for v1 |
| MCP | Out of scope v1 |
| Fuzzy / Scout | Out of scope v1; provider swap later |

---

## Routing

| Route | Name | Handler |
|-------|------|---------|
| `GET /find` | `find.index` | `SiteSearchController@index` |

Query string: `?q={query}`

Behind existing `auth` middleware.

---

## UX

### Top-nav search bar

- Component: `SearchBar.jsx` in `app-tools` area of `AppShell`
- Placeholder: `Search prospects, scans, lists…`
- Preserves `q` value when on `/find`
- Submit → `GET /find?q={query}`

### Results page (`/find`)

- `PageHeader`: “Search results for **{query}**”
- Section order (fixed):
  1. Prospects
  2. Scans
  3. Lists
  4. Tags
  5. Notes
  6. Reports
  7. Bookings
- Each section: heading + count, e.g. `Prospects (12)`
- Max 10 rows per section
- Row click destinations:

| Type | Destination |
|------|-------------|
| Prospect | `/prospects/{id}` |
| Scan | `/searches/{id}` |
| List | `/lists/{id}` |
| Tag | `/lists/browse?tags[]={name}` |
| Note | `/prospects/{prospect_id}` |
| Report | `/prospects/{prospect_id}` |
| Booking | `/bookings` |

### Empty / short query states

- `q` missing, whitespace-only, or &lt; 2 chars: no DB queries; message “Enter at least 2 characters to search”
- Valid query, zero hits: “No results for **{query}**” with hint to try business name, website, or niche

---

## Backend architecture

```
AppShell (SearchBar)
  → GET /find?q=…
  → SiteSearchController@index
  → SiteSearchRequest (validation)
  → SiteSearchService
      → SiteSearchProvider (interface)
          → SqlSiteSearchProvider (v1)
  → SiteSearchResource
  → Find/Index.jsx
```

### New files

| File | Purpose |
|------|---------|
| `app/Contracts/SiteSearchProvider.php` | Provider interface |
| `app/Services/SiteSearch/SqlSiteSearchProvider.php` | SQL `LIKE` implementation |
| `app/Services/SiteSearch/SiteSearchService.php` | Orchestration + min-length guard |
| `app/Data/SiteSearchResult.php` | Grouped result DTO |
| `app/Http/Controllers/SiteSearchController.php` | Inertia controller |
| `app/Http/Requests/SiteSearchRequest.php` | `q` validation |
| `app/Http/Resources/SiteSearchResource.php` | Frontend shape |
| `resources/js/Components/ui/SearchBar.jsx` | Nav input |
| `resources/js/Pages/Find/Index.jsx` | Results page |
| `config/site_search.php` | Limits (optional) |

Register `SiteSearchProvider` → `SqlSiteSearchProvider` in `AppServiceProvider`.

---

## Tenancy

Every query scoped to `auth()->user()`:

| Entity | Scope |
|--------|-------|
| Prospects | `whereHas('search', fn ($q) => $q->where('user_id', $user->id))` |
| Scans | `$user->searches()` |
| Lists | `$user->prospectLists()` |
| Tags | `$user->tags()` |
| Notes | `where('user_id', $user->id)` + prospect belongs to user |
| Reports | `whereHas('prospect.search', fn ($q) => $q->where('user_id', $user->id))` |
| Bookings | `whereHas('prospect.search', fn ($q) => $q->where('user_id', $user->id))` |

### Ignored prospects

Reuse `ProspectExclusionService::ignoredPlaceIdsAmong()` to build an exclusion set for the Prospects section. Other sections are unaffected.

---

## Searchable fields

Substring match (`LIKE %term%`) unless noted. Multi-token: split on whitespace; **each token** must match at least one searchable field (AND across tokens).

Escape `%` and `_` in user input before `LIKE`.

### Prospects

`business_name`, `website_url`, `email`, `phone`, `address`, `place_id`, `companies_house_number`

Row display: business name; secondary line = website or parent scan city.

### Scans

`niche`, `city`, `country`, `submitted_url`

Row display: niche + city; secondary = date + status.

### Lists

`name`, `description`

Row display: name; secondary = type (Manual/Smart) + item count.

### Tags

`name`

Row display: tag name; secondary = prospect count.

### Notes

`body`

Row display: truncated body (~120 chars); secondary = parent prospect business name.

### Reports

Joined prospect: `business_name`; report `token`

Row display: prospect name; secondary = view count + viewed status.

### Bookings

`attendee_name`, `attendee_email`, `attendee_phone`, `note`

Row display: attendee name; secondary = prospect name + formatted `starts_at`.

### Ordering

Within each section: most recently updated/created first (recency, not relevance score).

---

## Error handling

| Case | Behaviour |
|------|-----------|
| Unauthenticated | Redirect to login (middleware) |
| Query &lt; 2 chars | Prompt message, no queries |
| Special chars `%`, `_` | Escaped for literal `LIKE` |
| Zero results | Empty state copy |
| Rate limiting | None v1 |

---

## Config

`config/site_search.php`:

```php
return [
    'min_query_length' => 2,
    'max_query_length' => 100,
    'per_section_limit' => 10,
];
```

---

## Testing

`tests/Feature/SiteSearchTest.php`:

- Guest cannot access `/find`
- Query too short → prompt, no results
- Prospect match by `business_name`
- Ignored prospect excluded from prospects section
- Scan match by `niche`
- Cross-tenant isolation (user A data invisible to user B)
- Multi-token AND behaviour
- Tag result includes correct browse URL

---

## Out of scope (v1)

- MCP `site_search` tool
- Nav autocomplete / typeahead
- Fuzzy typo tolerance
- Laravel Scout / Meilisearch
- Per-section “view all” pagination
- Search history / analytics
- Database indexes (defer until performance review)

---

## Future upgrade path

Implement `MeilisearchSiteSearchProvider` (or similar) behind `SiteSearchProvider`. UI and controller unchanged. Enables fuzzy matching and better ranking without redesign.
