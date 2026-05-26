# Handoff — Prospect Scanner Operator UI + Public Report

**Designer:** Claude Design (via Alex N, nthdesigns)
**Date:** 26 May 2026
**Target stack:** Laravel 13 · Inertia · React · Tailwind v4
**Companion engineering specs:** `docs/superpowers/specs/2026-05-26-operator-ui-design.md` and `docs/superpowers/specs/2026-05-26-operator-ui.md`

---

## What this is

A high-fidelity design system + interactive prototype for the entire Prospect Scanner UI:

- **Internal operator app** — seven authenticated screens (`/search`, `/search/results`, `/prospect/{id}`, `/outreach`, `/saved`, `/reports`, `/settings`)
- **Public prospect report** — one standalone page (`/r/{token}`), no auth, no app chrome, both desktop and mobile

The bundled HTML files are **design references, not production code**. The job is to recreate them as Inertia + React + Tailwind v4 pages inside the existing codebase, reusing the patterns from `resources/js/Pages/Search/Show.jsx`, `resources/js/Pages/Prospect/Show.jsx`, and `resources/js/Pages/Report/Public.jsx`. The engineering spec already covers data flow, route map, controllers, queries and tests — this handoff covers the visual system and screen-level UX decisions.

## Fidelity

**High-fidelity.** Final colours (oklch), typography (Newsreader + IBM Plex Sans + IBM Plex Mono), spacing, badge shapes, hover states, polling animations are all settled. Implement pixel-close in Tailwind v4.

## Files in this bundle

| File | What it is |
|---|---|
| `README.md` | This document — read first. |
| `tokens.css` | Clean design tokens, ready to lift into Tailwind v4's `@theme` block. |
| `Design System.html` | Foundations — colour, type, components, badges, score scale, severity, annotation pin. Open in a browser. |
| `prototype.html` | The full navigable prototype. Open in a browser and visit `#/search`, `#/search/results?id=s-001`, `#/prospect?id=p-101`, `#/outreach`, `#/saved`, `#/reports`, `#/settings`, `#/public`. The "Show annotations" chip in the bottom-left toggles ~10 numbered pins explaining specific decisions. |
| `Canvas Overview.html` | Side-by-side tour of all 8 screens with launch links. |

---

## Design direction (the part that's not in the engineering spec)

### Voice
Senior UK consultancy. Calm, evidence-led, document-like. Not SaaS, not growth-hacky, no exclamation marks. The public report reads like a written audit; the internal app reads like a well-laid data terminal.

### Score semantics — non-negotiable
**Higher score = weaker business = warmer lead.** Never inverted. The colour scale on internal tables goes monochrome (stone-100) → mid-tint (stone-tinted) → ochre at 71+. Operators learn this in 30 seconds; do not show "73 ✓" next to a green chip because it will train the wrong mental model on day one.

### Public report — different surface, same brand
The public report uses the same tokens but reads as long-form editorial: bigger Newsreader headlines, generous whitespace, more vertical rhythm, no internal jargon ("combined score" never appears). It grades A–F (see thresholds in `tokens.css`) — that's the only score language the prospect sees.

### Severity is shape-coded, not just colour-coded
`Critical` chip pip is a square, `Serious` is a diamond (45° square), `Moderate` is a circle. This survives colour-blind users and monochrome printing. Preserve when porting.

### Annotation overlay (preview-only)
The annotation pins inside the prototype are a designer tool, **not part of the product**. They explain why a pattern looks the way it does. Don't port them; just read them.

---

## Tailwind v4 setup

### 1. Drop `tokens.css` into the project
Recommended location: `resources/css/tokens.css`. Then in your main stylesheet:

```css
@import "tailwindcss";
@import "./tokens.css";

/* Expose tokens as Tailwind utilities */
@theme {
  --color-paper: oklch(0.985 0.006 80);
  --color-ink: oklch(0.155 0.008 52);
  --color-accent: oklch(0.705 0.140 65);
  --color-accent-soft: oklch(0.945 0.045 75);
  --color-accent-deep: oklch(0.500 0.130 55);
  --color-accent-ink: oklch(0.290 0.080 50);
  --color-sev-critical: oklch(0.515 0.180 28);
  --color-sev-serious: oklch(0.660 0.155 52);
  --color-sev-moderate: oklch(0.610 0.085 85);
  --color-positive: oklch(0.580 0.110 145);

  /* full stone-100..800 + softs in tokens.css */

  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif;
  --font-serif: "Newsreader", Georgia, serif;
  --font-mono: "IBM Plex Mono", ui-monospace, Menlo, monospace;
}
```

