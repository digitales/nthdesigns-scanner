# Search History Index — Design Spec

**Date:** 2026-05-29  
**Status:** Approved  
**Screens:** `/search` (`Search/Index.jsx`), `/searches` (`Search/History.jsx`)

---

## Goal

Let operators view a paginated list of all searches they have run, reachable from a **View all** link on the recent-searches sidebar at `/search`.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Entry point | **View all** link on sidebar heading; cards still link to `/searches/{id}` |
| View all visibility | Always shown, even with 0–4 searches |
| List layout | Card list matching sidebar style |
| Pagination | Numbered pages, 20 per page, `?page=N` |
| Filters | None for v1 |
| Main nav | No new nav item; Search tab stays active on history via `searches.index` match |
| Extra card field | `total_found` shown on history page only |

---

## Routing

| Route | Name | Handler |
|-------|------|---------|
| `GET /search` | `search.index` | `SearchController::index` (unchanged) |
| `GET /searches` | `searches.index` | `SearchController::history` |
| `GET /searches/{search}` | `searches.show` | `SearchController::show` (unchanged) |

`GET /searches` must be registered **before** `GET /searches/{search}`.

---

## Backend

`SearchController::history()`:

- Query `auth()->user()->searches()->latest()->paginate(20)->withQueryString()`
- Map rows via shared `mapSearchSummary()` (same shape as sidebar)
- No `with('prospects')` on list queries

`SearchController::index()`:

- Align recent fetch to `take(4)` (remove UI slice)
- Reuse `mapSearchSummary()`

Authorisation: user-scoped query only; no policy change.

---

## Frontend

### Shared `SearchHistoryCard`

Used by sidebar and history page. Props: `search`, optional `showFound` for `total_found` label.

### `/search` sidebar

Heading row: **Recent searches** + **View all** → `/searches`.

### `/searches` history page

- `PageHeader`: back to `/search`, title "Your searches"
- Vertical card stack with `showFound`
- `EmptyState` when no searches + link to `/search`
- `Pagination` component: prev/next, page numbers, "Showing X–Y of Z"

### `AppShell`

Add `searches.index` to Search nav `match` array.

---

## Testing

- Guest redirected from `/searches`
- User sees only their searches
- 25 searches → 20 on page 1, 5 on page 2
- `/search` renders Inertia `Search/Index` with recent searches
