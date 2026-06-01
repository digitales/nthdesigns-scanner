# nthdesigns Prospect Scanner — Business Case & Viability Assessment

**Date:** 2026-05-31  
**Status:** Internal tool, active development  
**Author:** Senior analysis (Claude Code)

---

## 1. What the Product Is

A dual-signal prospecting and outreach platform for nthdesigns. It identifies UK SMEs with weak digital presence, scores them against two independent quality signals — Google Business Profile (GBP) health and website accessibility/performance — then automates personalised cold outreach.

The product is currently an internal tool. It is architected for eventual SaaS licensing.

---

## 2. Core Functionality (As Built)

### 2.1 Discovery Pipeline

- **Niche + city search** — queries Google Places API (New) by business category and location, returns up to 20 prospects per search
- **Direct URL scan** — audit a single website by URL, bypassing Places discovery
- **Niche scanner** — batch survey of niches across cities, ranks by opportunity score (avg GBP weakness, % with no website, % low reviews) to surface high-yield categories before committing to full searches
- **Benchmark capture** — stores the top-ranked competitor in the niche at scan time; all subsequent scoring is relative to that benchmark

### 2.2 Scoring Engine

Two independent signals, combined into a single 0–100 weakness score (higher = weaker = better prospect):

**GBP Signal:**
- Absolute scoring: missing phone, no website, low reviews, no photos, no description, incomplete hours
- Relative scoring: review gap vs benchmark, photo gap vs benchmark, rating gap vs benchmark

**Accessibility Signal (axe-core + Lighthouse):**
- Violation counts by severity (critical, serious, moderate, minor)
- Lighthouse accessibility score < 70
- Lighthouse performance score — separate signal surfaced on prospect detail
- Screenshot capture of failing elements

**Combined score + dominant angle** (`gbp`, `accessibility`, or `both`) — drives which pitch angle the AI selects for outreach.

### 2.3 Public Audit Reports

- Auto-generated shareable report per prospect (`/r/{token}`) — no login required
- Shows: grade (A–F), violation breakdown, top violations with plain-English copy, GBP vs benchmark comparison, Lighthouse scores, screenshots
- Expires after configurable days (default 30)
- View tracking (count + first-view IP)
- Booking CTA — inline TidyCal embed or external redirect

### 2.4 AI Outreach Generation

- Claude-powered (via OpenRouter) personalised cold email per prospect
- Pitch angle auto-resolved from dominant score signal or manually overridden
- Includes report URL when available — converts cold email to credibility demonstration
- Subject line + email body returned as structured JSON
- Token usage tracked per generation
- Bulk outreach: select multiple prospects, generate batch, manage selections via queue

### 2.5 CRM Layer

- Outreach status tracking: `generated` → `sent` → `responded`
- Prospect notes (free-text, timestamped)
- Saved prospects list (separate from search results)
- Search history with re-run capability
- Report dashboard — view all generated reports with view counts
- Export to CSV

### 2.6 Operator Settings

- Per-user booking URL (overrides global config on reports)
- Niche ignore list — suppress niches from niche scanner view
- NicheInclusionOverride — force-include specific niches regardless of global filters
- Niche maintenance: manual scan trigger, bootstrap from catalog

### 2.7 Infrastructure

- Laravel 13, Inertia + React, PostgreSQL
- Async job queue (database queue, Laravel Cloud workers)
- Separate Fly.io browser service — Playwright + axe-core run isolated from app workers
- Cloudflare browser service client (alternative)
- Queue channels: `searches`, `niches`, `auditing`

---

## 3. Use Cases

### Primary (nthdesigns internal)

| Use Case | Workflow |
|---|---|
| New business pipeline generation | Weekly niche search → score → select top prospects → generate reports → send outreach emails with report links |
| Niche viability assessment | Niche scanner → opportunity score → decide whether to pursue category |
| Compliance-led pitch | Prospect has material WCAG violations → auto-accessibility pitch → report shows specific violations → book call CTA |
| GBP-led pitch | Prospect has weak GBP vs local top-ranked competitor → visibility/cost-of-ads pitch → report shows review/photo gaps |
| Single-site audit | Direct URL scan → instant report → share with prospect or use in proposal |

### Secondary (potential clients/partners)

