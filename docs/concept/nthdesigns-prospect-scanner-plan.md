# nthdesigns Prospect Scanner — Business & Implementation Plan

---

## Part 1: Business Plan

---

### 1.1 Concept

A dual-signal prospect scanner that identifies UK SMEs with weak digital presence across two dimensions: Google Business Profile quality and website accessibility/performance compliance. The tool generates scored prospect lists and personalised outreach emails. It operates as internal business development infrastructure for nthdesigns, with an optional path to licensing it as a SaaS product to other agencies and freelancers.

The business model has three layers, which can be pursued sequentially:

1. **Lead generation for nthdesigns** — use the tool to find clients who need design and accessibility work, pitch that work directly. The tool never needs to be a public product.
2. **Done-for-you service** — offer GBP optimisation and accessibility remediation as a managed service, using the tool as operational infrastructure.
3. **SaaS licensing** — open the tool to other local SEO freelancers and small design agencies once the product is stable.

Start with layer one. It requires no sales infrastructure and turns build time into client pipeline. Layers two and three are optional upside.

---

### 1.2 Target Market

**Primary: UK SMEs with outdated or non-compliant web presence**

The core prospect is a UK business in a service niche (trades, professional services, health and wellness, hospitality) that has either:

- A neglected Google Business Profile (low reviews, no photos, incomplete information), or
- A website with material WCAG accessibility failures, or both.

These businesses exist in volume. The European Accessibility Act compliance deadline for private sector businesses serving EU customers was June 2025. Many UK businesses with EU-facing services have not audited their sites. Non-compliance is an active risk, not a hypothetical one.

**Secondary: Local SEO freelancers and small agencies**

Operators running GBP optimisation or local SEO services who need a prospecting and audit workflow tool. These are the SaaS tier customers. They are not the priority in year one but represent a recurring revenue stream once the product is proven internally.

**Niche focus for early outreach (recommended)**

Rather than spray across all industries, focus on two or three niches where the compliance gap and GBP neglect overlap. Recommended:

- **Private healthcare and dental** — high average transaction value, WCAG compliance increasingly expected, active online presence matters for patient acquisition, often run by clinicians who have neglected their web presence
- **Legal services (small firms, sole practitioners)** — regulated profession with growing accessibility expectations, high lifetime client value, enough practices to generate pipeline
- **Hospitality (independent hotels, restaurants)** — high GBP dependence, mixed web quality, clear cost-of-paid-ads comparison

One niche at a time. Depth over breadth in the prospecting phase.

---

### 1.3 Value Proposition

**For the accessibility/design pitch:**

"Your website has [X] accessibility violations and is likely non-compliant with WCAG 2.1 AA. Under the European Accessibility Act, businesses with EU-facing services face enforcement risk. I have identified the specific issues and can fix them."

This leads with risk and specificity, which converts better than a generic design pitch. The prospect cannot dismiss it as opinion — there are objective failure counts.

**For the GBP pitch:**

"Businesses in your category in [city] are spending £[X] per click on Google Ads for the visibility that an optimised GBP listing delivers organically. Your profile is currently ranked outside the top 7 and has [specific weaknesses]. I can fix this."

Cost framing with specific weaknesses performs better than generic "improve your online presence" language.

**Combined pitch (strongest):**

Lead with whichever signal is stronger for that prospect. A business with catastrophic accessibility failures and a decent GBP gets the compliance pitch. A business with a functional site but a non-existent GBP profile gets the visibility pitch. The tool surfaces both and the outreach engine picks the stronger angle.

**Verifiable proof layer (highest converting):**

Every outreach email includes a link to a personalised, public-but-unlisted audit report page generated specifically for that prospect. The email becomes:

> "I ran an audit on [businessname].co.uk and found 23 accessibility violations, including missing alt text on 14 images and a contrast failure on your main CTA button that affects roughly 8% of users with visual impairments. Full findings here: [report link]. Happy to walk through fixes on a call."

The report page shows their specific violations with screenshots of failing elements, a direct GBP comparison against the top-ranked competitor in their niche and city, severity ratings, plain-English explanations, and a single CTA booking link. The prospect can verify every claim themselves before replying. This transforms cold outreach into a credibility demonstration and dramatically improves response rates over email alone.

---

### 1.4 Revenue Streams

#### Stream 1: Design and Accessibility Remediation (nthdesigns core)

One-off project work sourced via the tool. The tool is marketing infrastructure; the revenue is standard client work.

