# UI Component Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate all Inertia views onto `@/Components/ui` primitives aligned with `Design System.html`, remove Breeze UI components, and split `HomePage.jsx` into focused section files — one PR.

**Architecture:** Add thin React wrappers for existing `components.css` classes; migrate pages in dependency order (primitives → layouts → operator → public → marketing → auth/profile → cleanup). Homepage sections live under `Pages/Welcome/components/`; shared atoms stay in `Components/ui/`.

**Tech Stack:** Laravel 13, Inertia v2, React 18, Vite, Tailwind v4 (token theme), existing `resources/css/tokens.css` + `components.css` + `homepage.css`.

**Spec:** `docs/superpowers/specs/2026-05-27-ui-component-refactor-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `resources/js/Components/ui/Card.jsx` | `.card` / `.card-pad` / `.card-title` |
| `resources/js/Components/ui/DataTable.jsx` | Bordered table shell + `ptable` |
| `resources/js/Components/ui/StatsStrip.jsx` | `.stats-strip` grid |
| `resources/js/Components/ui/StatTile.jsx` | `.stat-tile` |
| `resources/js/Components/ui/FilterBar.jsx` | `.filter-bar` form |
| `resources/js/Components/ui/RowActions.jsx` | `.row-actions` |
| `resources/js/Components/ui/Brand.jsx` | Brand mark + wordmark |
| `resources/js/Components/ui/Eyebrow.jsx` | Eyebrow label |
| `resources/js/Components/ui/Input.jsx` | `.input` + ref/focus |
| `resources/js/Components/ui/Select.jsx` | `.select` |
| `resources/js/Components/ui/LinkButton.jsx` | Inertia `Link` with `btn-*` |
| `resources/js/Components/ui/FormError.jsx` | Field-level error text |
| `resources/js/Components/ui/index.js` | Barrel exports |
| `resources/js/Layouts/AuthLayout.jsx` | Breeze auth shell |
| `resources/css/components.css` | Add `.auth-shell` layout block |
| `resources/js/Pages/Welcome/components/*.jsx` | Homepage sections |
| `resources/js/Pages/Welcome/HomePage.jsx` | Thin orchestrator |
| Operator/auth/profile/settings pages | Migrated consumers |
| **Delete:** `PrimaryButton`, `SecondaryButton`, `DangerButton`, `TextInput`, `InputLabel`, `InputError`, `Checkbox.jsx` (root), `GuestLayout.jsx`, `Dashboard.jsx` |

---

### Task 1: Auth shell CSS

**Files:**
- Modify: `resources/css/components.css` (append in `@layer components`)

- [ ] **Step 1: Add auth layout classes**

Append before the closing `}` of `@layer components`:

```css
  .auth-shell {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    background: var(--color-paper);
  }

  .auth-shell-card {
    width: 100%;
    max-width: 420px;
    background: var(--color-paper);
    border: 1px solid var(--color-line);
    border-radius: var(--radius-card);
    padding: 32px 28px;
    box-shadow: var(--shadow-1);
  }

  .auth-shell-brand {
    margin-bottom: 28px;
  }
```

- [ ] **Step 2: Verify build**

Run: `npm run build`  
Expected: success (no CSS errors)

---

### Task 2: Core `ui` primitives (batch 1)

**Files:**
- Create: `resources/js/Components/ui/Card.jsx`
- Create: `resources/js/Components/ui/DataTable.jsx`
- Create: `resources/js/Components/ui/RowActions.jsx`
- Create: `resources/js/Components/ui/FormError.jsx`
- Create: `resources/js/Components/ui/Eyebrow.jsx`
- Create: `resources/js/Components/ui/Brand.jsx`

- [ ] **Step 1: Create `Card.jsx`**

```jsx
export default function Card({ title, pad = true, className = '', children, ...rest }) {
    return (
        <div className={`card${pad ? ' card-pad' : ''} ${className}`.trim()} {...rest}>
            {title ? <div className="card-title">{title}</div> : null}
            {children}
        </div>
    );
}
```

- [ ] **Step 2: Create `DataTable.jsx`**

```jsx
export default function DataTable({ className = '', children }) {
    return (
        <div
            className={className}
            style={{ border: '1px solid var(--color-line)', borderRadius: 6, overflow: 'hidden' }}
        >
            <table className="ptable">{children}</table>
        </div>
    );
}
```

- [ ] **Step 3: Create `RowActions.jsx`**

```jsx
export default function RowActions({ children, className = '' }) {
    return <div className={`row-actions ${className}`.trim()}>{children}</div>;
}
```

- [ ] **Step 4: Create `FormError.jsx`**

```jsx
export default function FormError({ message, className = '' }) {
    if (!message) return null;
    return (
        <p className={`text-xs text-sev-critical mt-1 ${className}`.trim()} role="alert">
            {message}
        </p>
    );
}
```

- [ ] **Step 5: Create `Eyebrow.jsx`**

```jsx
export default function Eyebrow({ children, className = '' }) {
    return <span className={`eyebrow ${className}`.trim()}>{children}</span>;
}
```

- [ ] **Step 6: Create `Brand.jsx`**

```jsx
import { Link } from '@inertiajs/react';

export default function Brand({ href = '/', product = 'Prospect Scanner', className = '' }) {
    const inner = (
        <>
            <span className="brand-mark" aria-hidden="true" />
            <span className="brand-name">nthdesigns</span>
            {product ? (
                <>
                    <span className="brand-sep">/</span>
                    <span className="brand-product">{product}</span>
                </>
            ) : null}
        </>
    );

    const cls = `app-brand ${className}`.trim();

    return href ? (
        <Link href={href} className={cls} style={{ textDecoration: 'none', color: 'inherit' }}>
            {inner}
        </Link>
    ) : (
        <div className={cls}>{inner}</div>
    );
}
```

- [ ] **Step 7: Run build**

Run: `npm run build`  
Expected: PASS (files not exported yet — no imports broken)

---

### Task 3: Core `ui` primitives (batch 2)

**Files:**
- Create: `resources/js/Components/ui/Input.jsx`
- Create: `resources/js/Components/ui/Select.jsx`
- Create: `resources/js/Components/ui/LinkButton.jsx`
- Create: `resources/js/Components/ui/StatsStrip.jsx`
- Create: `resources/js/Components/ui/StatTile.jsx`
- Create: `resources/js/Components/ui/FilterBar.jsx`

- [ ] **Step 1: Create `Input.jsx` (forwardRef + isFocused)**

```jsx
import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function Input(
    { className = '', isFocused = false, type = 'text', ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) localRef.current?.focus();
    }, [isFocused]);

    return (
        <input
            ref={localRef}
            type={type}
            className={`input ${className}`.trim()}
            {...props}
        />
    );
});
```

- [ ] **Step 2: Create `Select.jsx`**

```jsx
export default function Select({ className = '', children, ...props }) {
    return (
        <select className={`select ${className}`.trim()} {...props}>
            {children}
        </select>
    );
}
```

- [ ] **Step 3: Create `LinkButton.jsx`**

```jsx
import { Link } from '@inertiajs/react';

export default function LinkButton({
    kind = 'secondary',
    size = 'md',
    className = '',
    children,
    ...rest
}) {
    const sizeClass = size === 'sm' ? ' btn-sm' : size === 'xs' ? ' btn-xs' : size === 'lg' ? ' btn-lg' : '';
    return (
        <Link className={`btn btn-${kind}${sizeClass} ${className}`.trim()} {...rest}>
            {children}
        </Link>
    );
}
```

- [ ] **Step 4: Create `StatsStrip.jsx` and `StatTile.jsx`**

`StatsStrip.jsx`:

```jsx
export default function StatsStrip({ children, className = '' }) {
    return <div className={`stats-strip ${className}`.trim()}>{children}</div>;
}
```

`StatTile.jsx`:

```jsx
export default function StatTile({ label, value, warm = false, className = '' }) {
    return (
        <div className={`stat-tile${warm ? ' warm' : ''} ${className}`.trim()}>
            <div className="stat-label">{label}</div>
            <div className="stat-value tabular">{value}</div>
        </div>
    );
}
```

- [ ] **Step 5: Create `FilterBar.jsx`**

```jsx
export default function FilterBar({ onSubmit, children, className = '' }) {
    return (
        <form onSubmit={onSubmit} className={`filter-bar ${className}`.trim()}>
            {children}
        </form>
    );
}
```

- [ ] **Step 6: Run build**

Run: `npm run build`  
Expected: PASS

---

### Task 4: Export primitives from `index.js`

**Files:**
- Modify: `resources/js/Components/ui/index.js`

- [ ] **Step 1: Add exports**

```js
export { default as Brand } from './Brand';
export { default as Card } from './Card';
export { default as DataTable } from './DataTable';
export { default as Eyebrow } from './Eyebrow';
export { default as FilterBar } from './FilterBar';
export { default as FormError } from './FormError';
export { default as Input } from './Input';
export { default as LinkButton } from './LinkButton';
export { default as RowActions } from './RowActions';
export { default as Select } from './Select';
export { default as StatTile } from './StatTile';
export { default as StatsStrip } from './StatsStrip';
```

- [ ] **Step 2: Run build**

Run: `npm run build`  
Expected: PASS

---

### Task 5: `AuthLayout` + `AppShell` brand

**Files:**
- Create: `resources/js/Layouts/AuthLayout.jsx`
- Modify: `resources/js/Components/ui/AppShell.jsx`

- [ ] **Step 1: Create `AuthLayout.jsx`**

```jsx
import { Brand } from '@/Components/ui';
import { Head } from '@inertiajs/react';

export default function AuthLayout({ title, children }) {
    return (
        <>
            {title ? <Head title={title} /> : null}
            <div className="auth-shell">
                <div className="auth-shell-card">
                    <div className="auth-shell-brand">
                        <Brand href="/" />
                    </div>
                    {children}
                </div>
            </div>
        </>
    );
}
```

- [ ] **Step 2: Replace inline brand in `AppShell.jsx`**

Replace the `Link` + spans block (lines ~29–34) with:

```jsx
<Brand href="/search" />
```

Add import: `import Brand from './Brand';`

- [ ] **Step 3: Run build**

Run: `npm run build`  
Expected: PASS

---

### Task 6: Migrate operator pages

**Files:**
- Modify: `resources/js/Pages/Search/Index.jsx`
- Modify: `resources/js/Pages/Search/Show.jsx`
- Modify: `resources/js/Pages/Saved/Index.jsx`
- Modify: `resources/js/Pages/Reports/Index.jsx`
- Modify: `resources/js/Pages/Outreach/Index.jsx`
- Modify: `resources/js/Pages/Prospect/Show.jsx`

- [ ] **Step 1: Update imports on each page**

Add to each file's `@/Components/ui` import:

`Card`, `DataTable`, `FilterBar`, `StatsStrip`, `StatTile`, `RowActions`, `Input`, `Select`, `FormError` (as needed per page).

- [ ] **Step 2: `Search/Index.jsx` — replace card + inputs**

Before:

```jsx
<div className="card card-pad">
    <div className="card-title" style={{ marginBottom: 18 }}>Parameters</div>
    ...
    <input className="input" ... />
```

After:

```jsx
<Card title="Parameters">
    ...
    <Field label="Niche" hint="local trade or profession">
        <Input value={data.niche} onChange={...} required />
        <FormError message={errors.niche} />
    </Field>
```

Remove redundant inline `style={{ marginBottom: 18 }}` on titles (handled by `Card`).

- [ ] **Step 3: `Saved/Index.jsx`, `Reports/Index.jsx`, `Search/Show.jsx` — tables**

Before:

```jsx
<div style={{ border: '1px solid var(--color-line)', borderRadius: 6, overflow: 'hidden' }}>
    <table className="ptable">...</table>
</div>
```

After:

```jsx
<DataTable>...</DataTable>
```

- [ ] **Step 4: `Reports/Index.jsx` — stats strip**

Before:

```jsx
<div className="stats-strip">
    <div className="stat-tile">...</div>
</div>
```

After:

```jsx
<StatsStrip>
    <StatTile label="Reports generated" value={stats.total_reports} />
    ...
</StatsStrip>
```

- [ ] **Step 5: `Saved/Index.jsx` + `Reports/Index.jsx` — filter bar**

Wrap filter `<form className="filter-bar">` with `<FilterBar onSubmit={...}>` and remove duplicate `className`.

- [ ] **Step 6: Table action cells — `RowActions`**

Replace `<div className="row-actions">` with `<RowActions>`.

- [ ] **Step 7: Run build + smoke**

Run: `npm run build`  
Manual: load `/search`, `/saved`, `/reports`, `/outreach`, `/prospects/{id}` while logged in.

---

### Task 7: `OutreachEmailCard.jsx`

**Files:**
- Modify: `resources/js/Components/OutreachEmailCard.jsx`

- [ ] **Step 1: Wrap body in `Card`**

Import `Card` from `@/Components/ui`. Replace outer `div` with `className="card card-pad"` → `<Card>` (preserve inner structure).

- [ ] **Step 2: Run build**

Run: `npm run build`  
Expected: PASS

---

### Task 8: `Report/Public.jsx`

**Files:**
- Modify: `resources/js/Pages/Report/Public.jsx`

- [ ] **Step 1: Replace raw buttons**

Import `Button`, `LinkButton` from `@/Components/ui`.

Replace:

```jsx
<a href={...} className="btn btn-accent btn-lg">
```

With:

```jsx
<LinkButton href={...} kind="accent" size="lg">
```

Replace `<button className="btn ...">` with `<Button kind="..." size="...">`.

- [ ] **Step 2: Replace inline table wrapper**

Use `DataTable` for tabular sections where `ptable` or bordered table pattern appears.

- [ ] **Step 3: Audit copy for combined numeric score**

Grep `Public.jsx` for `combined` / numeric score strings shown to prospects. Remove or replace with grade-only language per `scoreBand.js`.

- [ ] **Step 4: Run build + smoke**

Run: `npm run build`  
Manual: open `/r/{token}` for a seeded report.

---

### Task 9: Split `HomePage.jsx` into section files

**Files:**
- Create: `resources/js/Pages/Welcome/components/Arrow.jsx`
- Create: `resources/js/Pages/Welcome/components/Nav.jsx`
- Create: `resources/js/Pages/Welcome/components/AuditWidget.jsx`
- Create: `resources/js/Pages/Welcome/components/HeroEditorial.jsx`
- Create: `resources/js/Pages/Welcome/components/HowItWorks.jsx`
- Create: `resources/js/Pages/Welcome/components/SampleReportExcerpt.jsx`
- Create: `resources/js/Pages/Welcome/components/WhyNow.jsx`
- Create: `resources/js/Pages/Welcome/components/Evidence.jsx`
- Create: `resources/js/Pages/Welcome/components/Compare.jsx`
- Create: `resources/js/Pages/Welcome/components/Testimonials.jsx`
- Create: `resources/js/Pages/Welcome/components/Pricing.jsx`
- Create: `resources/js/Pages/Welcome/components/SelfCheck.jsx`
- Create: `resources/js/Pages/Welcome/components/FAQ.jsx`
- Create: `resources/js/Pages/Welcome/components/FooterCTA.jsx`
- Create: `resources/js/Pages/Welcome/components/SiteFooter.jsx`
- Modify: `resources/js/Pages/Welcome/HomePage.jsx`

- [ ] **Step 1: Move each function block verbatim**

For each `function X` in `HomePage.jsx` (lines 5–963), cut into its own file under `Welcome/components/`. Export named `X`. Move helper-only children with their parent (e.g. `CompleteAuditCard`, `MiniScore` → `AuditWidget.jsx`; `SampleViol` → `SampleReportExcerpt.jsx`; `EvCard` → `Evidence.jsx`).

`Arrow.jsx`:

```jsx
export default function Arrow({ size = 11 }) {
    return <span className="arrow" style={{ fontSize: size + 1 }}>→</span>;
}
```

- [ ] **Step 2: Replace buttons in section files**

In each moved file, replace:

```jsx
<button className="btn btn-primary ...">
<Link className="btn btn-ghost ...">
```

With `Button` / `LinkButton` from `@/Components/ui`. Keep `Arrow` as child where used.

Replace `nav-brand` mark spans with `<Brand href="/" />` only in `Nav.jsx` if structure allows (marketing nav may keep `site-nav` classes — use `Brand` with `className="nav-brand"` override if needed).

- [ ] **Step 3: Thin `HomePage.jsx`**

```jsx
import { useCallback } from 'react';
import Nav from './components/Nav';
import HeroEditorial from './components/HeroEditorial';
// ...other imports

export default function HomePage({ canLogin, canRegister }) {
    const scrollTo = useCallback((id) => {
        const el = document.getElementById(id);
        if (el) {
            const top = el.getBoundingClientRect().top + window.scrollY - 88;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    }, []);

    return (
        <div className="page-frame">
            <Nav scrollTo={scrollTo} canLogin={canLogin} canRegister={canRegister} />
            <HeroEditorial />
            <HowItWorks />
            {/* ...remaining sections */}
        </div>
    );
}
```

Target: `HomePage.jsx` ≤ 120 lines.

- [ ] **Step 4: Run build + smoke**

Run: `npm run build`  
Manual: load `/` logged out; click nav anchors; verify CTA buttons.

---

### Task 10: `Auth/Login.jsx` — shared primitives

**Files:**
- Modify: `resources/js/Pages/Auth/Login.jsx`

- [ ] **Step 1: Remove local `BrandMark`, `Eyebrow`, `ScoreBadge` functions**

- [ ] **Step 2: Import from ui**

```jsx
import { Brand, Eyebrow, ScoreBadge } from '@/Components/ui';
```

- [ ] **Step 3: Replace usages**

Topbar brand link → `<Brand href="/" />` (adjust wrapper classes on parent `a` if needed — may use `Brand` inside existing flex container).

`Eyebrow` → `<Eyebrow>Operator sign-in</Eyebrow>` (add `.eyebrow` styles to `components.css` if missing for non-marketing context, or keep Login-specific wrapper class).

Preview panel score chips → `<ScoreBadge value={87} withBar={false} />` etc. (use real band values, not inline styles).

- [ ] **Step 4: Run build + smoke**

Manual: `/login` — layout unchanged, scores render with correct bands.

---

### Task 11: Breeze auth pages → `AuthLayout`

**Files:**
- Modify: `resources/js/Pages/Auth/Register.jsx`
- Modify: `resources/js/Pages/Auth/ForgotPassword.jsx`
- Modify: `resources/js/Pages/Auth/ResetPassword.jsx`
- Modify: `resources/js/Pages/Auth/ConfirmPassword.jsx`
- Modify: `resources/js/Pages/Auth/VerifyEmail.jsx`

- [ ] **Step 1: Swap layout + form controls (Register example)**

Replace imports:

```jsx
import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Field, FormError, Input } from '@/Components/ui';
```

Replace structure:

```jsx
<AuthLayout title="Register">
    <h1 className="font-serif text-3xl font-normal tracking-tight text-ink mb-6">Create account</h1>
    <form onSubmit={submit} className="stack">
        <Field label="Name">
            <Input
                id="name"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                isFocused
                required
            />
            <FormError message={errors.name} />
        </Field>
        ...
        <Button kind="primary" type="submit" disabled={processing}>
            Register
        </Button>
    </form>
