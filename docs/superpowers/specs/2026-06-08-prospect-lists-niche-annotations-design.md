# Prospect Lists & Niche Annotations — Design Spec

**Date:** 2026-06-08  
**Status:** Approved  
**Screens:** `/lists` (new), `/niches` (annotate panel), `/prospects/{id}` (tags + add-to-list), `/s/{token}` (public share)

---

## Goal

Give operators a solo pipeline for market triage and prospect outreach:

1. Annotate **niches** (globally and per market) with notes and tags.
2. Curate **prospect lists** — manual collections and smart saved filters — with distinct UI icons for each.
3. Track **follow-up** via per-list status pipeline and due dates.
4. **Share** a curated prospect sheet externally (no contact details or report links).

Niche annotations and prospect lists are **first-class and linked but independent**. Lists do not replace Outreach or Saved filters; they add named, persistent pipeline tooling on top.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Workflow | Niche annotations + prospect lists both first-class; soft-linked via niche label + city |
| Niche annotation scope | Global (niche label) **and** per-market (niche × city) |
| Lists | Manual collections **and** smart/saved filters |
| List type icons | Manual = list/bookmark icon; Smart = filter/funnel icon |
| Follow-up | Status pipeline **plus** `follow_up_at` per manual list item |
| Sharing (v1) | Per-user only; no team collaboration |
| External share | Curated sheet: names, scores, flags, notes, tags — **no** phone, email, website, report URLs |
| Tags | Hybrid: config-suggested tags + user-created tags with autocomplete |
| Architecture | **Lists hub** (new `/lists` section); dedicated niche annotation tables |
| `prospect_notes` | Keep existing table; do not migrate to polymorphic notes in v1 |
| Outreach queue | Unchanged in v1 |

---

## Context (existing system)

| Area | Today |
|------|-------|
| Niches (`/niches`) | Aggregate opportunity scores per niche×city; ignore/include toggles only |
| Prospects | Full search records; `prospect_notes` (user + body); no tags |
| Saved (`/saved`) | Cross-search filtered view of all prospects (not named lists) |
| Outreach (`/outreach`) | Per-user queue for email generation |
| Public sharing | Audit reports at `/r/{token}`; CSV export from Saved |

`Search` already stores `niche` and `city`, enabling soft links from market annotations to search results without new FKs.

---

## Architecture

```text
┌─────────────┐     annotate      ┌──────────────────┐
│ Global Niche│◄──────────────────│ Operator notes/  │
│  (label)    │                   │ tags (global)    │
└──────┬──────┘                   └──────────────────┘
       │
       ▼
┌─────────────┐     annotate      ┌──────────────────┐
│ Market      │◄──────────────────│ Operator notes/  │
│ niche×city  │                   │ tags (market)    │
└──────┬──────┘                   └──────────────────┘
       │ "Run full scan"
       ▼
┌─────────────┐     prospects     ┌──────────────────┐
│ Search      │──────────────────►│ Prospect         │
│ niche+city  │                   │ notes, tags      │
└─────────────┘                   └────────┬─────────┘
                                           │
                    ┌──────────────────────┼──────────────────────┐
                    ▼                      ▼                      ▼
            ┌──────────────┐        ┌──────────────┐        ┌──────────────┐
            │ Manual list  │        │ Smart list   │        │ Outreach     │
            │ (membership) │        │ (filter JSON)│        │ (unchanged)  │
            └──────┬───────┘        └──────────────┘        └──────────────┘
                   │ status + follow_up_at
                   ▼
            ┌──────────────┐
            │ Share link   │
            │ /s/{token}   │
            └──────────────┘
```

### Linking rule

- **Market** = `(niche_label, city)` per user.
- Markets match `Search` rows via `search.niche` + `search.city` (same user).
- Market annotation panel shows count of related searches and shortcut **Add search prospects to list…**
- No automatic list population when a search completes in v1.

---

## Data model

