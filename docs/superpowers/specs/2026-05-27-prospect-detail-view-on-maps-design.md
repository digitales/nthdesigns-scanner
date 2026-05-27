# Prospect Detail — View on Maps — Design Spec

**Date:** 2026-05-27  
**Status:** Implemented  
**Screen:** C — `/prospects/{id}` (`Prospect/Show.jsx`)

---

## Goal

Give operators a **View on Maps** action on the prospect detail page so they can open the Google Business listing without returning to search results. Parity with the existing search-results row action, placed in a dedicated sidebar card.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Placement | New **Location** card in right sidebar |
| Sidebar order | Public report → Outreach → **Location** → Profile |
| Action style | Text link under address (not button, not icon-only) |
| Maps URL | `https://www.google.com/maps/place/?q=place_id:{place_id}` (same as `Search/Show.jsx`) |
| Implementation | **Frontend-only:** expose `place_id` from `ProspectController`; build URL in JSX |
| Card visibility | Render only when `prospect.place_id` is truthy |
| Address display | Show full address when present; link-only if address missing |
| Fallback URL | None in v1 (no address-based Maps search) |
| Automated tests | None in v1 (manual verification only) |

---

## UX

### Location card

- **Title:** `Location`
- **Body:** Prospect `address` at 13px when set (matches Profile card density).
- **Link:** `View on Maps` directly below the address (or as sole content if no address).
- **Link styling:** Same pattern as Profile **Website** — `className="micro"`, `target="_blank"`, `rel="noopener noreferrer"`.
- **Out of scope:** Page header actions, ghost buttons, map embeds.

### Reference

Search results already implement the maps target:

```jsx
href={`https://www.google.com/maps/place/?q=place_id:${p.place_id}`}
```

(`resources/js/Pages/Search/Show.jsx`)

---

## Data & API

### Backend

`ProspectController::show` — add to the `prospect` Inertia payload:

```php
'place_id' => $prospect->place_id,
```

No migration, route, or policy changes. `place_id` is required on `prospects` and already authorized via existing `view` policy.

### Frontend

`resources/js/Pages/Prospect/Show.jsx`:

1. Conditionally render `<Card title="Location">` when `prospect.place_id` is set.
2. Insert after the Outreach card, before Profile.
3. Maps href: `https://www.google.com/maps/place/?q=place_id:${prospect.place_id}`.

### Edge cases

| Condition | Behaviour |
|-----------|-----------|
| `place_id` + `address` | Address text + link |
| `place_id`, no `address` | Link only |
| No `place_id` | Card not rendered |

---

## Files to change

| File | Change |
|------|--------|
| `app/Http/Controllers/ProspectController.php` | Include `place_id` in prospect array |
| `resources/js/Pages/Prospect/Show.jsx` | Location card + link |

---

## Test plan (manual)

1. From search results, open a prospect that shows the maps icon in the row.
2. Confirm **Location** appears between **Outreach** and **Profile**.
3. Address matches the page header subline when both are present.
4. **View on Maps** opens the correct listing in a new tab (same listing as search row action).
5. If a prospect has no address but has `place_id`, card shows link only.

---

## Non-goals

- Shared `LocationCard` component
- Server-computed `maps_url`
- Address-query fallback when `place_id` is missing
- Design handoff prototype header placement (ghost button in `PageHeader` actions)