| Service                                           | Price range    | Notes                              |
| ------------------------------------------------- | -------------- | ---------------------------------- |
| WCAG accessibility audit (report only)            | £400–800       | Delivered as PDF; foot in the door |
| Accessibility remediation (implement fixes)       | £800–3,000     | Depends on site complexity         |
| Full design refresh with accessibility compliance | £2,500–8,000   | Larger engagement                  |
| Ongoing accessibility monitoring retainer         | £100–250/month | Monthly scan + report              |

Conversion assumption: if the tool surfaces 40 qualified prospects per week and 3% convert to a paid engagement over 12 months, that is roughly 60 new clients per year. At an average project value of £1,200, that is £72,000 in additional revenue attributable to the tool. Conservative estimate.

#### Stream 2: Done-for-You GBP Optimisation (optional service line)

Running the GBP audit and optimisation as a managed service, delivered by a VA once the workflow is documented.

| Tier                                | Price          | Deliverable                                  |
| ----------------------------------- | -------------- | -------------------------------------------- |
| One-off GBP audit + recommendations | £150–200       | Written report, no implementation            |
| GBP optimisation (one-time)         | £250–400       | Implement fixes, photo strategy, description |
| Monthly GBP management              | £200–350/month | Ongoing posts, review responses, updates     |

This stream requires operational overhead (VA, quality control) and is optional. Only worth pursuing if the tool is generating more GBP leads than nthdesigns can absorb as design clients.

#### Stream 3: SaaS Licensing

Open the tool to other agencies and freelancers once it is stable and has been validated internally.

| Plan        | Price      | Limits                           |
| ----------- | ---------- | -------------------------------- |
| Solo        | £39/month  | 5 searches/day, 1 user           |
| Agency      | £89/month  | 20 searches/day, 3 users         |
| White label | £149/month | 20 searches/day, custom branding |

Revenue projections at scale:

| Scenario                 | Accounts                          | MRR    | Annual net (after API costs) |
| ------------------------ | --------------------------------- | ------ | ---------------------------- |
| Conservative (12 months) | 15 solo, 4 agency                 | £941   | ~£10,000                     |
| Moderate (18 months)     | 35 solo, 12 agency                | £2,433 | ~£27,000                     |
| Active sales (24 months) | 60 solo, 25 agency, 5 white label | £5,530 | ~£60,000                     |

SaaS is not the priority in year one. Build it for yourself first.

---

### 1.5 Competitive Landscape

| Tool                | What it does                             | Gap                                                                   |
| ------------------- | ---------------------------------------- | --------------------------------------------------------------------- |
| BrightLocal         | GBP tracking, local SEO reporting        | No accessibility scanning; subscription-heavy; not a prospecting tool |
| Whitespark          | Local citation and GBP tools             | US-focused; no outreach generation                                    |
| Semrush / Ahrefs    | Site audits including some accessibility | Not local prospect-focused; expensive; no GBP signal                  |
| Siteimprove / Deque | Enterprise accessibility platforms       | Enterprise pricing; no prospecting layer                              |
| Outscraper          | Raw data scraping                        | No scoring, no outreach, no accessibility                             |
| Wave / axe DevTools | Manual accessibility testing             | No automation, no prospecting, no outreach                            |

No direct competitor combines GBP weakness scoring, accessibility audit, and AI-generated outreach in a single prospecting workflow. That is the gap.

---

### 1.6 Go-to-Market

**Phase 1: Internal use only (months 1–3)**

Do not launch publicly. Use the tool to generate leads for nthdesigns. Run 5–10 niche searches per week, review scored prospects, send personalised outreach from nthdesigns. Track conversion rate and refine the scoring rubric and outreach prompts based on real responses. This phase validates the product and generates revenue simultaneously.

**Phase 2: Soft launch to communities (months 4–6)**

Post about the tool in relevant communities with a working demo. Recommended channels:

- UK Web Design Facebook groups and Slack communities
- r/bigseo and r/webdev
- Local SEO forum threads (Black Hat World, local SEO specialists)
- LinkedIn posts about the build, specifically the accessibility + EAA compliance angle
- Write one detailed post about "how I built a WCAG compliance scanner in Laravel 13" — developer audience but generates SEO and inbound

Offer free trials to first 10 external users in exchange for feedback. Do not optimise for conversion yet; optimise for learning what the product is missing.

**Phase 3: Paid acquisition and partnerships (months 7–12)**

Once the product is validated:

- Target Google Ads on "accessibility audit tool UK" and "WCAG compliance checker" terms — these have purchase intent
- Partner with web design communities or freelancer platforms for affiliate or bundle deals
- Offer white label tier to agencies who want to use the tool under their own brand

---

### 1.7 12-Month Financial Projection

Assumptions: nthdesigns is the primary client-facing entity; tool is built in spare time over 8 weeks; no staff until month 10+.

| Month     | Stream 1 (design work) | Stream 2 (GBP) | Stream 3 (SaaS) | API costs | Net         |
| --------- | ---------------------- | -------------- | --------------- | --------- | ----------- |
| 1–2       | £0 (building)          | £0             | £0              | £20       | -£20        |
| 3         | £1,200                 | £0             | £0              | £30       | £1,170      |
| 4         | £2,400                 | £0             | £0              | £40       | £2,360      |
| 5         | £2,400                 | £400           | £0              | £50       | £2,750      |
| 6         | £3,600                 | £800           | £200            | £60       | £4,540      |
| 7         | £3,600                 | £800           | £400            | £80       | £4,720      |
| 8         | £4,800                 | £1,200         | £600            | £90       | £6,510      |
| 9         | £4,800                 | £1,200         | £800            | £100      | £6,700      |
| 10        | £6,000                 | £1,600         | £1,000          | £120      | £8,480      |
| 11        | £6,000                 | £1,600         | £1,200          | £140      | £8,660      |
| 12        | £7,200                 | £2,000         | £1,600          | £160      | £10,640     |
| **Total** | **£42,000**            | **£9,600**     | **£5,800**      | **£890**  | **£56,510** |

Stream 1 projections assume 2–4 new design/accessibility clients per month sourced via the tool at an average project value of £1,200–1,800. These are conservative. Stream 2 requires VA support from month 5 onwards (cost not included above — budget £500–800/month for a part-time VA if you pursue this).

---

### 1.8 Risk Register

| Risk                                      | Likelihood | Impact | Mitigation                                                                      |
| ----------------------------------------- | ---------- | ------ | ------------------------------------------------------------------------------- |
| Google Places ToS restriction on caching  | Medium     | Medium | Expire raw_payload after 30 days; re-fetch on demand                            |
| PECR cold email compliance                | Medium     | Medium | B2B-only outreach; ToS places responsibility on user; avoid sole trader targets |
| EAA enforcement weaker than expected      | Low        | Medium | Pitch is still valid on UX and SEO grounds if legal urgency is overstated       |
| Competitors copy the dual-signal approach | Medium     | Low    | Speed advantage; nthdesigns brand as trust signal                               |
| API costs outpace SaaS revenue            | Low        | Low    | Cost per search is ~£1.10; priced into tiers                                    |
| Headless Chrome resource spikes           | Medium     | Low    | Queue sizing; rate-limit concurrent audit jobs                                  |

---

---

## Part 2: Implementation Plan

---

### 2.1 Product Architecture

A single Laravel 13 application with two scanning pipelines sharing a common job queue and scoring output layer.

```
┌─────────────────────────────────────────────┐
│               nthdesigns Scanner             │
│                                              │
│  ┌──────────────┐    ┌────────────────────┐  │
│  │  Discovery   │    │   Audit Pipeline   │  │
│  │  (Places API)│───▶│  GBP Scorer        │  │
│  │              │    │  Accessibility     │  │
│  │              │    │  Scorer (axe-core) │  │
│  └──────────────┘    └────────────────────┘  │
│           │                    │              │
│           ▼                    ▼              │
│  ┌──────────────────────────────────────┐    │
│  │         Prospect Store (DB)          │    │
│  │  combined_score, gbp_score,          │    │
│  │  a11y_score, weakness_flags          │    │
│  └──────────────────────────────────────┘    │
│           │                                   │
│           ▼                                   │
│  ┌──────────────────────────────────────┐    │
│  │     Outreach Generator               │    │
│  │     (Laravel AI SDK → Claude)        │    │
│  │     picks strongest pitch angle      │    │
│  └──────────────────────────────────────┘    │
└─────────────────────────────────────────────┘
```

---

### 2.2 Tech Stack

