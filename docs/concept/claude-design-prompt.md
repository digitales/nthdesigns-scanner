# Claude Design — Prompt Kit

Prompts for [Claude Design](https://claude.ai/design), derived from:

- `docs/concept/nthdesigns-prospect-scanner-plan.md`
- `docs/concept/gbp-prospect-tool-build-plan.md`

## How to use

1. **First session:** paste the [Master prompt](#master-prompt) below and attach both concept docs (or this file).
2. **Design system:** if you have nthdesigns.co.uk, use web capture or upload brand assets, then ask Claude to extract tokens before screen work.
3. **Iterate:** use the [per-screen prompts](#follow-up-prompts) in the same project so visual language stays consistent.
4. **Handoff:** export with notes for Inertia + React + Tailwind v4 implementation.

## Suggested session order

1. Master prompt → design system + shell navigation
2. [Public report (H)](#h-public-prospect-report) — conversion-critical
3. [Search results (B)](#b-search-results) + [Prospect detail (C)](#c-prospect-detail)
4. [Outreach (D)](#d-outreach)
5. [Saved (E)](#e-saved-prospects) + [Reports dashboard (F)](#f-reports-dashboard) + [Settings (G)](#g-settings)

## Design decisions (preset for Claude)

| Topic | Recommendation |
|--------|----------------|
| Brand | `scanner.nthdesigns.co.uk` internal; public reports feel like `report.nthdesigns.co.uk` (same UI, calmer subdomain vibe) |
| Public vs internal | Internal = dense ops UI; public = report/document layout |
| Score direction | **Higher number = weaker business = better lead** — label clearly for operators |
| CTA | Single primary: "Book a free 30-minute review" → Calendly |
| Sample niches | Dental, legal, or hospitality (UK cities) |

## What to attach

- Both files under `docs/concept/`
- Screenshots of current `resources/js/Pages/Search/Show.jsx` or `Report/Public.jsx` if evolving existing UI
- nthdesigns.co.uk capture for brand tokens

---

## Master prompt

Copy everything inside the block into Claude Design:

```xml
<context>
Design a complete UI system and interactive prototype for "nthdesigns Prospect Scanner" — an internal B2B web app for a UK design/accessibility agency (nthdesigns). Operators run niche+city searches, review scored local business prospects (Google Business Profile weakness + website WCAG/accessibility audits), generate AI outreach emails, and share unlisted public audit reports with prospects via link.

Stack for implementation (do not build code — design only): Laravel + Inertia + React + Tailwind v4. Desktop-first internal tool; one critical public-facing page optimized for mobile share links.
</context>

<product>
Dual-signal prospecting: higher scores = weaker/weaker opportunity (more sales potential). Dominant pitch angle per prospect: GBP visibility, accessibility/compliance, or both. Highest-converting flow: outreach email embeds a personalised public report link prospects can verify before replying.

Core user journeys:
1. Run search → watch live progress → sort/filter scored table → drill into prospect
2. Select prospects → generate/edit outreach emails (with report links) → mark sent
3. Review saved prospects, warm leads (report viewed, no reply), export CSV
4. Prospect opens public report (no login) → sees violations, competitor GBP comparison, Lighthouse-style scores, books call
</product>

<audiences>
- Primary: nthdesigns operator (agency owner or VA) — power user, scans many rows, needs clarity and speed
- Secondary: UK SME business owner opening a cold-email report link on phone — must feel credible, specific, non-salesy, not "SaaS template"
</audiences>

<screens>
Design all of the following as linked prototype screens with shared navigation (authenticated shell except public report):

INTERNAL (authenticated):
A. Search — create: niche, city, country (default GB), scan type (GBP only / Accessibility only / Combined)
B. Search results — polling state while discovering/auditing; table: Business name, Combined score (badge), GBP score, A11y score, Dominant angle, Report ready, Website link, row actions (Add to outreach, expand weaknesses, Maps, Preview report)
C. Prospect detail — score cards, flags, generate report / outreach, public link + view stats
D. Outreach — split layout: selected prospects left; controls right (pitch angle override, agency name, optional CPC benchmark, Generate all); output cards with subject, body, copy, edit, mark sent, preview report
E. Saved prospects — filters (date, niche, city, scan type, min score, angle, warm leads); CSV export; outreach history per row
F. Reports dashboard — all generated reports: views, last viewed, viewer IP, warm badge (viewed in 7 days), copy token URL
G. Settings — API health, defaults (country, agency name, Calendly URL), storage status

PUBLIC (no auth, no app chrome):
H. Public prospect report `/r/{token}` — nthdesigns branded only; business name + URL; overall grade A–F (not internal combined score); accessibility panel (severity counts, top 5 violations with screenshot, WCAG ref, plain-English impact, one-line fix); GBP side-by-side vs top local competitor; performance dials (Performance, SEO, Best Practices); single CTA "Book a free 30-minute review"; footer with expiry date. No login, no nav to internal app.
</screens>

<design_direction>
Tone: professional, calm, evidence-led — like a senior consultant's audit, not a growth-hacking SaaS. UK B2B sensibility.

Visual language:
- Clean data-dense tables with generous row height and scannable badges
- Score semantics must be obvious: use color scale where HIGH score = weak prospect = warm opportunity (e.g. amber/red for high weakness), not "green = good grade" confusion
- Accessibility content: severity hierarchy (critical / serious / moderate) with accessible color pairs
- Public report: more editorial/report-like than dashboard; whitespace, trust, screenshot evidence prominent
- Avoid: generic purple SaaS gradients, stock illustrations, "I hope this finds you well" energy, gamification, chatbot widgets

Typography: modern sans (Inter or similar), clear numeric tabular figures for scores
Components: cards, badges, data tables, expandable rows, toast/flash success, empty/loading/error states for failed audits
</design_direction>

<constraints>
- Do not show internal scoring formulas on the public report
- Public page: no navigation to internal app, no pricing tables
- Internal app: indigo accent acceptable as placeholder unless brand assets say otherwise
- Include realistic UK sample data (e.g. "Birmingham Dental Practice", "23 accessibility violations", competitor "Smile Studio Birmingham")
- Design desktop 1440px for internal; public report also show mobile 390px variant
- Include states: search running (skeleton/pulse), audit failed row, report not ready yet, warm lead highlighted
</constraints>

<deliverables>
1. Design system page: color, type, buttons, badges (score bands), tables, cards, severity chips
2. Interactive prototype linking screens A–H
3. Brief annotation layer: what each score badge means for operators
</deliverables>
```

---

## Brand block (optional)

Use after uploading logo or capturing nthdesigns.co.uk:

```xml
<brand>
Extract design tokens from nthdesigns.co.uk: typography, neutrals, accent, button radius, spacing. Apply to internal app and public report consistently. Public report should feel like an extension of the agency brand, not a separate product.
</brand>
```

---

## Follow-up prompts

Use these **after** the master prompt in the same Claude Design project.

### A. Search — create

> Design the new search form: niche (text), city (text), country (select, default United Kingdom), scan type (GBP only / Accessibility only / Combined). Primary CTA "Run search". Show validation and a note that combined scans take longer. Match the authenticated app shell.

### B. Search results

> Refine the Search create form and results table. Emphasise live status ("Discovering businesses" / "Auditing websites") with a clear progress indicator. Score badges: 0–40 low opportunity (cool), 41–70 medium (amber), 71–100 high opportunity (warm/red). Dominant angle as small pill: GBP | Accessibility | Both. Row expand shows human-readable weakness flags as tags.

### C. Prospect detail

> Design a prospect detail page: back link to search, business name and address, three score cards (Combined, GBP, Accessibility), actions to generate report and outreach, public report URL with copy button and view count when available. Show dominant angle and expandable weakness flags. Include empty states when audit skipped (no website).

### D. Outreach

> Design the Outreach workspace: left column stacked prospect chips with dominant angle + report status icon; right column generation controls (pitch angle override: auto / GBP / accessibility, agency name, optional CPC benchmark, Generate all) and stacked email cards. Each card: subject (editable), body textarea with visible report URL line, Copy, Mark sent toggle, Preview report. Feel efficient for batching 10–20 emails.

### E. Saved prospects

> Design Saved prospects: filter bar (date range, niche, city, scan type, min combined score, dominant angle, warm leads only). Data table with export CSV. Warm leads panel or filter highlight for reports viewed in last 7 days with no reply. Link to outreach history per row.

### F. Reports dashboard

> Design internal Reports dashboard: table of all generated reports with business name, copyable public URL, created date, view count, last viewed, viewer IP, warm badge if viewed in last 7 days. Filters: niche, viewed / unviewed. Quick link to prospect and outreach email.

### G. Settings

> Design Settings for operators: Google Places and Anthropic API status indicators, default country, default agency name for outreach, Calendly/booking URL for public report CTA, object storage health. Simple account section (billing stubbed). Calm admin aesthetic, not consumer settings.

### H. Public prospect report

> Design the public prospect report as a trust-building document for a dental practice owner on mobile. Lead with overall grade A–F and business name. Accessibility section first if violations are severe: screenshot cards with WCAG criterion, impact sentence, fix line. GBP comparison: two-column "You vs top competitor in Birmingham" with reviews, photos, description, hours, rating. Lighthouse-style circular score dials (Performance, SEO, Best Practices). One primary CTA button to book a call. Footer: "Prepared by nthdesigns · Expires [date]". No scores labelled "combined" or internal jargon. No app navigation.

### Warm leads (E + F combined emphasis)

> Add a Warm leads panel: prospects whose report was viewed in last 7 days but outreach not marked replied. Use a subtle flame or "warm" badge, sort by most recent view. Reports dashboard table with view count and copy-link action.

---

## MVP vs full spec

If time-boxing the first Claude Design session, prioritise:

| Priority | Screens | Why |
|----------|---------|-----|
| P0 | H (public report), B (search results) | Conversion and daily operator workflow |
| P1 | D (outreach), C (prospect detail) | Email + report generation loop |
| P2 | E, F, G | Pipeline management and configuration |

Full screen list matches section 2.6 in `nthdesigns-prospect-scanner-plan.md`.

---

## Implementation handoff notes

When exporting to Claude Code or the repo:

- Score badges: higher = weaker prospect (invert typical "green is good" mental model on internal tables only)
- Public report: show grade A–F, not `combined_score`
- Severity chips: critical / serious / moderate with accessible contrast
- Tailwind v4, Inertia layouts: authenticated shell vs standalone public page
- Reference existing pages: `resources/js/Pages/Search/Show.jsx`, `resources/js/Pages/Prospect/Show.jsx`, `resources/js/Pages/Report/Public.jsx`