### Niche notes — `niche_notes`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `user_id` | FK users | Owner |
| `niche_label` | string | From `config/niches.php` label |
| `city` | string, nullable | `null` = global niche note; set = market note |
| `body` | text | Note content |
| `created_at`, `updated_at` | timestamps | |

Index: `(user_id, niche_label, city)` for listing. Notes are append-only threads (same pattern as `prospect_notes`).

### Tags — `tags`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `user_id` | FK users | Owner |
| `name` | string | Case-insensitive unique per user |
| `color` | string, nullable | Hex or token name; default neutral |
| `created_at`, `updated_at` | timestamps | |

Unique: `(user_id, lower(name))` via application normalisation or generated column.

### Tag assignments — `taggables`

| Column | Type | Notes |
|--------|------|-------|
| `tag_id` | FK tags | |
| `taggable_type` | string | `niche_global`, `niche_market`, `prospect` |
| `taggable_id` | bigint | For niches: `niche_label` stored as id via hash, **or** use string key columns — see implementation note below |

**Implementation note:** For niche tags (no numeric entity id), use explicit columns on a dedicated pivot instead of generic polymorphic id:

#### `niche_tag_assignments`

| Column | Notes |
|--------|-------|
| `user_id` | Owner |
| `tag_id` | FK |
| `niche_label` | Required |
| `city` | Nullable; null = global |

Unique: `(user_id, tag_id, niche_label, city)`.

#### `prospect_tag_assignments`

| Column | Notes |
|--------|-------|
| `prospect_id` | FK |
| `tag_id` | FK |

Unique: `(prospect_id, tag_id)`. Prospects inherit user scope via `prospect.search.user_id`.

### Prospect lists — `prospect_lists`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `user_id` | FK users | Owner |
| `name` | string | Display name |
| `type` | enum | `manual` \| `smart` |
| `description` | text, nullable | Optional |
| `filter` | json, nullable | Smart lists only; see filter schema |
| `created_at`, `updated_at` | timestamps | |

### Manual list membership — `prospect_list_items`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `prospect_list_id` | FK | Must be `type = manual` |
| `prospect_id` | FK | |
| `status` | enum | See pipeline below |
| `follow_up_at` | datetime, nullable | Due date for follow-up |
| `created_at`, `updated_at` | timestamps | |

Unique: `(prospect_list_id, prospect_id)`.

Smart lists have **no** `prospect_list_items` rows; membership is computed at query time.

### Pipeline status enum

`ListItemStatus`:

| Value | Label |
|-------|-------|
| `new` | New |
| `contacted` | Contacted |
| `replied` | Replied |
| `booked` | Booked |
| `closed_won` | Closed won |
| `closed_lost` | Closed lost |

Default: `new` when prospect added to manual list. Status is **per list item** — same prospect may differ across lists.

### External share — `shared_lists`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `user_id` | FK users | Owner |
| `prospect_list_id` | FK | |
| `token` | string, unique | Unguessable; same generation approach as `prospect_reports.token` |
| `snapshot` | json, nullable | Frozen rows at share time for smart lists |
| `expires_at` | datetime, nullable | Optional expiry |
| `revoked_at` | datetime, nullable | Soft revoke |
| `created_at`, `updated_at` | timestamps | |

**Manual lists:** share reads live membership at request time (or snapshot on create — **snapshot on create** so recipients see a stable sheet).

**Smart lists:** snapshot matching prospects at share time into `snapshot` JSON; no live re-query on public page.

---

## Smart list filter schema

Extends `FilterProspectListRequest` / `ProspectListQuery` fields:

```json
{
  "niche": "Dental Clinic",
  "city": "Birmingham",
  "min_score": 60,
  "warm": true,
  "tags": ["priority"],
  "has_note": false
}
```

All keys optional. `tags` filters prospects having **any** listed tag (OR). `ProspectListQuery` gains joins for `prospect_tag_assignments`.

---

## Suggested tags (config)

`config/scanner.php` or new `config/prospect_lists.php`:

```php
'suggested_tags' => [
    'priority',
    'compliance-angle',
    'gbp-angle',
    'warm-lead',
    'hold',
    'partner-handoff',
],
```