| Layer               | Choice                       | Version                        |
| ------------------- | ---------------------------- | ------------------------------ |
| Framework           | Laravel                      | 13 (PHP 8.3+)                  |
| Frontend            | Inertia.js + React           | v3 + React 19.2.x              |
| Styling             | Tailwind CSS                 | v4                             |
| AI                  | Laravel AI SDK               | stable (ships with Laravel 13) |
| Queue               | Laravel Horizon + Redis      | latest                         |
| Accessibility audit | axe-core via Node subprocess | latest                         |
| Performance audit   | Google Lighthouse CLI        | latest                         |
| Database            | PostgreSQL                   | 16+                            |
| Auth                | Laravel Breeze               | latest                         |
| Deployment          | Laravel Forge + Hetzner CX31 | 2 vCPU, 8GB RAM                |

**Server sizing note**: the CX31 (£8.50/month) handles headless Chrome audit jobs. Do not use a 1GB VPS. If concurrent audits cause memory pressure, add a dedicated queue worker server via Forge.

---

### 2.3 Data Models

#### `searches`

```
id
user_id
niche
city
country              (default 'GB')
scan_type            (enum: gbp_only, accessibility_only, combined)
status               (enum: pending, discovering, auditing, complete, failed)
total_found          (integer, nullable)
created_at
updated_at
```

#### `prospects`

```
id
search_id
place_id             (Google Places ID, unique per search)
business_name
phone                (nullable)
website_url          (nullable)
address
rating               (decimal 2,1)
review_count         (integer)
photo_count          (integer)
has_description      (boolean)
hours_complete       (boolean)

gbp_score            (smallint 0–100, higher = weaker)
gbp_flags            (json)

a11y_score           (smallint 0–100, higher = more violations)
a11y_flags           (json)          ← array of specific violation strings
performance_score    (smallint 0–100, from Lighthouse)

combined_score       (smallint 0–100) ← weighted composite
dominant_angle       (enum: gbp, accessibility, both)

audit_status         (enum: pending, complete, failed, skipped)
                     ← skipped when no website_url

raw_gbp_payload      (json)
raw_a11y_payload     (json, nullable)
raw_lighthouse_payload (json, nullable)

expires_at           (timestamp)      ← 30 days from creation for ToS compliance
created_at
updated_at
```

#### `outreach_emails`

```
id
prospect_id
user_id
prospect_report_id   (foreign key, nullable)  ← links to generated report
pitch_angle          (enum: gbp, accessibility, combined)
subject_line         (string)
email_body           (text)
model_used           (string)
prompt_tokens        (integer)
completion_tokens    (integer)
sent_at              (timestamp, nullable)
response_received    (boolean, default false)
created_at
```

#### `audit_jobs`

```
id
prospect_id
job_type             (enum: gbp_score, accessibility, lighthouse, screenshot)
status               (enum: pending, running, complete, failed)
attempts             (smallint)
error_message        (text, nullable)
started_at           (timestamp, nullable)
completed_at         (timestamp, nullable)
created_at
```

#### `prospect_reports`

```
id
prospect_id
token                (uuid, unique, indexed)   ← public URL key
benchmark_place_id   (string, nullable)        ← top-ranked competitor place_id
screenshot_paths     (json)                    ← array of object storage paths
report_data          (json)                    ← denormalised snapshot of scores,
                                                  flags, benchmark at generation time
                                                  (insulates report from live data changes)
viewed_at            (timestamp, nullable)     ← first view timestamp
view_count           (integer, default 0)
viewer_ip            (string, nullable)        ← last viewer IP for warm lead signal
expires_at           (timestamp)              ← 30 days, consistent with raw payload policy
created_at
updated_at
```

---

### 2.4 Core Services

#### `GooglePlacesService`

Discovers businesses and fetches GBP detail. Unchanged from the GBP-only plan. Returns place details including `websiteUri` which feeds the accessibility pipeline.

#### `GbpScoringService`

Applies the GBP weakness rubric (review count, photo count, description, hours, rating). Returns `gbp_score` and `gbp_flags`.

#### `AccessibilityAuditService`

Runs axe-core against a URL via a Node.js subprocess. Laravel dispatches a shell command to a small Node script that uses Puppeteer + axe-core, captures the violations JSON, and returns it to PHP.

```php
class AccessibilityAuditService
{
    public function audit(string $url): AccessibilityResult
    // Spawns: node scripts/audit.js {url}
    // Returns: violation_count, violations (array), score (0–100)
}
```

Node script (`scripts/audit.js`) structure:

