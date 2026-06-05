# Lean Audit & Refactor Backlog — Design Spec

**Date:** 2026-06-05  
**Status:** Approved  
**Goal:** Audit the nthdesigns-scanner codebase and produce a prioritized refactor backlog with medium-sized PR slices — no code changes in this effort.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Outcome | Prioritized refactor backlog (documented findings + recommended PR slices) |
| Scope | Backend-first (`app/`, `routes/`, `config/`, jobs, services, tests) with a lighter frontend pass (`resources/js/`) |
| Prioritization | Balanced scoring across maintainability, runtime efficiency, Laravel 13 alignment, and operational reliability |
| Docs approach | Fresh audit from code as source of truth, plus gap analysis on existing `docs/superpowers/` specs and plans |
| PR granularity | Medium — subsystem-sized slices |
| Methodology | Domain-aligned audit (Approach 1) |

---

## Problem statement

The project has grown quickly across scanning, auditing, scoring, outreach, OAuth/MCP, and booking subsystems. Architectural knowledge is spread across 20+ specs in `docs/superpowers/` with no `CONTEXT.md` or ADR index. Recent features (calendar bookings, website discovery, audit repair) added capability but also increased surface area in services, support classes, and controllers.

Without a structured audit, refactor work risks being ad hoc — either too granular (death by PR) or too broad (unreviewable diffs). This effort establishes a repeatable audit process and produces a scored backlog aligned with Laravel 13 best practices and operator workflow needs.

---

## Approach

Three approaches were considered:

1. **Domain-aligned audit (chosen)** — walk subsystems by workflow; findings map to medium PR slices.
2. **Layer-first audit** — audit Models → Services → Jobs → Controllers; rejected because it splits workflow logic and makes PR scheduling harder.
3. **Tool-driven audit** — lead with static analysis; rejected because it misses design debt and workflow boundaries.

---

## Audit methodology

Four phases, no code changes:

| Phase | What | Output |
|-------|------|--------|
| **1. Inventory** | Subsystem map, file/test counts, automated signals | Raw signal sheet |
| **2. Subsystem review** | Per subsystem: Laravel 13 checklist + manual code read | Finding cards |
| **3. Spec gap analysis** | Cross-reference `docs/superpowers/specs/` and `plans/` against code | Gap register |
| **4. Synthesis** | Score, dedupe, rank, assign medium PR slices | Final backlog |

### Phase 1: Automated signals

Run at audit start to establish baseline:

- Test suite health (`php artisan test`)
- Controllers without Form Requests (currently 6 requests vs ~25 controllers)
- Model `casts()` migration status (mixed `casts()` method and `$casts` property)
- Large files (>200 lines)
- `Support/` vs `Queries/` responsibility overlap
- Pint dry-run (`./vendor/bin/pint --test`)

### Phase 2: Laravel 13 checklist

Applied per subsystem:

- Thin controllers / clear service boundaries
- Form Request validation coverage
- Policy authorization consistency
- Job idempotency and failure recording
- Eloquent: `casts()` method, eager-loading, dedicated query objects
- Command structure (dry-run / execute patterns, e.g. `RepairAuditsCommand`)
- Config-driven behaviour (`config/scanner.php` seams)
- Observable failure paths for async and integration work

### Phase 3: Spec gap analysis

For each file in `docs/superpowers/specs/` and `docs/superpowers/plans/`:

| Check | Action |
|-------|--------|
| Spec marked **Implemented** | Spot-check key files named in spec still match decisions |
| Plan has unchecked `- [ ]` tasks | Flag if task appears undone in code |
| Recent feature, no spec | Flag as documentation debt (informational, not P1) |
| Test failures referencing changed signatures | Auto P1 gap (e.g. `DirectUrlScanJob` DI mismatch) |

### Phase 4: Synthesis

Merge finding cards, apply dedup rules, rank by score, group into medium PR slices with dependency notes.

---

## Subsystem map