Creating a tag on first use inserts into `tags` if not present (case-insensitive dedupe).

---

## External share payload

Public page `GET /s/{token}` — no authentication, `noindex`.

### Included per prospect row

| Field | Source |
|-------|--------|
| Business name | `prospect.business_name` |
| Niche | `prospect.search.niche` |
| City | `prospect.search.city` |
| Combined score | `prospect.combined_score` |
| GBP score | `prospect.gbp_score` |
| Top flags | First 3 `gbp_flags` / `a11y_flags` |
| Tags | Prospect tags |
| Notes | `prospect_notes.body` (all notes, or most recent — **most recent note only** in v1) |
| List status | From `prospect_list_items` or snapshot |
| Follow-up date | From item or snapshot |

### Explicitly excluded

- Phone, email (if ever added), website URL
- `report_url` / `/r/{token}` links
- Raw payloads, place_id, internal IDs

### Errors

| Case | Response |
|------|----------|
| Unknown token | 404 |
| Revoked | 404 |
| Expired | 404 with friendly message |

Rate-limit public route consistent with `/r/{token}`.

---

## UX

### Niches (`/niches`)

- Row action **Annotate** opens side panel (reuse `NicheSamplePanel` width pattern).
- Panel tabs: **Global** | **This market** (`{city}`).
- Each tab: notes thread (newest first, add form at bottom) + tag chip input.
- Market tab footer: related search count, **Add to list…**, existing **Run full scan** unchanged.
- Manage Niches panel: global annotate shortcut per catalog row.

### Lists (`/lists`) — new