```javascript
const { chromium } = require("playwright");
const { checkA11y, injectAxe } = require("axe-playwright");

// 1. Launch headless Chromium, navigate to URL
// 2. Run axe analysis, capture violations JSON
// 3. For each critical/serious violation (up to 5):
//    - locate the first affected element
//    - screenshot a 400px context window around it
//    - save to /tmp/screenshots/{token}-{index}.png
// 4. Output JSON to stdout: { violations, screenshots: [paths] }
// Laravel reads stdout, parses, uploads screenshots to object storage
```

**Playwright over Puppeteer**: Playwright is better maintained in 2026 and handles flaky page loads more gracefully. Use `axe-playwright`.

#### `LighthouseService`

Runs Lighthouse CLI for performance, SEO, and best practices scores. The accessibility score from Lighthouse partially overlaps with axe-core but use axe-core as the authoritative source for WCAG violation counts — Lighthouse's accessibility audit is less thorough.

```php
class LighthouseService
{
    public function audit(string $url): LighthouseResult
    // Spawns: lighthouse {url} --output=json --quiet --chrome-flags="--headless"
    // Returns: performance_score, accessibility_score, seo_score, best_practices_score
}
```

#### `ProspectScoringService`

Combines GBP and accessibility scores into a `combined_score` and determines `dominant_angle`.

```php
class ProspectScoringService
{
    public function combine(GbpScore $gbp, AccessibilityResult $a11y): CombinedScore
    // combined_score = (gbp_score * 0.4) + (a11y_score * 0.6)
    // dominant_angle: if a11y_score > 70 → accessibility
    //                 if gbp_score > 70 → gbp
    //                 else → both
    // Weighting favours accessibility because it is the stronger sales hook
}
```

**Phase 7 extension (post-launch):** once Lighthouse data is validated in production, extend `combine()` to a three-signal model:

```php
// combined_score = (gbp_score * 0.35) + (a11y_score * 0.50) + (performance_score * 0.15)
```

`dominant_angle` logic is unchanged — performance does not drive pitch angle selection. It acts as a quiet score booster for slow sites and surfaces as a secondary line in outreach emails when `performance_score < 30`. No new infrastructure is required; Lighthouse already runs as part of Phase 2.

#### `ReportGeneratorService`

Triggered when a prospect is added to outreach. Creates a `ProspectReport` record, fetches the top-ranked competitor from Places API for the GBP comparison panel, denormalises the current scores and flags into `report_data`, uploads screenshots from object storage, and returns the public report URL.

```php
class ReportGeneratorService
{
    public function generate(Prospect $prospect): ProspectReport
    // 1. Fetch benchmark: GooglePlacesService::getTopRankedInNiche()
    //    (the #1 Places result for the same niche + city query)
    // 2. Upload screenshots from local tmp to object storage
    // 3. Denormalise scores, flags, screenshots, benchmark into report_data JSON
    // 4. Create ProspectReport with UUID token and 30-day expiry
    // 5. Return report (URL: /r/{token})

    public function recordView(ProspectReport $report, string $ip): void
    // Increments view_count, sets viewed_at on first view, updates viewer_ip
}
```

**Report data snapshot rationale**: denormalising into `report_data` means the public report always shows what was true when the email was sent, not live-changing data. If a prospect improves their site before clicking the link, the report still reflects the original findings. Avoids an embarrassing situation where the report contradicts the email.

#### `OutreachGeneratorService`

Uses Laravel AI SDK. System prompt and user message adapt based on `dominant_angle`. When a `ProspectReport` exists for the prospect, the prompt includes the report URL so Claude can reference it naturally in the email body.

Three prompt variants:

- `accessibility` — leads with violation count and EAA compliance risk, includes report link
- `gbp` — leads with CPC benchmark and profile weaknesses, includes report link
- `combined` — leads with accessibility, references GBP as additional context, includes report link

---

### 2.5 Job Queue Architecture

```
ScrapeProspectsJob
  └── For each place_id found:
        └── ScorePlaceJob
              ├── Calls GbpScoringService (fast, no subprocess)
              └── If website_url exists:
                    └── AuditSiteJob (dispatched separately, slower queue)
                          ├── AccessibilityAuditService (subprocess, ~30 sec)
                          │     └── Captures screenshots of top 5 critical violations
                          └── LighthouseService (subprocess, ~20 sec)
                                └── On both complete: CombineScoresJob
                                      └── Uploads screenshots to object storage
                                            └── GenerateReportJob
                                                  └── ReportGeneratorService::generate()
                                                        (fetches benchmark, builds report_data,
                                                         creates ProspectReport with token)
```