That gives you `bg-paper`, `text-ink`, `border-line`, `font-serif`, etc. directly.

### 2. Load the fonts
Both families on Google Fonts. In the Inertia root template:

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
```

Don't ship Inter or Roboto as a fallback — keep `ui-sans-serif, system-ui` so the FOUT degrades to system.

### 3. Tabular figures
Every number that compares to another number (scores, view counts, severity counts, table cells with integers) needs `font-variant-numeric: tabular-nums`. Add a Tailwind utility class `.tabular` or rely on `tabular-nums`.

---

## Component library to build

Group these into `resources/js/Components/ui/`. Most are 30–80 lines of React.

| Component | Props | Where to use |
|---|---|---|
| `<Button>` | `kind: primary \| accent \| secondary \| ghost \| destructive`, `size: xs \| sm \| md \| lg`, `icon`, `iconRight`, `disabled` | Everywhere. **Note:** `accent` is reserved for the public-report CTA only — `primary` (ink on paper) is the internal app's confident action. |
| `<ScoreBadge>` | `value: number \| null`, `withBar?: bool` | Tables, prospect cards, queue chips. Renders the bar + numeric. Bands at 41 and 71. |
| `<AnglePill>` | `angle: "gbp" \| "a11y" \| "both"` | Tables, prospect detail, outreach. Glyph + label. |
| `<SevChip>` | `level: "critical" \| "serious" \| "moderate"`, `count?`, `label?` | Public report and prospect detail. Shape-coded pip. |
| `<Status>` | `kind: "ready" \| "pending" \| "failed" \| "warm" \| "default"` | Anywhere a row needs a one-line status. `pending` LED pulses. |
| `<Badge>` | `dot?: bool` | Inline weakness flags inside expandable rows ("Missing &lt;h1&gt;", "Contrast 2.1 : 1"). |
| `<ScoreCard>` | `label`, `value`, `highlight?`, `delta?` | Prospect detail trio. `highlight` swaps to ochre gradient. |
| `<Field>` | `label`, `hint?`, children | Wraps every form input. Hint sits on the right of the label. |
| `<Segmented>` | `value`, `onChange`, `options: [{value, label}]` | Filter bars, pitch-angle override. |
| `<PageHeader>` | `eyebrow`, `title`, `sub`, `actions`, `back?`, `onBack?` | Every internal screen's top section. |
| `<EmptyState>` | `icon`, `title`, `sub`, `action` | "No prospects match these filters." |
| `<Toast>` | `children`, `onClose` | Success on add-to-outreach, copy-link, etc. Auto-dismiss at 3s. |
| `<AppShell>` | `route`, `children`, `selectionCount` | Wraps every authenticated page. Top bar with brand + nav + APIs-online chip + user chip. |

The prototype's `<style>` block has the exact CSS for each — copy the class-level styles into Tailwind utility combinations or a `.btn-*` etc. layer.

---

## Screen-by-screen design notes

References to the engineering spec are by section.

### A — `/search` (Search create)
- **Layout:** Two-column. Form on left (560px), recent-searches sidebar right (320px).
- **Scan type** is a 3-button radio group, not a `<select>` — operators choose 5–10× per day. Each button shows the niche-time estimate.
- **Info panel** below scan type explains expected duration. Copy varies per scan type.
- **Recent searches sidebar** lists last 4 with niche · city · timestamp · status pill. Clicking opens that search's results page (read-only path).

### B — `/search/results?id=…` (Search results)
- **The operator's daily workspace.** Optimised for scanning 20–200 rows.
- **Live polling state:** `?live=1` flag in the prototype shows a `<ProgressBar>` above the filter bar with a spinner + "scanned 6 of 23 · ~12 min remaining" + percentage. Skeleton rows fill the bottom of the table while audits land. In production: poll `outreachEmails` / `prospects` via Inertia `router.reload({ only: ['prospects'] })` every 4s while `status === 'running'`.
- **Warm-lead row** is the linear-gradient wash from `accent-soft` to transparent at 50%. Overrides hover. Only applies when `report.viewed_at` is set + `outreach.sent_at` set + no `response_received` (strict warm definition).
- **Audit-failed row** dims via `color: var(--color-stone-500)` on all cells, URL line goes red, action becomes "Retry" (not "Open").
- **Expandable row** holds two columns of weakness flags (GBP left, A11y right) inside a `.expanded-row` with a 3px left ochre border.
- **Row actions** are icon-only buttons aligned right: expand · maps · preview report · open. Disable preview when audit failed/pending.
- **Multi-select** via checkbox column. Toolbar appears when ≥1 selected with `Add N to outreach` (POSTs the selection to `/outreach/selections`).
- **Perf column (Phase 7 — page-speed signal):** sits between A11y and Angle. Renders `<ScoreBadge withBar={false} weakPip={perf < 30}>`. **Convention break worth noting:** this column is Lighthouse-native (higher = faster site, opposite direction to every other score column), so an 87 here means a fast site — not a warm lead. The terracotta `weakPip` (square, matches Critical-severity coding) appears below 30 to flag "this is the weak end of THIS column" and disambiguate. Pending/failed audits show `—`.
- See spec: §B "Search results", §Search/Show.jsx changes (selection checkboxes + In-outreach badge).

### C — `/prospect/{id}` (Prospect detail)
- **Layout:** 1fr / 320px. Main column = score cards + flag panel + audit timeline. Sidebar = public report card + outreach card + profile meta.
- **Score cards** use the serif (Newsreader) for the big number — 48px, tabular figures, `letter-spacing: -0.02em`. Combined gets `.highlight` which adds the ochre gradient when `combined >= 71`.
- **Flag panel** splits GBP weaknesses (neutral pips) from accessibility weaknesses (severity-coloured pips). A11y flags show their severity label on the right of each row.
- **Public report card** has: token line, copy button (clipboard + 2s "Copied" state), preview button, and a 14-bar sparkline showing the last 14 days of view distribution. Bar turns ochre on days where views landed in the warm window. Show "Not yet opened" when `view_count === 0`.
- **Outreach card** shows latest email subject + sent status, or "No email drafted" with an Add-to-outreach CTA when empty.

### D — `/outreach` (Outreach workspace)
- **Layout:** 300px / 1fr. Queue on left, controls + email cards on right.
- **Queue chip** holds: business name, ScoreBadge (no bar — saves space), AnglePill, status (Drafted / Sent / No report). Remove (×) on hover. Active chip has a black border.
- **Controls** are a single row: `pitch_angle` (Segmented: Auto / GBP / A11y / Both), `agency_name` (text), `cpc_benchmark` (input-with-prefix `£`), and the Generate button. Generate disables while running, shows a spinner during, and `eligible.length` in the label otherwise.
- **Skipped warning** (when prospects in queue have no report) is a moderate-severity banner: mustard background, lock icon, "{n} prospect(s) will be skipped — outreach requires an embedded link" + a "Generate reports" link.
- **Email card** has: header with To: + email + Score + Angle + actions (Preview report · Copy · Mark sent), then editable Subject input (set in Newsreader 14px medium) and Body textarea (Plex Sans 13px / 1.65 line-height), then a footer mono-line showing the embedded report URL and sentAt timestamp when applicable. When `isSent` the whole card drops to 70% opacity and inputs become readOnly.
- **"+ slow site" footnote tag (Phase 7):** when `prospect.perf < 30`, render `<span class="slow-site-tag">+ slow site</span>` directly below the pitch-angle pill (vertical stack inside the header). Small, monospace, terracotta square pip, paper-2 background. This is a signal that the email body contains a performance line — keep it muted; it's a footnote, not a feature. The generator service should weave a single factual sentence about the perf score into the body (see the existing demo emails for p-101 and p-109 as tone references).
- See spec: §D "Outreach workspace", §`generate` controller. Job payload: `{ agency_name, pitch_angle, cpc_benchmark }`. Skip per spec when no report.

### E — `/saved` (Saved prospects)
- **Warm leads panel** at top (collapsible on mobile, hidden when `?warm=1` is the filter). Three preview cards of the most recently-viewed warm prospects. CTA: "Filter to warm" sets `warm=true` in the URL.
- **Filter bar** holds the full spec set: from/to (date inputs), niche (select), city (text), scan_type (select), angle (Segmented), min_score (range with live value), warm (checkbox). Reset button at the right.
- **Table columns:** Business · Niche/City · Combined · GBP · A11y · Angle · Outreach history · Actions (copy report URL, add to outreach, open).
- **Outreach history column** shows "Sent 23 May" + (if warm) "Viewed 2h ago" in mono. Empty dash when no outreach.

### F — `/reports` (Reports dashboard)
- **Stats strip** above the filter bar: Reports generated · Total views · Warm (7d) · Avg views per report. The Warm tile gets the ochre gradient.
- **Filter bar:** Segmented `[All / Viewed / Unviewed / Warm·7d]`, niche select.
- **Table columns:** Business · Public URL · Created · Views · Last viewed · Viewer · Actions. Token shown as `<code>/r/{token}</code>` in Plex Mono, plus the Warm status pill inline when applicable.
- **Views cell** uses `tabular-nums`, dims when `0`, shows a small ochre `● new` indicator when within the 7-day warm window.
- **Copy URL** action shows a toast with "/r/{token} copied" for 1.8s.
- 7-day warm rule per spec: `viewed_at >= now()->subDays(7)`.

### G — `/settings`
- **Note:** This screen is **out of scope per the engineering spec**. Designed in case it lands later. If shipping the spec's slice, skip this screen — `agency_name` + `cpc_benchmark` live on the outreach form (per spec §Settings page) and don't need a separate route.
- If you do build it: API health rows with online LED + quota bar (Places, Anthropic, Lighthouse, R2), Defaults grid (country, agency name, booking URL, report expiry days), Account row with avatar + sign out.

### H — `/r/{token}` (Public prospect report)
- **No app chrome, no nav, no internal jargon.** This is shipped from a `Report/Public.jsx` Inertia page using a separate layout (no `<AppShell>`).
- **Constraint:** max-width 880px (desktop) / 390px (mobile). The prototype's viewport toggle is for preview only.
- **Sections in order:**
  1. **Header** — nthdesigns mark + italic wordmark, audit date right-aligned, eyebrow "Independent audit · WCAG 2.2 + Google Business Profile", business name as 56px Newsreader, URL + address line.
  2. **Grade hero** — 160px serif letter (A–F) coloured by severity (terracotta ≥85, amber ≥41, positive <41) on the left, editorial paragraph + severity chip row on the right.
  3. **Section 1 · Accessibility** — eyebrow + serif h2 + lede, then 5 violation cards. Each: 240px stylised browser-frame "screenshot" with a sev-coloured marker box + text label highlighting the issue area, then the issue title, plain-English impact paragraph, and a left-bordered ochre "Fix" block.
  4. **Section 2 · GBP** — same head treatment, then a 3-column comparison table (Signal · You · Top competitor). Competitor column has the ochre wash. Numbers are big serif.
  5. **Section 3 · Site performance** — three Lighthouse-style SVG dials (Performance / SEO / Best Practices). Colour band: <50 critical, <70 serious, ≥70 positive. **Phase 7 addition:** when `prospect.perf < 30`, a single serif editorial sentence renders below the dial row, separated by a hairline rule — "This site loads slowly on mobile. Google's research shows that pages taking over 3 seconds to load lose approximately half their visitors before the first interaction." Same Newsreader / `--color-stone-700` styling as the other editorial paragraphs on the page. Don't add an icon, don't change the dial colour — the editorial register does the work.
  6. **CTA section** — eyebrow ("Next step"), 48px serif headline, lede, single accent button "Book a free 30-minute review", mono booking URL + reply-time line.
  7. **Footer** — small brand mark + agency descriptor, token + expiry date.
- **A–F grade derivation:** see `tokens.css` for thresholds. Never expose the numeric combined score.
- **CTA target:** `settings.bookingUrl` (default `https://cal.nthdesigns.co.uk/30min`).
- **Mobile breakpoint:** all paddings drop from 80px to 24px, dial sizes from 120px to 80px, type scales by ~33%.