- Nav item **Lists** in `AppShell`.
- `/saved` redirects to `/lists` for one release cycle (preserve query params); Saved page deprecated.
- Index:
  - Card or table rows with **type icon** (manual vs smart)
  - Prospect count
  - Overdue badge (`follow_up_at < now()` on manual items; smart lists show overdue count from computed members without persisted due dates — **due dates on smart lists are view-only via prospect's manual list items or out of scope**)

**Clarification:** Due dates exist only on **manual list items** in v1. Smart lists show status/tags from prospect data but cannot set per-list due dates unless prospect is also on a manual list.

- Create flows:
  - **New manual list** — name + optional description
  - **New smart list** — name + filter builder (reuse Saved `FilterBar` fields + tag multi-select)
- Detail:
  - Manual: membership table with status dropdown, date picker, remove from list
  - Smart: computed results table; **Save to manual list** bulk action
  - **Share** button → creates `shared_lists` row, copies URL to clipboard
- Sort index by **Due soon** (manual lists with nearest `follow_up_at` first).

### Prospect detail (`/prospects/{id}`)

- Tag chips (editable) in sidebar or notes section
- **Add to list…** dropdown (manual lists only)
- Existing notes form unchanged
- Navigation `from=list` query param: Back → `/lists/{id}` (mirror outreach pattern)

### Follow-up surfacing

- List detail: highlight rows where `follow_up_at` is past
- List index: overdue count badge on manual lists
- Dashboard widget: out of scope v1

---

## API & routes

### Authenticated

| Method | Path | Action |
|--------|------|--------|
| GET | `/lists` | Index |
| POST | `/lists` | Create manual or smart list |
| GET | `/lists/{list}` | Show |
| PATCH | `/lists/{list}` | Update name, description, filter (smart) |
| DELETE | `/lists/{list}` | Delete |
| POST | `/lists/{list}/items` | Add prospect(s) — manual only |
| PATCH | `/lists/{list}/items/{item}` | Update status, `follow_up_at` |
| DELETE | `/lists/{list}/items/{item}` | Remove prospect |
| POST | `/lists/{list}/share` | Create share link |
| DELETE | `/shared-lists/{sharedList}` | Revoke |
| POST | `/niche-notes` | Add niche/market note |
| POST | `/niche-tags` | Assign/remove niche tag |
| POST | `/prospects/{prospect}/tags` | Assign/remove prospect tag |

Redirect: `GET /saved` → `/lists` (301 or Inertia redirect).

### Public

| Method | Path | Action |
|--------|------|--------|
| GET | `/s/{token}` | Shared list sheet |

### Authorization

- All mutations scoped to `auth()->id()`.
- Policies: `ProspectListPolicy`, `SharedListPolicy` — owner only.
- Prospect add: prospect must belong to user's search.

---

## Backend components

| Component | Responsibility |
|-----------|----------------|
| `ProspectListController` | CRUD lists, items, share |
| `NicheAnnotationController` | Notes + tags for global/market |
| `ProspectTagController` | Prospect tag assign/remove |
| `PublicSharedListController` | Token lookup + public Inertia page |
| `ProspectListQuery` | Extend with tag filter + smart list execution |
| `SharedListSnapshotBuilder` | Build snapshot JSON for share |
| `ListItemStatus` enum | Pipeline values |
| `ProspectListPolicy` | Owner authorization |

### Enums

```php
enum ProspectListType: string { case Manual = 'manual'; case Smart = 'smart'; }
enum ListItemStatus: string { case New = 'new'; /* ... */ }
enum NicheTagScope: string { case Global = 'global'; case Market = 'market'; } // if needed in requests
```

---

## Testing

| Test | Asserts |
|------|---------|
| `ProspectListTest` | Create manual list, add/remove items, unique constraint |
| `ProspectListItemTest` | Status transitions, `follow_up_at` update |
| `SmartProspectListTest` | Filter JSON resolves correct prospects |
| `NicheAnnotationTest` | Global vs market notes isolated; per-user scope |
| `ProspectTagTest` | Tag dedupe, assign/remove, filter by tag |
| `SharedListTest` | Token access, revoke, expiry, excluded fields absent |
| `SavedRedirectTest` | `/saved` → `/lists` |

Use `RefreshDatabase`, existing factories for `User`, `Search`, `Prospect`.

---

## Out of scope (v1)

- Team sharing / multi-user collaborative lists
- Email or push reminders for due dates
- Auto-add prospects when search completes
- MCP tools for lists or niche annotations
- Merging Outreach queue into Lists
- Due dates on smart list computed members (without manual list membership)
- Configurable per-share field toggles (fixed payload per spec)
- Dashboard "Due this week" widget

---

## File checklist (implementation reference)

| File | Action |
|------|--------|
| `database/migrations/*_create_prospect_lists_and_annotations_tables.php` | Lists, items, niche_notes, tags, niche_tag_assignments, prospect_tag_assignments, shared_lists |
| `app/Models/ProspectList.php` | Create |
| `app/Models/ProspectListItem.php` | Create |
| `app/Models/NicheNote.php` | Create |
| `app/Models/Tag.php` | Create |
| `app/Models/SharedList.php` | Create |
| `app/Enums/ListItemStatus.php` | Create |
| `app/Enums/ProspectListType.php` | Create |
| `app/Http/Controllers/ProspectListController.php` | Create |
| `app/Http/Controllers/NicheAnnotationController.php` | Create |
| `app/Http/Controllers/PublicSharedListController.php` | Create |
| `app/Policies/ProspectListPolicy.php` | Create |
| `app/Queries/ProspectListQuery.php` | Extend |
| `app/Services/SharedListSnapshotBuilder.php` | Create |
| `resources/js/Pages/Lists/Index.jsx` | Create |
| `resources/js/Pages/Lists/Show.jsx` | Create |
| `resources/js/Pages/SharedList/Show.jsx` | Create |
| `resources/js/Components/Niches/NicheAnnotatePanel.jsx` | Create |
| `resources/js/Pages/Niches/Index.jsx` | Extend |
| `resources/js/Pages/Prospect/Show.jsx` | Extend |
| `resources/js/Components/ui/AppShell.jsx` | Add Lists nav |
| `routes/web.php` | Register routes + redirect |
| `config/prospect_lists.php` | Suggested tags |