`GenerateReportJob` runs on the `auditing` queue after scores are combined. The report is ready and linked before the user ever opens the outreach page, so generating emails with embedded report URLs requires no extra wait.

Two queues: `scraping` (fast, Places API calls) and `auditing` (slow, headless Chrome). Horizon supervises both with separate worker counts — 5 workers on scraping, 2 on auditing (headless Chrome is memory-hungry).

```php
// horizon.php
'environments' => [
    'production' => [
        'scraping-supervisor' => [
            'connection' => 'redis',
            'queue' => ['scraping'],
            'balance' => 'auto',
            'maxProcesses' => 5,
        ],
        'auditing-supervisor' => [
            'connection' => 'redis',
            'queue' => ['auditing'],
            'balance' => 'auto',
            'maxProcesses' => 2,
            'memory' => 512,
        ],
    ],
],
```

---

### 2.6 Frontend Pages

#### `/search` — ProspectSearch

- Form: niche, city, country, scan type (GBP only / Accessibility only / Combined)
- Submit creates a `Search` record and dispatches `ScrapeProspectsJob`
- Results table polls while `search.status !== 'complete'` using Inertia's `router.reload()` with a 5-second interval
- Table columns: Business Name, Combined Score (coloured badge), GBP Score, A11y Score, Dominant Angle, Report Ready (indicator), Website (link), Actions
- Row-level actions: Add to Outreach, Preview Weaknesses (expandable flags), View on Maps, Preview Report

#### `/outreach` — OutreachGenerator

- Left: selected prospects with dominant angle and report status shown per row
- Right: generation controls
  - Pitch angle override (auto / force GBP / force accessibility)
  - Agency name field (for outreach personalisation)
  - Optional CPC benchmark input for GBP pitches
  - "Generate All" button
- Output: card per prospect — subject line, email body (with report URL already embedded), copy button, edit-in-place textarea, "Mark as Sent" toggle, "Preview Report" link

#### `/saved` — SavedProspects

- Full history of all prospects across searches
- Filters: date range, niche, city, scan type, min combined score, dominant angle, report viewed (warm leads filter)
- CSV export (all columns or outreach-ready subset)
- Outreach history tab per prospect: shows all generated emails, sent status, report view count
- **Warm leads panel**: prospects whose report has been viewed but not yet responded — sorted by most recent view

#### `/reports` — ReportsDashboard (internal)

- Table of all generated reports
- Columns: Business Name, Token URL (copy), Created, Views, Last Viewed, Viewer IP
- "Warm" badge on reports viewed in last 7 days
- Filterable by niche, viewed/unviewed
- Quick link to the associated outreach email and prospect record

#### `/r/{token}` — PublicProspectReport (no auth)

- No navigation, no login prompt — clean branded page only
- Header: business name, URL, overall grade (A–F), nthdesigns branding
- **Accessibility panel**: violation count by severity (critical / serious / moderate), top 5 violations each with:
  - Screenshot of the failing element in context
  - WCAG criterion reference (e.g. 1.1.1 Non-text Content)
  - Plain-English user impact statement
  - One-line fix description (not a full tutorial — enough to show competence)
- **GBP comparison panel** (if GBP data available):
  - Side-by-side: their profile vs top-ranked competitor
  - Metrics shown: review count, photo count, description present, hours complete, rating
- **Performance panel**: Lighthouse scores as coloured dials (Performance, SEO, Best Practices)
- **CTA**: single prominent button — "Book a free 30-minute review" → Calendly or equivalent
- Footer: "This report was prepared by nthdesigns. It expires on [date]."
- Does not show the combined_score or internal scoring logic — that is internal only

#### `/settings` — UserSettings

- API key health check (Google Places, OpenRouter)
- Default country (pre-fill on search form)
- Default pitch agency name and Calendly/booking URL (used in report CTA)
- Object storage health check
- Account/billing (stubbed for now)

---

### 2.7 Build Phases

#### Phase 1 — Foundation (days 1–4)

- Laravel 13 install: `composer create-project laravel/laravel gbp-scanner`
- Breeze + Inertia + React stack: `php artisan breeze:install react`
- Tailwind v4: install `@tailwindcss/vite`, configure in `resources/css/app.css`
- Horizon: `composer require laravel/horizon && php artisan horizon:install`
- Migrations for all models
- `GooglePlacesService` with real API calls (test with Postman before wiring to Laravel)
- `GbpScoringService` (pure PHP, no external dependencies — write unit tests first)
- `ScrapeProspectsJob` and `ScorePlaceJob`
- Basic `/search` page: form, submit, polling, results table (GBP scores only at this stage)

