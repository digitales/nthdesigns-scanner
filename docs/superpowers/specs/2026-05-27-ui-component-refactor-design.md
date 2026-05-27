# UI Component Refactor & Design-System Compliance — Design Spec

**Date:** 2026-05-27  
**Status:** Implemented  
**Reference:** `docs/design/Design System.html`, `resources/css/tokens.css`, `resources/css/components.css`

---

## Goal

Eliminate duplicated UI markup across all Inertia views, consolidate on a single React component library aligned with the Prospect Scanner design system, and verify every surface matches `Design System.html` — delivered in **one PR**.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Scope | **Full app:** operator UI, marketing homepage, public report, auth, profile, settings, dashboard |
| Delivery | **One pass** — single effort/PR with ordered internal steps |
| Approach | **Extend `ui/` + delete Breeze components** — no wrapper/deprecation layer |
| Homepage structure | **Split:** shared primitives in `Components/ui/`; section components in `Pages/Welcome/components/` |
| Login | Keeps bespoke split-panel layout; uses shared `Brand`, `Eyebrow`, `ScoreBadge` (not `AuthLayout`) |
| Dashboard | Redirect `GET /dashboard` → `/search` (remove Breeze stub) |
| CSS | Keep `homepage.css` scoped to `.marketing-page`; normalize touched aliases to `--color-*` |
| New tests | None unless existing feature tests break during migration |

---

## Problem statement

The codebase has three overlapping UI stacks:

1. **Design-system stack** — `@/Components/ui` + `components.css` (operator pages)
2. **Raw CSS-in-JSX** — `className="btn btn-*"`, inline `style={{}}`, repeated `card` / `ptable` wrappers
3. **Laravel Breeze legacy** — `PrimaryButton`, `TextInput`, gray Tailwind on Profile/Settings/auth (except Login)

Local duplicates exist (`Login.jsx` defines its own `BrandMark`, `Eyebrow`, `ScoreBadge`). `HomePage.jsx` is ~990 lines with page-local helpers. Tables, filter bars, and stat strips are copy-pasted across Saved, Reports, and Search.

---

## Component architecture

### Folder layout

```
resources/js/Components/ui/           # App-wide design-system primitives
  Brand.jsx, Eyebrow.jsx
  Card.jsx, DataTable.jsx
  Input.jsx, Select.jsx
  StatsStrip.jsx, StatTile.jsx
  FilterBar.jsx, RowActions.jsx
  LinkButton.jsx                      # Inertia <Link> with btn-* classes
  FormError.jsx                       # Replaces standalone InputError where needed
  …existing exports (Button, Field, ScoreBadge, etc.)

resources/js/Pages/Welcome/components/   # Homepage-only sections
  Nav.jsx, AuditWidget.jsx, Hero.jsx, …

resources/js/Layouts/
  AuthLayout.jsx                      # Breeze auth shell (Register, Forgot, etc.)
```

### New `ui` components

Thin React wrappers over existing `components.css` classes — **no new visual language**.

| Component | Props (summary) | Replaces |
|-----------|-----------------|----------|
| `Card` | `title`, `pad`, `className`, `children` | `card`, `card-pad`, `card-title` |
| `DataTable` | `children` (thead/tbody), optional `className` | Border wrapper + `ptable` |
| `StatsStrip` | `children` | `.stats-strip` |
| `StatTile` | `label`, `value`, `warm?` | `.stat-tile` |
| `FilterBar` | `onSubmit`, `children` | `.filter-bar` form |
| `RowActions` | `children` | `.row-actions` |
| `Brand` | `product?`, `markOnly?` | Inline brand marks |
| `Eyebrow` | `children` | Login local + header pattern |
| `Input` | spreads to `<input className="input">` | Raw inputs |
| `Select` | spreads to `<select className="select">` | Raw selects |
| `LinkButton` | `kind`, `size`, `href`, Inertia `Link` props | `<Link className="btn …">` |
| `FormError` | `message` | `InputError` |

Export all new components from `resources/js/Components/ui/index.js`.

### Components to remove after migration

| File | Reason |
|------|--------|
| `Components/PrimaryButton.jsx` | → `ui/Button` `kind="primary"` |
| `Components/SecondaryButton.jsx` | → `kind="secondary"` |
| `Components/DangerButton.jsx` | → `kind="destructive"` |
| `Components/TextInput.jsx` | → `ui/Input` inside `Field` |
| `Components/InputLabel.jsx` | → `Field` label |
| `Components/InputError.jsx` | → `FormError` |
| `Components/Checkbox.jsx` (root) | → `ui/Checkbox` if still needed |
| `Layouts/GuestLayout.jsx` | → `AuthLayout` |

Keep `ApplicationLogo` only if still referenced outside auth; otherwise remove or limit to non-brand contexts.

### Layout changes