Ten backend subsystems. Cross-cutting patterns get a single finding in S10, referenced by subsystem items.

| # | Subsystem | Key files | Related specs/plans |
|---|-----------|-----------|---------------------|
| **S1** | Search & prospecting | `SearchController`, `ScrapeProspectsJob`, `DirectUrlScanJob`, `GooglePlacesService`, `BraveSearchService`, `WebsiteDiscoveryService` | `2026-06-04-website-discovery`, `2026-05-28-single-site-url-audit` |
| **S2** | Niche scanning | `NicheScanController`, `ScanNicheJob`, `NicheSampleCollector`, `NicheExclusionService`, niche commands | `2026-05-27-niches-bootstrap`, `2026-05-28-niche-opportunity-confidence` |
| **S3** | Audit pipeline | `AuditSiteJob`, `ProspectAuditService`, `AuditRunnerService`, `CaptureScreenshotJob`, repair/backfill commands, `Support/*AuditQuery*` | `2026-06-02-audit-repair`, `2026-05-27-audit-backfill`, `2026-06-02-audit-job-error-details` |
| **S4** | Scoring & enrichment | `ScorePlaceJob`, `CombineScoresJob`, `GbpScoringService`, `A11yScoringService`, `DetectCmsJob`, `ProspectEnrichmentService` | `2026-05-27-gbp-scoring-flags`, `2026-06-01-cms-detection` |
| **S5** | Reports & public surfaces | `ReportBuilderService`, `PublicReportController`, `GenerateProspectReportJob`, `ScreenshotStorageService` | `2026-05-27-prospect-site-audit-detail`, `2026-05-29-prospect-page-speed-detail` |
| **S6** | Outreach | `OutreachController`, `OutreachEmailController`, `GenerateOutreachEmailJob`, `OutreachEmailGeneratorService` | `2026-05-29-outreach-queue-clickable-prospects` |
| **S7** | OAuth & MCP | `OAuthServerController`, `McpController`, `McpSearchService`, `ProgressFlowService`, OAuth models/services | `2026-06-01-mcp-scan-monitoring`, `2026-06-02-mcp-progress-flow`, `mcp-integration-guide.md` |
| **S8** | Booking & calendar | `AgencyBookingSettingsController`, `PublicReportBookingController`, `BookingAvailabilityService`, CalDAV providers | `2026-06-04-report-booking-fastmail` |
| **S9** | Operator settings & data hygiene | `SettingsController`, `UserSettingsService`, `PurgeExpiredProspectData`, ignore/exclusion controllers | `2026-05-29-niche-settings-maintenance` |
| **S10** | Shared infrastructure | `Support/`, `Queries/`, policies, `HandleInertiaRequests`, config, mail | — |

### Frontend light pass (F1)

Not a full subsystem audit. Surface checklist against S1–S9 pages:

- Inertia prop bloat / duplicated serialization in controllers
- Pages bypassing `Components/ui/` primitives
- Duplicated fetch/poll logic (Search progress, MCP monitoring)
- Missing loading/error states on async surfaces

Findings use the same rubric but cap at P2 unless they cause operator-facing failures. No new component library work — flag only.

---

## Scoring rubric

Each finding scored on four dimensions (1–3 each). Total (4–12) drives priority.

| Dimension | 1 (low) | 2 (medium) | 3 (high) |
|-----------|---------|------------|----------|
| **Maintainability (M)** | Cosmetic / style only | Moderate duplication or unclear boundary | Blocks understanding or causes repeated bugs |
| **Runtime efficiency (R)** | No measurable impact | Minor query/API overhead | N+1, redundant jobs, or expensive hot-path work |
| **Laravel 13 alignment (L)** | Works but non-idiomatic | Inconsistent with project patterns | Actively fights framework (inline validation, fat controllers, legacy casts) |
| **Operational reliability (O)** | Edge-case only | Harder to debug/recover | Stuck jobs, silent failures, missing observability on production paths |

### Priority tiers