---

## Interactions & motion

- **Pulse animation** on `Status.pending` LED — 1.6s ease-in-out, opacity 1 → 0.35 → 1.
- **Shimmer** on `.skel` skeleton elements — 1.6s linear, background-position 100% → -100%.
- **Pin-in** for annotation pins — 0.3s ease, scale 0.4 → 1.
- **Toast in** — 0.25s ease, translateY(8px) → 0 with opacity.
- All button hovers — 120ms transition on background and border.
- Card hovers (cursor cards on the Canvas page) — `translateY(-2px)` over 150ms.
- No big page transitions. Hash navigation is instant on purpose — the prototype mimics Inertia's snap behaviour.

---

## Accessibility notes

- **Contrast:** ink-on-paper hits ≈14:1, accent-deep-on-paper ≈6.2:1, accent-soft-on-paper is decorative only (never text-bearing in isolation).
- **Severity chips** are shape-coded as well as colour-coded (square/diamond/circle pip). Preserve.
- **Focus rings** are 3px `accent-soft` halo plus `accent-deep` border on inputs. Override Tailwind's blue default.
- **Tabular figures** on every numeric column — prevents `8` and `38` from re-aligning when filtering.
- **Annotation pins** are decorative; never put screen-reader-essential content inside them.
- **The public report** is the priority — assume the prospect uses a screen reader or text-zooms to 200%. Don't lock heading levels into divs (`<h1>`/`<h2>` proper, in order).