</AuthLayout>
```

Remove all `InputLabel`, `TextInput`, `InputError`, `PrimaryButton`, `GuestLayout`, gray Tailwind classes.

- [ ] **Step 2: Repeat for Forgot, Reset, Confirm, Verify**

Same pattern; preserve route-specific copy and `useForm` fields.

- [ ] **Step 3: Run auth feature tests**

Run: `php artisan test tests/Feature/Auth`  
Expected: PASS (update assertions only if tests check for `rounded-md` / gray classes)

---

### Task 12: `Settings/Index.jsx`

**Files:**
- Modify: `resources/js/Pages/Settings/Index.jsx`

- [ ] **Step 1: Rebuild with operator shell**

```jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Card, Field, FormError, Input, PageHeader, Select } from '@/Components/ui';
```

Structure:

```jsx
<AuthenticatedLayout>
    <Head title="Settings" />
    <main className="page" style={{ maxWidth: 720 }}>
        <PageHeader eyebrow="Settings" title="Workspace defaults." sub="..." />
        {flash?.success && (
            <p className="micro" style={{ color: 'var(--color-positive)' }}>{flash.success}</p>
        )}
        <Card title="API & storage health">
            <ul className="stack">...</ul>
        </Card>
        <Card title="Defaults">
            <form onSubmit={submit}>...</form>
        </Card>
    </main>