**Exit criterion**: can run a real search for "dental practice Birmingham" and see a scored prospect table.

#### Phase 2 — Accessibility pipeline (days 5–8)

- Node.js setup on dev machine: `npm install playwright @axe-core/playwright`
- `scripts/audit.js` Node script: headless Chrome, axe-core, stdout JSON
- `AccessibilityAuditService` PHP wrapper calling the Node subprocess
- `LighthouseService` PHP wrapper (Lighthouse CLI installed globally via npm)
- `AuditSiteJob` and `CombineScoresJob`
- `ProspectScoringService` combining both signals
- Update results table to show a11y and combined scores
- Handle `audit_status: skipped` gracefully for prospects without a website

**Exit criterion**: a prospect with a website shows accessibility violation count and combined score.

#### Phase 3 — Outreach generation (days 9–11)

- OpenRouter API key and `OPENROUTER_MODEL` for outreach LLM calls
- `OutreachGeneratorService` with three prompt variants (each embeds report URL)
- `ReportGeneratorService`: denormalise scores, fetch benchmark place, build `report_data`
- `GenerateReportJob` wired into the end of `CombineScoresJob`
- `/outreach` page: selection, angle override, generation, copy/edit, report link per card
- Store generated emails to `outreach_emails` table with `prospect_report_id`

**Exit criterion**: select 3 prospects, click Generate All, get three emails with embedded report links.

#### Phase 4 — Public report page (days 12–13)

- `/r/{token}` Blade/Inertia page: accessibility panel with screenshots, GBP comparison panel, Lighthouse dials, CTA
- View tracking: `ReportGeneratorService::recordView()` on every page load
- `/reports` internal dashboard: report list with view counts and warm lead badges
- Warm leads panel on `/saved` page
- Booking URL wired through from settings to report CTA

**Exit criterion**: open a report link in an incognito window, see a branded page, check that the view registers in `/reports`.

#### Phase 5 — Saved prospects and export (day 14)

- `/saved` page with filters including warm leads filter
- CSV export via Laravel `StreamedResponse`
- Full outreach history per prospect
- "Mark as Sent" toggle on outreach cards

**Exit criterion**: export a filtered prospect list, filter to warm leads only.

#### Phase 6 — Polish and hardening (days 15–16)

- Error states: failed scrape, audit subprocess timeout, screenshot failure, API errors
- `expires_at` cleanup: nightly scheduled command purges raw payloads and report screenshots older than 30 days from both DB and object storage
- Rate limiting on search submissions
- Horizon dashboard gated behind auth
- Object storage bucket policy: private by default, screenshots served via signed URLs or public read (decide based on report page architecture)
- `.env.example` documentation
- Smoke tests for core services

**Exit criterion**: deploy to Hetzner via Forge, run a full search end-to-end including report generation in production.

#### Phase 7 — Performance signal (post-launch)

Prerequisites: Phase 6 complete and stable in production; at least 4 weeks of internal use data.

- Extend `ProspectScoringService::combine()` to include `performance_score` as a third weighted input:
  `combined_score = (gbp_score * 0.35) + (a11y_score * 0.50) + (performance_score * 0.15)`
- Update `/search` results table to show Lighthouse performance score as a visible column (it is already stored in `performance_score` from Phase 2)
- Update outreach prompt variants: add a conditional secondary line — "Your site also scored [X]/100 on Google's performance benchmark, which affects both rankings and bounce rate" — triggered only when `performance_score < 30`; never used as the opening line
- `dominant_angle` logic is unchanged — performance does not resolve as a pitch angle and does not affect email template selection
- No new infrastructure, no new API costs, no new queue jobs — Lighthouse already runs in the Phase 2 `AuditSiteJob` pipeline
- Update `/r/{token}` report page performance panel copy if needed to reflect the scoring weight

**Exit criterion**: a prospect with `performance_score < 30` receives a secondary performance line in the generated outreach email; `dominant_angle` remains `gbp`, `accessibility`, or `both` as before; existing unit tests for `ProspectScoringService` still pass after updating the weights.

---

### 2.8 Infrastructure Setup

**Hetzner CX31** (£8.50/month): 2 vCPU, 8GB RAM, 80GB SSD. Handles app + queue workers + headless Chrome. Upgrade to CX41 if audit jobs cause memory pressure.