| Use Case | Workflow |
|---|---|
| Agency prospecting workflow | Agency runs niche + city searches for their service area → generates outreach at scale |
| Freelance local SEO | Solo operator uses niche scanner to identify high-opportunity categories → outreach to GBP optimisation clients |
| White-label audit delivery | Agency sends personalised report link to prospect as part of sales process |
| Accessibility compliance consultancy | Consultancy uses tool to pipeline WCAG remediation clients ahead of EAA enforcement |

---

## 4. Business Case Assessment

### 4.1 Core Thesis

The product closes a gap no current tool occupies: a single prospecting workflow combining **GBP signal + accessibility signal + AI outreach** at local-business granularity. Each signal exists in isolation in competing tools; the combination is the differentiator.

The business case is strongest as **internal marketing infrastructure** first. It turns development time into client pipeline with no additional sales cost. Every use case thereafter is additive.

### 4.2 Market Timing

Two converging factors make 2026 a strong window:

1. **European Accessibility Act (EAA)** — private sector enforcement began June 2025. Many UK SMEs with EU-facing services have not audited. Non-compliance is a live, verifiable risk, not a hypothetical. The tool's pitch leads with proof, not opinion.

2. **Google Maps / GBP competition** — local businesses increasingly depend on Maps placement for organic lead generation. Paid keyword CPCs for local categories (dental, legal, trades) range £3–25/click. A well-optimised GBP delivers equivalent visibility organically. The gap between weak and strong profiles is quantifiable and compelling.

Neither factor requires the prospect to believe a design agency's opinion. The report shows them the numbers.

### 4.3 Competitive Position

No direct competitor combines all three signals (GBP + accessibility + AI outreach) in a single prospecting workflow:

- **BrightLocal / Whitespark** — GBP-focused, no accessibility, not a prospecting tool, subscription-heavy
- **Semrush / Ahrefs** — site audits with some accessibility; not local-prospect-focused; no GBP signal; expensive
- **Siteimprove / Deque** — enterprise accessibility; no prospecting layer; £10k+ contracts
- **Wave / axe DevTools** — manual accessibility testing; no automation or prospecting
- **Outscraper** — raw data scraping; no scoring, no outreach, no accessibility

The moat is the combined scoring model and the public report layer — a prospect can verify every claim in the email themselves before replying. This is difficult for competitors to replicate without rebuilding both the GBP and accessibility pipelines.

### 4.4 Revenue Potential

Three independently viable streams:

**Stream 1: Client acquisition for nthdesigns (immediate)**  
Tool generates qualified leads → standard client work (accessibility remediation, design, GBP optimisation). Conservative model: 40 prospects/week, 3% annual conversion, £1,200 avg project value → **~£72,000/year in attributable revenue**. No subscription infrastructure needed.

**Stream 2: Done-for-you GBP service (months 3–6)**  
GBP audit + optimisation as a managed service. One-off £150–400, monthly management £200–350/month. Requires VA operational support. Optional — only pursue if GBP leads exceed design capacity.

**Stream 3: SaaS licensing (months 6–18)**  
Open to agencies and freelancers. Pricing: Solo £39/month, Agency £89/month, White-label £149/month. Conservative 12-month projection: ~£10,000 net. Moderate 18-month: ~£27,000 net. These projections are low-risk because the product is already validated internally before external launch.

### 4.5 Risk Factors

| Risk | Likelihood | Impact | Notes |
|---|---|---|---|
| Google Places ToS violation | Medium | Medium | Raw payloads expire 30 days; re-fetch on demand; typical usage is well within ToS |
| PECR cold email compliance | Medium | Medium | B2B-only; avoid sole trader targets; responsibility sits with the operator sending email |
| EAA enforcement weaker than expected | Low | Medium | GBP and performance pitch remain valid independently; tool is not a single-signal bet |
| Competitor enters the space | Medium | Low | Speed advantage + nthdesigns track record; first-mover in this specific niche |
| API cost escalation | Low | Low | Google Places + OpenRouter costs ~£1–1.50/search at current usage; absorbed into pricing |
| Playwright/browser service reliability | Medium | Low | Browser service on Fly.io is isolated; audits degrade gracefully to GBP-only if unavailable |

---

## 5. What to Sell and When

### Now (months 1–3): Internal pipeline only

The tool is already capable of generating a lead-generation workflow. Minimum viable internal use requires no further feature development. Focus:

- Run 5–10 niche searches per week
- Identify top-scoring prospects
- Send outreach emails with report links
- Track responses, refine prompts based on reply rates
- Measure conversion from outreach → call → paid engagement

**This phase costs nothing to run and generates revenue.**