---

## Sample data

The prototype carries 11 sample prospects across 4 searches:
- **Dental practices in Birmingham** (×5 incl. the hero "Birmingham Dental Practice" at combined 87 and "Singh Family Dentistry" as the audit-failed row)
- **Solicitors in Manchester** (×2 incl. "Briar & Wren Solicitors" at combined 93)
- **Independent hotels around the Lake District** (×2)
- **Chiropractors in Solihull** (×2)

Plus 7 sample reports, 3 sample outreach emails (a11y angle, both-signals angle, a11y-against-solicitor angle). All values are realistic for UK B2B — feel free to swap when seeding dev. Don't change the score → grade mapping without consulting the rules in `tokens.css`.

---

## Assets

The HTML files are self-contained — no external images, fonts loaded from Google Fonts CDN, icons drawn inline as SVG paths.

If you want the icon set as a standalone library, lift the `Icons` object from `prototype.html` (search for `const Icons = {`). It's ~25 paths, all 16×16, stroke-only.

---

## What was intentionally *not* designed

- Login / auth pages — outside scope.
- Onboarding / first-run flow — outside scope.
- Billing / subscription UI — spec marks billing as stubbed.
- Public-report 404 / expired-token page — needs a follow-up.
- Dark mode — discussed but deferred. Tokens are designed to be invertible later via a `[data-theme="dark"]` selector.
- Loading skeleton variants for Prospect detail / Outreach / Reports — only Search Results got the full polling treatment because it's where polling actually happens.