</AuthenticatedLayout>
```

Remove `max-w-3xl`, `bg-white`, `text-gray-*`, `border-gray-*`.

Health rows: use `micro` + colour via inline `var(--color-sev-critical)` only when status not ok.

- [ ] **Step 2: Run tests**

Run: `php artisan test tests/Feature/SettingsTest.php`  
Expected: PASS

---

### Task 13: Profile pages

**Files:**
- Modify: `resources/js/Pages/Profile/Edit.jsx`
- Modify: `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.jsx`
- Modify: `resources/js/Pages/Profile/Partials/UpdatePasswordForm.jsx`
- Modify: `resources/js/Pages/Profile/Partials/DeleteUserForm.jsx`

- [ ] **Step 1: `Profile/Edit.jsx`**

Remove Breeze `header` prop and gray wrapper divs:

```jsx
<AuthenticatedLayout>
    <Head title="Profile" />
    <main className="page" style={{ maxWidth: 720 }}>
        <PageHeader eyebrow="Account" title="Profile & security." />
        <div className="stack" style={{ gap: 24 }}>
            <Card><UpdateProfileInformationForm /></Card>
            <Card><UpdatePasswordForm /></Card>
            <Card><DeleteUserForm /></Card>
        </div>
    </main>
</AuthenticatedLayout>
```

- [ ] **Step 2: Migrate each partial**

Replace `InputLabel` + `TextInput` + `InputError` with `Field` + `Input` + `FormError`.  
Replace `PrimaryButton` → `Button kind="primary"`.  
Replace `SecondaryButton` → `Button kind="secondary"`.  
Replace `DangerButton` → `Button kind="destructive"`.  
Remove gray typography classes; use `card-title` or serif heading inside card for section titles.

Keep `Modal` from `@/Components/Modal` for delete confirmation (out of scope to rebuild).

- [ ] **Step 3: Run profile tests**

Run: `php artisan test tests/Feature/ProfileTest.php tests/Feature/Auth/PasswordUpdateTest.php`  
Expected: PASS

---

### Task 14: Remove dead files + dashboard page

**Files:**
- Delete: `resources/js/Pages/Dashboard.jsx`
- Delete: `resources/js/Components/PrimaryButton.jsx`
- Delete: `resources/js/Components/SecondaryButton.jsx`
- Delete: `resources/js/Components/DangerButton.jsx`
- Delete: `resources/js/Components/TextInput.jsx`
- Delete: `resources/js/Components/InputLabel.jsx`
- Delete: `resources/js/Components/InputError.jsx`
- Delete: `resources/js/Components/Checkbox.jsx` (only if zero imports remain)
- Delete: `resources/js/Layouts/GuestLayout.jsx`
- Verify: `routes/web.php` already redirects `/dashboard` → `search.index` (no change needed)

- [ ] **Step 1: Grep for stale imports**

Run:

```bash
rg "PrimaryButton|SecondaryButton|DangerButton|TextInput|InputLabel|InputError|GuestLayout|Pages/Dashboard" resources/js
```

Expected: no matches

- [ ] **Step 2: Delete files listed above**

- [ ] **Step 3: If `ApplicationLogo` unused, delete**

Run: `rg "ApplicationLogo" resources/js` — delete `ApplicationLogo.jsx` only if no references.

- [ ] **Step 4: Run build**

Run: `npm run build`  
Expected: PASS

---

### Task 15: Design-system compliance audit

**Files:**
- Modify: any files flagged by grep (pages only)

- [ ] **Step 1: Grep gates**

```bash
rg 'className="btn |from .@/Components/(Primary|Secondary|Danger)Button|GuestLayout|text-gray-|bg-white|bg-gray-' resources/js/Pages
rg 'function ScoreBadge' resources/js/Pages
```

Expected: zero matches in `Pages/` (except `Welcome/components` may not define ScoreBadge).

- [ ] **Step 2: Fix violations**

Any remaining raw `btn` in pages → `Button` or `LinkButton`.  
Any `gray-*` Tailwind in pages/layouts → design tokens / `components.css` classes.

- [ ] **Step 3: Add `.eyebrow` to `components.css` if Login/PageHeader need it**

Copy eyebrow rules from `Design System.html` (lines 84–102) into `@layer components` if not already present for non-marketing pages.

- [ ] **Step 4: Full verification**

Run: `php artisan test`  
Run: `npm run build`  
Manual route smoke: `/`, `/login`, `/register`, `/search`, `/saved`, `/reports`, `/outreach`, `/settings`, `/profile`, `/r/{token}`

- [ ] **Step 5: Update spec status**

In `docs/superpowers/specs/2026-05-27-ui-component-refactor-design.md`, set **Status:** Approved — implemented.

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| New ui primitives | Tasks 2–4 |
| AuthLayout | Task 5, 11 |
| Operator migration | Task 6 |
| OutreachEmailCard | Task 7 |
| Public report | Task 8 |
| Homepage split | Task 9 |
| Login primitives | Task 10 |
| Settings + Profile | Tasks 12–13 |
| Dashboard redirect + delete stub | Task 14 |
| Delete Breeze components | Task 14 |
| Compliance grep | Task 15 |
| phpunit + build | Tasks 6–15 |

## PR checklist (for description)

- [ ] All pages use `@/Components/ui`
- [ ] Breeze components deleted
- [ ] `HomePage.jsx` ≤ 120 lines
- [ ] `php artisan test` green
- [ ] `npm run build` green
- [ ] Manual smoke routes visited
- [ ] Intentional visual deltas vs `Design System.html` noted