- **`AuthenticatedLayout`:** Remove Breeze `header` prop; all authenticated pages use `PageHeader` inside `<main className="page">`.
- **`AuthLayout`:** Paper background, centered card, `Brand` at top — used by Register, Forgot, Reset, Confirm, Verify.
- **`Login`:** Does not use `AuthLayout`; refactored to import shared primitives only.

---

## Page migration map

Internal order within the single PR:

| Step | Target | Actions |
|------|--------|---------|
| 1 | `Components/ui/*` | Implement new primitives; export from `index.js` |
| 2 | `Layouts/AuthLayout.jsx` | Create auth shell |
| 3 | Operator pages | `Search/Index`, `Search/Show`, `Saved/Index`, `Reports/Index`, `Outreach/Index`, `Prospect/Show` — adopt `Card`, `DataTable`, `FilterBar`, `StatsStrip`; remove duplicate inline layout |
| 4 | `OutreachEmailCard.jsx` | Align with `Card` / `Button` / `Badge` |
| 5 | `Report/Public.jsx` | `Button`, `LinkButton`, `SevChip`, `ScoreCard`; reduce inline styles; A–F grades only in copy |
| 6 | `Welcome/HomePage.jsx` | Split into `Welcome/components/*`; use `Button`, `LinkButton`, `Brand`; thin page shell |
| 7 | `Auth/Login.jsx` | Replace local helpers with `Brand`, `Eyebrow`, `ScoreBadge` |
| 8 | Breeze auth pages | `AuthLayout` + `Field` + `Input` + `Button` |
| 9 | `Settings/Index.jsx` | `AppShell` + `PageHeader` + `Card` + `Field`; health rows via `Status` / micro typography |
| 10 | `Profile/Edit.jsx` + partials | Three `Card` sections; `ui` form controls |
| 11 | `routes/web.php` + remove `Dashboard.jsx` | Redirect `GET /dashboard` → `/search` |
| 12 | Cleanup | Delete legacy Breeze components; grep for `gray-`, `PrimaryButton`, raw `btn` in pages |

### Homepage section extraction (indicative)

Move from `HomePage.jsx` into `Pages/Welcome/components/`:

- `Nav.jsx`
- `AuditWidget.jsx`
- `Hero.jsx` (hero + audit widget container)
- `HowItWorks.jsx`, `SampleReport.jsx`, `Evidence.jsx`, `Compare.jsx`, `Pricing.jsx`, `Faq.jsx`, `FinalCta.jsx` (names match existing section IDs where possible)

`HomePage.jsx` becomes imports + scroll/orchestration only (~80–120 lines target).

---

## Design-system compliance checklist

Post-migration audit — **zero violations** in `resources/js`:

| Design System section | Rule | Allowed API |
|----------------------|------|-------------|
| Tokens | `--color-*` in shared app code | No Tailwind `gray-*`, `bg-white` in pages/layouts |
| Buttons | § Buttons & inputs | `Button`, `LinkButton` only |
| Inputs | `.input`, `.select`, `.field-label` | `Field` + `Input` / `Select` |
| Score badges | `low` / `mid` / `high` | `ScoreBadge` + `scoreBand.js` |
| Angle / severity / status | Pills and chips | `AnglePill`, `SevChip`, `Status` |
| Cards | § Cards | `Card` |
| Tables | § Table | `DataTable` + `ptable` |
| Typography | Eyebrow, micro, lede | `PageHeader`, `Eyebrow` |
| Public report | Editorial, grades not numeric combined | `gradeColor`, no combined score in prospect-facing copy |

**Manual verification:** Open each route beside `Design System.html` and the relevant screen HTML (`Homepage.html`, prototype). Record intentional deltas in PR description.

---

## Testing & verification

| Check | Command / action |
|-------|------------------|
| PHPUnit | `php artisan test` |
| Frontend build | `npm run build` |
| Route smoke | `/`, `/login`, `/register`, `/search`, `/saved`, `/reports`, `/outreach`, `/settings`, `/profile`, `/r/{token}` |
| Grep gates | No `PrimaryButton`, `GuestLayout`, `className="btn` in `Pages/` (except inside `ui/` implementations) |

Do not add snapshot or visual regression tests in this pass unless required to fix a failure.

---

## Out of scope

- In-app `/design-system` gallery route
- Dark mode / `[data-theme="dark"]`
- Merging `homepage.css` into `components.css`
- Laravel AI SDK or backend changes
- New product features on any page

---

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| Large PR hard to review | Ordered commits matching migration map steps; PR description with checklist |
| Login regression | Test auth flows manually; keep layout structure, swap primitives only |
| Homepage visual drift | Section-by-section extraction without CSS changes in first pass |
| Profile tests assert HTML classes | Update assertions only if tests fail |

---

## Success criteria

1. All Inertia pages use `@/Components/ui` for interactive and structural patterns.
2. Breeze button/input components deleted; no dead imports.
3. `HomePage.jsx` under ~150 lines; sections in `Welcome/components/`.
4. Grep shows no gray Tailwind or duplicate `ScoreBadge` implementations in pages.
5. `php artisan test` and `npm run build` pass.