| Tier | Score | Label | Scheduling |
|------|-------|-------|------------|
| **P1** | 10–12 | Do soon | Next refactor sprint; may include broken tests or production risk |
| **P2** | 7–9 | Plan | Schedule within 1–2 months |
| **P3** | 4–6 | Backlog | Worth doing; no urgency |
| **P4** | 4 with any 1s | Nice-to-have | Defer unless touching that file anyway |

### Finding card template

```
ID:        REF-S3-01
Subsystem: S3 Audit pipeline
Title:     Extract screenshot repair orchestration from RepairAuditsCommand
Scores:    M=2 R=1 L=3 O=2 → Total 8 (P2)
Evidence:  RepairAuditsCommand.php:142, FailedScreenshotQuery.php
Spec gap:  None — pattern established in 2026-06-02-audit-repair spec
PR slice:  Medium — "S3: thin RepairAuditsCommand, move selection to Support"
Risk:      Low — covered by RepairAuditsCommandTest
Effort:    ~2–4 hours
```

### Dedup rules

- Cross-cutting patterns (e.g. `casts()` migration) → one P2 finding in S10, referenced by subsystem findings
- Spec gaps with existing plan tasks → link to plan, don't duplicate; flag only if plan is stale or unimplemented
- Positive patterns → noted in **Keep** section, not scored as findings

### Tie-breaking within a tier

Higher **O** score first, then **M**, then subsystem dependency order (S10 before S1–S9 when it unblocks others).

---

## Deliverables

### 1. This design spec

`docs/superpowers/specs/2026-06-05-lean-audit-refactor-design.md` — audit charter.

### 2. Audit report (the backlog)

`docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md` — produced by executing this audit.

Structure:

```markdown
# Backend Refactor Backlog

## Executive summary
## Keep (positive patterns)
## Spec gap register
## Findings by subsystem (S1–S10)
## Cross-cutting findings (S10)
## Frontend light pass (F1)
## Recommended PR schedule
## Appendix: automated signal sheet
```

### PR schedule format

Medium slices, ~1 subsystem or one cross-cutting concern each. Target **8–15 PR slices**.

| PR | Subsystem | Title | Priority | Depends on |
|----|-----------|-------|----------|------------|
| PR-01 | S10 | Migrate remaining models to `casts()` method | P2 | — |
| PR-02 | S1 | Fix `DirectUrlScanJob` test drift + add Form Requests | P1 | — |

---

## Known baseline signals

Captured at design time; validated during Phase 1:

| Signal | Value |
|--------|-------|
| PHP files in `app/` | ~157 |
| JS files in `resources/js/` | ~94 |
| Test files | 83 |
| Test health | 331/337 passing (4 failures, 2 skipped, 3 risky) |
| Form Requests | 6 |
| Largest services | `ReportBuilderService` (457 lines), `WebsiteDiscoveryService` (316), `BrowserServiceClient` (303) |
| Largest controllers | `OAuthServerController` (288), `SearchController` (220), `ProspectController` (202) |
| `casts()` method models | 7 |
| `$casts` property models | 8 |
| Confirmed test drift | `DirectUrlScanJobTest` — 5 deps passed, 6 expected |

---

## Positive patterns to preserve

Document in the audit **Keep** section:

- `RepairAuditsCommand` dry-run / execute split with connection-aware queue presence
- Dedicated query classes in `Support/` for repair audit selection
- `ProspectAuditService::repairSiteAudit()` as a distinct path from `queueSiteAudit()`
- UI component refactor (`Components/ui/`) — treat as done; frontend pass flags drift only

---

## Out of scope

- Implementing any refactor (backlog only)
- Adding Larastan/PHPStan to the project (may recommend in findings)
- Full frontend redesign or new component library work
- Performance benchmarking or load testing
- Infrastructure/deployment changes

---

## Next step

Execute the audit per this spec → write backlog → invoke `writing-plans` to create an implementation plan for executing the PR schedule.