**Hetzner Object Storage** (£4.26/month for 1TB): S3-compatible. Store violation screenshots here. Laravel's Filesystem abstraction supports S3-compatible storage via the `s3` driver pointed at Hetzner's endpoint. Screenshots are small (typically 20–80KB each); 1TB is overkill but the pricing is fixed regardless.

**Forge provisioning checklist:**

- PHP 8.3
- PostgreSQL 16
- Redis
- Node.js 20+ (for axe-core, axe-playwright, and Lighthouse)
- Chromium (for Playwright headless): `apt install chromium-browser`
- Lighthouse CLI: `npm install -g lighthouse`
- Playwright browsers: `npx playwright install chromium`
- Horizon daemon configured as a Forge daemon

**Domain**: separate from nthdesigns.co.uk if you intend to license the tool externally. Something like `auditpro.co.uk` or `siteprospect.co.uk`. If internal only, a subdomain of nthdesigns is fine. The public report pages at `/r/{token}` will be on whatever domain you choose — keep this in mind for the nthdesigns branding on the report.

**SSL**: Forge provisions Let's Encrypt automatically.

---

### 2.9 Environment Variables

```env
APP_NAME="nthdesigns Scanner"
APP_URL=https://scanner.nthdesigns.co.uk

DB_CONNECTION=pgsql
DB_DATABASE=nth_scanner
DB_USERNAME=
DB_PASSWORD=

REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis

GOOGLE_PLACES_API_KEY=
ANTHROPIC_API_KEY=

# Node subprocess paths
NODE_BINARY=/usr/bin/node
AUDIT_SCRIPT_PATH=/var/www/scanner/scripts/audit.js
LIGHTHOUSE_BINARY=/usr/local/bin/lighthouse

# Hetzner Object Storage (S3-compatible)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=nth-scanner-screenshots
AWS_ENDPOINT=https://fsn1.your-objectstorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true

# Report settings
REPORT_BOOKING_URL=https://calendly.com/yourhandle
REPORT_EXPIRY_DAYS=30

HORIZON_PREFIX=nth-scanner
```

---

### 2.10 Launch Sequence

| Week | Action                                                                     |
| ---- | -------------------------------------------------------------------------- |
| 1–2  | Build Phase 1 — GBP pipeline working locally                               |
| 3    | Build Phase 2 — Accessibility pipeline, combined scoring                   |
| 4    | Build Phase 3 — Outreach generation, report generator, report page         |
| 5    | Build Phase 4–5 — Saved prospects, export, warm leads panel                |
| 6    | Phase 6 — Harden, deploy to Forge/Hetzner including object storage         |
| 7    | Internal use begins — run 5 searches/week, send outreach with report links |
| 9    | Review response rates, view tracking data; refine report page copy and CTA |
| 11   | Soft launch post in communities — demo with real anonymised report example |
| 13   | First external SaaS users onboarded (free trial → paid)                    |
| 17   | Evaluate Stream 2 (GBP done-for-you) based on lead volume                  |
| 21   | SaaS paid tier live; agency plan and white label available                 |

---

### 2.11 Open Questions to Resolve Before Building

1. **Brand decision**: scanner.nthdesigns.co.uk (internal only) or separate product brand (public SaaS path)? The public report pages will carry this branding, so the decision affects how prospects perceive nthdesigns before they have even replied.

2. **Report page hosting**: the `/r/{token}` page is public-facing and branded. If you use `scanner.nthdesigns.co.uk/r/{token}`, it looks internal. A cleaner option is `report.nthdesigns.co.uk/r/{token}` — same application, separate subdomain, looks more intentional.

3. **Screenshot storage visibility**: serve screenshots as publicly readable (simpler, slightly less private) or via signed URLs that expire (more secure, small complexity overhead). For the content involved — a screenshot of a button with poor contrast — public read is fine.

4. **Sole trader exclusion**: will the outreach feature warn users when a prospect appears to be a sole trader? Relevant for PECR compliance on cold email.

5. **CPC benchmark source**: manual input, SEMrush API, or a hardcoded niche benchmark table. Decide before building the outreach prompt — it affects how the GBP panel on the report page is framed.

6. **Booking/CTA provider**: Calendly is the obvious choice for the report page CTA. TidyCal (one-time fee) is a reasonable alternative if you want to avoid a recurring subscription. Decide before building the report page so the URL is wired through settings correctly.

7. **Multi-user from day one?**: Breeze scaffolds single-user auth. If a VA will use the tool, add a simple team model early rather than retrofitting it.
