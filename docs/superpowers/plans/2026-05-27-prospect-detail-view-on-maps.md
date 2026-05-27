# Prospect Detail — View on Maps — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a **Location** sidebar card on prospect detail with address (when present) and a **View on Maps** text link that opens the Google Business listing via `place_id`.

**Architecture:** Expose `place_id` on the existing Inertia `ProspectController::show` payload; render a conditional `<Card title="Location">` in `Prospect/Show.jsx` between Outreach and Profile, reusing the same Maps URL as search results. No new components, routes, or migrations.

**Tech Stack:** Laravel 13, Inertia.js, React (`resources/js/Pages/Prospect/Show.jsx`).

**Spec:** `docs/superpowers/specs/2026-05-27-prospect-detail-view-on-maps-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Http/Controllers/ProspectController.php` | Add `place_id` to Inertia prospect array |
| `resources/js/Pages/Prospect/Show.jsx` | Location card + View on Maps link |

---

### Task 1: Expose `place_id` on prospect show

**Files:**
- Modify: `app/Http/Controllers/ProspectController.php` (prospect array in `show()`)

- [ ] **Step 1: Add `place_id` to the Inertia prospect payload**

In `app/Http/Controllers/ProspectController.php`, inside `show()`, add `place_id` immediately after `id` in the `prospect` array:

```php
'prospect' => [
    'id'               => $prospect->id,
    'place_id'         => $prospect->place_id,
    'business_name'    => $prospect->business_name,
    'address'          => $prospect->address,
    // ... rest unchanged
],
```

- [ ] **Step 2: Smoke-check the controller response**

Run (replace `{id}` with a real prospect id from your DB):

```bash
php artisan tinker --execute="echo json_encode(app(\App\Http\Controllers\ProspectController::class)->show(\App\Models\Prospect::first())->toResponse(request())->getData());"
```

Or open `/prospects/{id}` in the browser, open DevTools → Network → Inertia page props, and confirm `prospect.place_id` is present.

Expected: `place_id` is a non-empty string for scraped prospects.

- [ ] **Step 3: Commit (optional)**

```bash
git add app/Http/Controllers/ProspectController.php
git commit -m "feat(prospect): expose place_id on detail page"
```

---

### Task 2: Location card in prospect detail UI

**Files:**
- Modify: `resources/js/Pages/Prospect/Show.jsx` (sidebar `<aside>`)

- [ ] **Step 1: Add Location card between Outreach and Profile**

In `resources/js/Pages/Prospect/Show.jsx`, inside `<aside>`, insert the block below **after** the Outreach `</Card>` and **before** the Profile `<Card title="Profile">`:

```jsx
{prospect.place_id && (
    <Card title="Location">
        {prospect.address && (
            <p style={{ fontSize: 13, marginBottom: 8, lineHeight: 1.45 }}>
                {prospect.address}
            </p>
        )}
        <a
            href={`https://www.google.com/maps/place/?q=place_id:${prospect.place_id}`}
            target="_blank"
            rel="noopener noreferrer"
            className="micro"
        >
            View on Maps
        </a>
    </Card>
)}
```

Reference for URL parity: `resources/js/Pages/Search/Show.jsx` (maps icon `href`).

- [ ] **Step 2: Build frontend assets**

Run:

```bash
npm run build
```

Expected: Build completes with no errors.

- [ ] **Step 3: Manual verification**

1. Log in and open `/searches/{searchId}`.
2. Click a prospect row that shows the maps icon in row actions.
3. On `/prospects/{id}`, confirm sidebar order: **Public report** → **Outreach** → **Location** → **Profile**.
4. If address exists: text matches header subline; **View on Maps** is below it.
5. Click **View on Maps** — new tab opens the correct Google Business listing (compare with maps icon on search row for same prospect).
6. Optional: find or seed a prospect with `place_id` but null `address` — card shows link only.

- [ ] **Step 4: Commit (optional)**

```bash
git add resources/js/Pages/Prospect/Show.jsx
git commit -m "feat(prospect): add Location card with View on Maps link"
```

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Location card in sidebar | Task 2 |
| Order: report → outreach → location → profile | Task 2 |
| Text link, not button/icon | Task 2 |
| `place_id` URL same as search | Task 1 + Task 2 |
| Card only when `place_id` set | Task 2 |
| Address when present | Task 2 |
| No automated tests v1 | Manual steps in Task 2 |
| No `maps_url` / shared component | Omitted by design |

No placeholders; scope is two files only.
