# Outreach Queue — Clickable Prospects — Design Spec

**Date:** 2026-05-29  
**Status:** Implemented  
**Screens:** D — `/outreach` (`Outreach/Index.jsx`), C — `/prospects/{id}` (`Prospect/Show.jsx`)

---

## Goal

Let operators open a queued prospect’s **operator detail page** (audit, report tools, notes, outreach history) directly from the outreach queue, and return to outreach via Back — without leaving the outreach workflow for review.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Click destination | `/prospects/{prospect_id}` (same as Saved / Search / Reports) |
| Navigation mechanism | Inertia `Link` wrapping queue chip content (not row `onClick` only) |
| Outreach context | Append query param `?from=outreach` on links from the queue |
| Back from detail | When `from=outreach`: label **Back to outreach**, href **`/outreach`** |
| Back default | Unchanged: **Back to {niche}** → `/searches/{search.id}` |
| No report | Still navigable — detail page is where report is generated/previewed |
| Remove (×) control | Stays on queue chip; must not trigger navigation (`preventDefault` + `stopPropagation`) |
| Right-column email headings | Out of scope for v1 |
| In-page queue selection (`.queue-chip.active`) | Out of scope — prototype pattern deferred |
| Backend outreach payload | No change — `prospect_id` already present |

---

## UX

### Outreach queue (`Outreach/Index.jsx`)

Each queue list item (`queue-chip`) becomes a navigable surface:

- **Primary action:** Click anywhere on the chip (except remove) → `/prospects/{prospect_id}?from=outreach`
- **Remove:** Existing `router.delete` on ×; event isolated from link navigation
- **Visual:** Existing `.queue-chip` hover styles suffice; link inherits card styling (`textDecoration: 'none'`, `color: 'inherit'` — same pattern as Saved warm-lead cards)
- **Accessibility:** Real `<a href>` via `Link` so keyboard and “open in new tab” work

### Prospect detail (`Prospect/Show.jsx`)

`PageHeader` back behaviour is driven by server-provided navigation props:

| Condition | `back` label | `onBack` / href |
|-----------|--------------|-----------------|
| `from=outreach` query param | Back to outreach | `/outreach` |
| Default | Back to {niche} | `/searches/{search.id}` |

---

## Data & API

### Backend — `ProspectController::show`

When `request->query('from') === 'outreach'`, add to Inertia payload:

```php
'navigation' => [
    'back_href'  => '/outreach',
    'back_label' => 'Back to outreach',
],
```

When param absent, pass existing search-based navigation (or equivalent inline props today):

```php
'navigation' => [
    'back_href'  => "/searches/{$prospect->search->id}",
    'back_label' => "Back to {$prospect->search->niche}",
],
```

Consolidate `Prospect/Show` to read `navigation.back_href` and `navigation.back_label` instead of hard-coded search values.

### Frontend — `Outreach/Index.jsx`

For each `selection` item, wrap chip body in:

```jsx
<Link href={`/prospects/${item.prospect_id}?from=outreach`} style={{ textDecoration: 'none', color: 'inherit', flex: 1, minWidth: 0 }}>
  {/* business name, badges, report status */}
</Link>
```

Remove button remains a sibling outside the link (or inside list item with stopPropagation on the link’s parent layout — prefer sibling structure: `li` flex row with `Link` + remove button).

### Edge cases

| Condition | Behaviour |
|-----------|-----------|
| Prospect in queue, no report | Link works; detail shows report generation / pending audit |
| User bookmarks `/prospects/{id}?from=outreach` | Back still goes to outreach (acceptable) |
| User opens detail from Search/Saved | No `from` param; search back unchanged |
| Remove while hovering chip | Only removes from queue |

---

## Testing

### Feature tests (`tests/Feature/ProspectShowTest.php` or new file)

1. **With outreach context:** `GET /prospects/{id}?from=outreach` → Inertia `Prospect/Show` includes `navigation.back_href` = `/outreach` and `navigation.back_label` = `Back to outreach`.
2. **Without param:** `GET /prospects/{id}` → navigation points to originating search (existing behaviour).

### Manual

1. Add prospects to outreach queue → click chip → lands on prospect detail.
2. Back → returns to `/outreach` with queue intact.
3. Click × on chip → removes only; no navigation.
4. Open detail from search → Back still goes to search.

---

## Files to touch

| File | Change |
|------|--------|
| `resources/js/Pages/Outreach/Index.jsx` | `Link` on queue chips; isolate remove button |
| `resources/js/Pages/Prospect/Show.jsx` | Use `navigation` props for `PageHeader` back |
| `app/Http/Controllers/ProspectController.php` | Build `navigation` from `from` query param |
| `tests/Feature/ProspectShowTest.php` | Assert navigation props |

---

## Out of scope

- Clickable generated-email section headers on outreach right column
- In-page active queue selection / scroll-to-email (design prototype)
- Changing public report URL or `OutreachEmailCard` preview link behaviour