---

## Open questions for the implementer

1. **A–F grade is currently combined-score derived.** Spec didn't pin down the rule. Confirm the thresholds in `tokens.css` are acceptable, or replace with a rubric you'd rather defend.
2. **The public report assumes a "top competitor" exists.** When the prospect's niche+city returns no competitor (e.g. rural single-practice), do we hide Section 2 entirely or show a "you're the only one for 10km" panel?
3. **Outreach email tone** is editorial UK-consultancy — confirm with Alex before locking the prompts in `AnthropicService`.
4. **Settings screen** — keep designed or drop until the spec admits it.
5. **Perf column convention break.** The new Phase-7 Perf column reads Lighthouse-native (high = good site), the opposite direction to every other score column where high = warm lead. The terracotta `weakPip` below 30 disambiguates, but it's worth confirming this is acceptable rather than inverting the perf score on the table (showing `100 - lighthouse_perf` as a "perf weakness" score so the column reads consistently with the rest). The current direction matches the public report dials and the raw Lighthouse value the operator sees in PageSpeed Insights, which is the upside.
6. **Editorial sentence under Performance dial uses a hard 30 threshold.** The dial itself bands at 50/70 (Google's penalty thresholds). Confirm 30 is the right cut-off for the editorial sentence, or replace with a different threshold (e.g. < 40, or a more nuanced rule based on which Lighthouse field actually triggered the score).

Ping Alex with answers before implementation lands.