### Short Term (months 3–6): Productise the report as a deliverable

The public audit report (`/r/{token}`) is already shareable and professional. It can be sold as a standalone product:

- WCAG accessibility audit report: £400–800 (send report link, offer remediation)
- GBP audit report: £150–200 (send report link, offer optimisation)
- Bundle: £500–900

The report page is the sales document. No separate PDF generation needed; the tool already does this.

**Sell the output, not the tool.**

### Medium Term (months 4–9): Accessibility retainer

Once a client receives a report and pays for remediation, they need ongoing monitoring. The tool already re-runs audits on demand. Package as:

- Monthly accessibility monitoring retainer: £100–250/month
- Automated re-scan + report delivery on a schedule (requires minor feature work: scheduled re-audit + email delivery)

This is the highest-value recurring revenue stream available without SaaS infrastructure.

### Longer Term (months 9–18): SaaS for agencies

Multi-tenant access to the tool for other agencies and freelancers. Required feature work before external launch:

- Per-user API key management (currently per-installation config)
- Usage metering / rate limiting per plan
- Billing integration (Stripe)
- Onboarding flow for new users
- White-label report branding (custom logo, colour scheme on `/r/{token}`)

The platform is architecturally ready for multi-tenancy (user_id on searches, per-user settings already exist). The gap is billing and onboarding — roughly 4–6 weeks of focused build time.

**Pricing:** Solo £39/month, Agency £89/month, White-label £149/month.

---

## 6. Long-Term Viability

### Strong signals

- **Regulatory tailwind** — EAA is law; accessibility remediation demand is structural, not cyclical
- **Google local search dependency** — GBP relevance is increasing as zero-click searches grow; the pitch writes itself
- **AI cost trajectory** — outreach generation costs are falling; the margin on AI-assisted features improves over time
- **No dominant competitor in this specific niche** — the window to establish is open now
- **Architecture is production-grade** — Laravel Cloud deployment, async queue, separate browser service; scales without re-architecture

### Risks to viability

- **Google Places API dependency** — a ToS change or pricing restructure would require migration to alternative data sources (e.g., Outscraper, Places scraping). Mitigable by caching and avoiding bulk-export use cases.
- **Commoditisation of accessibility scanning** — tools like Semrush are adding accessibility features. Differentiation must come from the prospecting workflow and outreach layer, not the scan alone.
- **EAA enforcement uncertainty** — if enforcement proves slow or toothless, the urgency angle weakens. The GBP and performance signals remain independently compelling.

### Verdict

The product has a clear, immediate revenue path (internal lead generation), a short-term productisation play (report as a deliverable), a medium-term recurring revenue stream (accessibility retainer), and a longer-term SaaS option. Each layer is independently viable and does not depend on the others succeeding.

The single highest-leverage near-term action is to **run the tool weekly and send outreach**. That turns the existing build into revenue without any further feature development.

---

## 7. Capability Gaps (for future roadmap consideration)

These are not blocking issues — the tool is functional now. They represent the delta between current state and SaaS-ready.

| Gap | Required for | Effort |
|---|---|---|
| Scheduled re-audits + email delivery | Accessibility retainer product | Medium |
| Per-user API key management | External users | Small |
| Usage metering / plan limits | SaaS billing | Medium |
| Stripe billing integration | SaaS launch | Medium |
| White-label report branding | White-label tier | Small |
| Onboarding flow | New external users | Small |
| Multi-user team accounts | Agency tier | Medium |
| Report PDF export | Standalone audit deliverable | Small |

Total effort to SaaS-ready: approximately 6–8 weeks part-time.

---

## 8. Summary

| Dimension | Assessment |
|---|---|
| **Current state** | Functional internal tool; production deployed on Laravel Cloud |
| **Immediate value** | Lead generation for nthdesigns — operational now |
| **Short-term sell** | Standalone audit report as paid deliverable (£400–800) |
| **Medium-term sell** | Accessibility monitoring retainer (£100–250/month) |
| **Long-term sell** | SaaS for agencies/freelancers (£39–149/month) |
| **Competitive moat** | Dual-signal (GBP + accessibility) + AI outreach — no direct equivalent |
| **Market timing** | Strong: EAA enforcement live, GBP competition increasing |
| **Architecture readiness** | Multi-tenancy-ready; billing/metering is the primary gap |
| **Viability verdict** | High — each revenue layer is independent; internal use alone justifies the build |
