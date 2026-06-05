# Lean Audit & Refactor Backlog — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute the domain-aligned codebase audit defined in `docs/superpowers/specs/2026-06-05-lean-audit-refactor-design.md` and produce `docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md` — a scored, prioritized refactor backlog with 8–15 medium PR slices. No application code changes.

**Architecture:** Four phases — inventory (automated signals), per-subsystem manual review (S1–S10), spec/plan gap analysis, synthesis into finding cards and PR schedule. Frontend light pass (F1) runs in parallel with S5–S9 page reviews.

**Tech Stack:** Laravel 13.8, PHPUnit, Pint, ripgrep, existing `docs/superpowers/specs/` and `plans/`.

**Spec:** `docs/superpowers/specs/2026-06-05-lean-audit-refactor-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md` | Final audit deliverable (create) |
| `docs/superpowers/specs/2026-06-05-lean-audit-refactor-design.md` | Audit charter (read-only reference) |
| `app/**` | Backend audit targets (read-only) |
| `resources/js/**` | Frontend light pass targets (read-only) |
| `tests/**` | Test coverage and drift signals (read-only) |
| `docs/superpowers/specs/*.md` | Spec gap analysis (read-only) |
| `docs/superpowers/plans/*.md` | Plan gap analysis (read-only) |

---

## Finding card format

Every finding appended to the backlog uses this exact block (fill all fields; use `—` only when genuinely N/A):

```markdown
#### REF-S{n}-{nn}: {Title}

| Field | Value |
|-------|-------|
| Subsystem | S{n} {name} |
| Scores | M={1-3} R={1-3} L={1-3} O={1-3} → Total {sum} ({P1\|P2\|P3\|P4}) |
| Evidence | `{path}:{line}` or grep output |
| Spec gap | {spec/plan link or "None"} |
| PR slice | Medium — "{one-line PR title}" |
| Risk | {Low\|Medium\|High} — {reason} |
| Effort | ~{hours} hours |
| Notes | {optional context} |
```

---

## Subsystem review checklist

Apply to every S1–S10 task. Record a finding when the answer is "yes, problem" or "partial, inconsistent":

1. **Controllers:** Does any controller method exceed ~40 lines or contain business logic that belongs in a service?
2. **Validation:** Are `store`/`update` actions using inline `$request->validate()` instead of Form Requests?
3. **Authorization:** Are policy checks missing on mutating routes? (`authorize()`, `$this->authorize()`, middleware)
4. **Jobs:** Is the job idempotent? Does it record failures via `AuditErrorRecorder` or equivalent?
5. **Queries:** Are there N+1 risks (missing `with()` on list endpoints)? Grep for `::all()`, `->get()` without eager loads in hot paths.
6. **Commands:** Do operator commands support dry-run before execute (like `RepairAuditsCommand`)?
7. **Config:** Is behaviour hardcoded that should use `config('scanner.*')`?
8. **Tests:** Are subsystem tests present? Any failing tests in this subsystem's test files?
9. **Duplication:** Does logic duplicate something in `Support/` or another service?

---

### Task 1: Scaffold backlog and run Phase 1 inventory

**Files:**
- Create: `docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md`

- [ ] **Step 1: Create backlog scaffold**

Create `docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md`:

```markdown
# Backend Refactor Backlog

**Date:** 2026-06-05
**Baseline:** Laravel 13.8
**Spec:** [2026-06-05-lean-audit-refactor-design.md](../specs/2026-06-05-lean-audit-refactor-design.md)

## Executive summary

_TBD after Task 14_

## Keep (positive patterns)

_TBD after Task 14_

## Spec gap register

_TBD after Task 12_

## Findings by subsystem

### S1 — Search & prospecting

### S2 — Niche scanning

### S3 — Audit pipeline

### S4 — Scoring & enrichment

### S5 — Reports & public surfaces

### S6 — Outreach

### S7 — OAuth & MCP

### S8 — Booking & calendar

### S9 — Operator settings & data hygiene

### S10 — Shared infrastructure

## Cross-cutting findings (S10)

## Frontend light pass (F1)

## Recommended PR schedule

## Appendix: automated signal sheet
```

- [ ] **Step 2: Run test baseline**

```bash
cd /Users/rosstweedie/Sites/nthdesigns-scanner
php artisan test 2>&1 | tee /tmp/audit-test-baseline.txt
tail -20 /tmp/audit-test-baseline.txt
```

Record in Appendix: total tests, passed, failed, skipped, risky. List each failure with test class and error message.

- [ ] **Step 3: Run Pint dry-run**

```bash
./vendor/bin/pint --test 2>&1 | tee /tmp/audit-pint-baseline.txt
echo "Pint exit: $?"
```

Record: files needing format fixes (count + list if ≤10).

- [ ] **Step 4: Collect file size signals**

```bash
wc -l app/Services/*.php app/Http/Controllers/*.php app/Jobs/*.php | sort -rn | head -20
find app -name "*.php" | wc -l
find resources/js -name "*.jsx" -o -name "*.js" | wc -l
find tests -name "*.php" | wc -l
```

Record all files >200 lines in Appendix.

- [ ] **Step 5: Form Request coverage gap**

```bash
ls app/Http/Requests/**/*.php 2>/dev/null
rg -l "function (store|update)\(" app/Http/Controllers --glob "*.php"
rg "validate\(" app/Http/Controllers --glob "*.php" -n
```

Record: count of Form Requests vs controllers with inline validation. List controllers using `$request->validate()` without a Form Request.

- [ ] **Step 6: Model casts migration status**

```bash
rg "protected \\\$casts" app/Models -l
rg "function casts\(" app/Models -l
```

Record: which models still use `$casts` property vs `casts()` method.

- [ ] **Step 7: Support vs Queries overlap**

```bash
ls app/Support/
ls app/Queries/
```

Record: responsibilities of each directory; flag classes that could move between them.

- [ ] **Step 8: Commit scaffold + appendix**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs: scaffold backend refactor audit backlog with signal sheet"
```

---

### Task 2: Review S1 — Search & prospecting

**Files to read:**
- `app/Http/Controllers/SearchController.php`
- `app/Jobs/ScrapeProspectsJob.php`
- `app/Jobs/DirectUrlScanJob.php`
- `app/Services/GooglePlacesService.php`
- `app/Services/BraveSearchService.php`
- `app/Services/WebsiteDiscoveryService.php`
- `app/Services/SearchStatusService.php`
- `app/Console/Commands/BackfillWebsitesCommand.php`
- `tests/Feature/DirectUrlScanJobTest.php`
- `tests/Feature/SearchControllerTest.php` (if exists)

**Specs:** `docs/superpowers/specs/2026-06-04-website-discovery-design.md`, `docs/superpowers/specs/2026-05-28-single-site-url-audit-design.md`

- [ ] **Step 1: Apply subsystem checklist to S1 files**

Read each file. Note line counts for methods >40 lines.

- [ ] **Step 2: Confirm DirectUrlScanJob test drift**

```bash
rg "function handle\(" app/Jobs/DirectUrlScanJob.php -A 3
rg "->handle\(" tests/Feature/DirectUrlScanJobTest.php -n
php artisan test --filter=DirectUrlScanJobTest 2>&1
```

If tests fail on argument count: create finding **REF-S1-01** as P1 (test drift + DI mismatch). Scores: M=2 R=1 L=3 O=3 → 9 (P2) or higher if blocking CI.

- [ ] **Step 3: Check WebsiteDiscoveryService size and boundaries**

`WebsiteDiscoveryService.php` is ~316 lines. Assess whether Brave/Google/URL normalization should be split. If yes: finding with M=3, effort ~4–6h.

- [ ] **Step 4: Check SearchController validation**

Does `store` / `storeDirectUrl` use `StoreDirectUrlSearchRequest` only for direct URL, or is main `store` still inline? Flag missing Form Requests.

- [ ] **Step 5: Append S1 findings to backlog**

Add all finding cards under `### S1 — Search & prospecting`.

- [ ] **Step 6: Commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S1 search and prospecting findings"
```

---

### Task 3: Review S2 — Niche scanning

**Files to read:**
- `app/Http/Controllers/NicheScanController.php`
- `app/Http/Controllers/NicheScanSampleController.php`
- `app/Http/Controllers/NicheIgnoreController.php`
- `app/Jobs/ScanNicheJob.php`
- `app/Services/NicheExclusionService.php`
- `app/Services/NicheSampleCollector.php`
- `app/Console/Commands/ScanNichesCommand.php`
- `app/Console/Commands/NichesBootstrapCommand.php`
- `app/Console/Commands/SyncNicheExclusionsCommand.php`
- `app/Console/Commands/RecalculateNicheScoresCommand.php`
- `tests/Feature/ScanNichesCommandTest.php`

**Specs:** `docs/superpowers/specs/2026-05-27-niches-bootstrap-design.md`, `docs/superpowers/specs/2026-05-28-niche-opportunity-confidence-design.md`

- [ ] **Step 1: Apply checklist**

- [ ] **Step 2: Check ScanNichesCommandTest failure**

```bash
php artisan test --filter=ScanNichesCommandTest 2>&1
```

The baseline shows `test_skips_already_complete_scans_without_force` expects output `"Skipped 1"`. If failing: REF-S2-01 P1/P2 spec or code drift.

- [ ] **Step 3: Assess ScanNicheJob complexity**

`ScanNicheJob.php` ~124 lines — check idempotency and error recording.

- [ ] **Step 4: Append S2 findings and commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S2 niche scanning findings"
```

---

### Task 4: Review S3 — Audit pipeline

**Files to read:**
- `app/Jobs/AuditSiteJob.php`
- `app/Jobs/CaptureScreenshotJob.php`
- `app/Services/ProspectAuditService.php`
- `app/Services/AuditRunnerService.php`
- `app/Services/AuditErrorRecorder.php`
- `app/Services/ScreenshotCaptureService.php`
- `app/Console/Commands/RepairAuditsCommand.php`
- `app/Console/Commands/BackfillAuditsCommand.php`
- `app/Support/RepairAuditScope.php`
- `app/Support/StuckSiteAuditQuery.php`
- `app/Support/FailedSiteAuditQuery.php`
- `app/Support/FailedScreenshotQuery.php`
- `app/Support/AuditingQueuePresence.php`
- `app/Support/StaleAuditJobCloser.php`
- `tests/Feature/RepairAuditsCommandTest.php`

**Specs:** `docs/superpowers/specs/2026-06-02-audit-repair-design.md`, `docs/superpowers/specs/2026-05-27-audit-backfill-design.md`, `docs/superpowers/specs/2026-06-02-audit-job-error-details-design.md`

- [ ] **Step 1: Document positive patterns for Keep section**

Note (do not score as findings):
- `RepairAuditsCommand` dry-run/execute pattern
- `AuditingQueuePresence` connection-aware detection
- `ProspectAuditService::repairSiteAudit()` distinct path

- [ ] **Step 2: Apply checklist — focus on job idempotency and stale job handling**

```bash
rg "failed\(|release\(|delete\(" app/Jobs/AuditSiteJob.php app/Jobs/CaptureScreenshotJob.php -n
```

- [ ] **Step 3: Check RepairAuditsCommand size**

If command mixes selection + orchestration + output formatting: REF-S3 finding to thin it (use as reference pattern, not anti-pattern).

- [ ] **Step 4: Append S3 findings and commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S3 audit pipeline findings"
```

---

### Task 5: Review S4 — Scoring & enrichment

**Files to read:**
- `app/Jobs/ScorePlaceJob.php`
- `app/Jobs/CombineScoresJob.php`
- `app/Jobs/DetectCmsJob.php`
- `app/Services/GbpScoringService.php`
- `app/Services/A11yScoringService.php`
- `app/Services/ProspectEnrichmentService.php`
- `app/Services/CmsDetectionRunnerService.php`
- `app/Services/BenchmarkNormalizer.php`
- `app/Console/Commands/BackfillCmsCommand.php`

**Specs:** `docs/superpowers/specs/2026-05-27-gbp-scoring-flags-design.md`, `docs/superpowers/specs/2026-06-01-cms-detection-design.md`, `docs/superpowers/specs/2026-05-28-prospect-enrichment-design.md`

- [ ] **Step 1: Apply checklist**

- [ ] **Step 2: Assess GbpScoringService (~292 lines)**

Flag decomposition if scoring rules, flag detection, and persistence are tangled.

- [ ] **Step 3: Check job chain coupling**

How do `ScorePlaceJob` → `CombineScoresJob` → `AuditSiteJob` chain? Flag tight coupling or missing failure propagation.

- [ ] **Step 4: Append S4 findings and commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S4 scoring and enrichment findings"
```

---

### Task 6: Review S5 — Reports & public surfaces

**Files to read:**
- `app/Services/ReportBuilderService.php`
- `app/Http/Controllers/PublicReportController.php`
- `app/Http/Controllers/ReportDashboardController.php`
- `app/Jobs/GenerateProspectReportJob.php`
- `app/Services/ScreenshotStorageService.php`
- `resources/js/Pages/Report/Public.jsx`
- `resources/js/Pages/Reports/Index.jsx`

**Specs:** `docs/superpowers/specs/2026-05-27-prospect-site-audit-detail-design.md`, `docs/superpowers/specs/2026-05-29-prospect-page-speed-detail-design.md`

- [ ] **Step 1: Assess ReportBuilderService (457 lines)**

This is the highest-priority decomposition candidate. Read full file; identify natural extraction units (sections, benchmarks, screenshots). Create REF-S5-01 with M=3, L=2, O=1 → score accordingly.

- [ ] **Step 2: Check public report controller prop shaping**

Does `PublicReportController` pass raw models or shaped arrays? Flag prop bloat for F1 cross-reference.

- [ ] **Step 3: Apply checklist**

- [ ] **Step 4: Append S5 findings and commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S5 reports and public surfaces findings"
```

---

### Task 7: Review S6 — Outreach

**Files to read:**
- `app/Http/Controllers/OutreachController.php`
- `app/Http/Controllers/OutreachEmailController.php`
- `app/Jobs/GenerateOutreachEmailJob.php`
- `app/Services/OutreachEmailGeneratorService.php`
- `resources/js/Pages/Outreach/Index.jsx`

**Specs:** `docs/superpowers/specs/2026-05-29-outreach-queue-clickable-prospects-design.md`

- [ ] **Step 1: Apply checklist**

`OutreachController.php` ~155 lines — check for missing Form Requests on queue mutations.

- [ ] **Step 2: Check authorization on outreach actions**

```bash
rg "authorize|Policy" app/Http/Controllers/OutreachController.php app/Policies/ -n
```

Only 4 policies exist: `ProspectPolicy`, `SearchPolicy`, `OutreachSelectionPolicy`, `UserMcpKeyPolicy`. Flag controllers without policy coverage.

- [ ] **Step 3: Append S6 findings and commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S6 outreach findings"
```

---

### Task 8: Review S7 — OAuth & MCP

**Files to read:**
- `app/Http/Controllers/OAuthServerController.php`
- `app/Http/Controllers/OAuthWellKnownController.php`
- `app/Http/Controllers/Api/McpController.php`
- `app/Services/Mcp/McpSearchService.php`
- `app/Services/ProgressFlowService.php`
- `app/Services/OAuthMcpJwtService.php`
- `app/Services/OAuthMcpRefreshTokenService.php`
- `app/Http/Middleware/AuthenticateOAuthBearer.php`
- `docs/mcp-integration-guide.md`

**Specs:** `docs/superpowers/specs/2026-06-01-mcp-scan-monitoring-design.md`, `docs/superpowers/specs/2026-06-02-mcp-progress-flow-design.md`

- [ ] **Step 1: Assess OAuthServerController (288 lines)**

Flag validation/token grant logic that should move to dedicated action classes or services.

- [ ] **Step 2: Check MCP progress flow test coverage**

```bash
rg -l "ProgressFlow|McpSearch" tests/ --glob "*.php"
php artisan test --filter=Mcp 2>&1 | tail -15
```

- [ ] **Step 3: Apply checklist — focus on security and observability**

- [ ] **Step 4: Append S7 findings and commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S7 OAuth and MCP findings"
```

---

### Task 9: Review S8 — Booking & calendar

**Files to read:**
- `app/Http/Controllers/AgencyBookingSettingsController.php`
- `app/Http/Controllers/PublicReportBookingController.php`
- `app/Http/Controllers/PublicBookingController.php`
- `app/Services/Calendar/BookingAvailabilityService.php`
- `app/Services/Calendar/FastmailCalDavProvider.php`
- `app/Services/Calendar/FastmailCalDavClient.php`
- `app/Services/ReportBookingService.php`
- `app/Providers/BookingServiceProvider.php`
- `resources/js/Pages/Book/Index.jsx`
- `resources/js/Components/AgencyBookingSettingsCard.jsx`

**Specs:** `docs/superpowers/specs/2026-06-04-report-booking-fastmail-design.md`

- [ ] **Step 1: Apply checklist**

Recent feature (calendar bookings, commit `b290b98`) — verify spec alignment.

- [ ] **Step 2: Check CalDAV error handling and operator visibility**

Do failures surface to the user or log clearly?

- [ ] **Step 3: Append S8 findings and commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S8 booking and calendar findings"
```

---

### Task 10: Review S9 — Operator settings & data hygiene

**Files to read:**
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/Settings/McpKeyController.php`
- `app/Http/Controllers/Settings/ConnectedAppsController.php`
- `app/Http/Controllers/IgnoredProspectController.php`
- `app/Http/Controllers/ProspectIgnoreController.php`
- `app/Services/UserSettingsService.php`
- `app/Services/ProspectExclusionService.php`
- `app/Console/Commands/PurgeExpiredProspectData.php`
- `resources/js/Pages/Settings/Index.jsx`

**Specs:** `docs/superpowers/specs/2026-05-29-niche-settings-maintenance-design.md`

- [ ] **Step 1: Apply checklist**

- [ ] **Step 2: Check HandleInertiaRequests shared props**

```bash
wc -l app/Http/Middleware/HandleInertiaRequests.php
rg "share\(" app/Http/Middleware/HandleInertiaRequests.php -n
```

Flag if middleware has grown into a god-object for prop sharing.

- [ ] **Step 3: Append S9 findings and commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S9 operator settings findings"
```

---

### Task 11: Review S10 — Shared infrastructure

**Files to read:**
- All of `app/Support/` (21 files)
- `app/Queries/ProspectListQuery.php`
- `app/Policies/*.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Resources/ProspectListResource.php`
- `config/scanner.php`
- `app/Providers/*.php`

- [ ] **Step 1: Cross-cutting Form Request gap**

Synthesize Task 1 Step 5 data into one finding: **REF-S10-01** — add Form Requests for mutating controllers. Priority P2 unless a controller handles sensitive data without validation (then P1).

- [ ] **Step 2: Cross-cutting casts() migration**

Synthesize Task 1 Step 6 into **REF-S10-02** — migrate 8 models from `$casts` to `casts()` method. P2.

- [ ] **Step 3: Policy coverage audit**

```bash
rg "Route::(post|patch|put|delete)" routes/web.php routes/api.php -n
ls app/Policies/
```

List mutating routes without an obvious policy. Controllers to check: `ExportController`, `AgencyBookingSettingsController`, `NicheIgnoreController`.

- [ ] **Step 4: Http/Resources usage**

Only `ProspectListResource` exists. Grep controllers for manual array mapping that could use API Resources:

```bash
rg "Inertia::render" app/Http/Controllers -l
rg "Resource" app/Http/Controllers -n
```

- [ ] **Step 5: Append S10 + cross-cutting findings and commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): S10 shared infrastructure findings"
```

---

### Task 12: Spec gap register

**Files:**
- All 25 specs in `docs/superpowers/specs/` (excluding this audit's own spec)
- All 20 plans in `docs/superpowers/plans/`

- [ ] **Step 1: Build spec inventory table**

For each spec file, record: filename, status (Implemented / Approved / other), key files named, spot-check result (match / drift / not checked).

Priority spot-checks (read spec decision table, verify in code):

| Spec | Key verification |
|------|------------------|
| `2026-06-02-audit-repair-design.md` | `RepairAuditsCommand`, `ProspectAuditService::repairSiteAudit()` exist |
| `2026-06-04-website-discovery-design.md` | `WebsiteDiscoveryService`, `BackfillWebsitesCommand` exist |
| `2026-06-04-report-booking-fastmail-design.md` | CalDAV provider, `ReportBookingService` exist |
| `2026-05-27-ui-component-refactor-design.md` | `Components/ui/` used; Breeze components gone |
| `2026-06-02-mcp-progress-flow-design.md` | `ProgressFlowService` exists and is wired |

- [ ] **Step 2: Scan plans for unchecked tasks**

```bash
rg "^- \[ \]" docs/superpowers/plans/ -n
```

For each unchecked item: determine if implemented in code. If not: add to Spec gap register with severity (P1 if tests fail, P2 if feature incomplete, P3 if docs-only).

- [ ] **Step 3: Flag features without specs**

Recent commits without a matching spec:
- `b290b98 feat: Calendar bookings` → has spec `2026-06-04-report-booking-fastmail`
- `e992843 fix: tweaking the calendar settings page` → verify against booking spec

Any feature in `git log --oneline -20` without a spec → documentation debt (informational).

- [ ] **Step 4: Write Spec gap register section**

Replace `_TBD after Task 12_` with a table:

```markdown
| ID | Source | Gap | Severity | Notes |
|----|--------|-----|----------|-------|
| GAP-01 | tests/Feature/DirectUrlScanJobTest.php | Job handle() signature drift | P1 | 3 tests erroring |
```

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): spec and plan gap register"
```

---

### Task 13: Frontend light pass (F1)

**Files to read:**
- `resources/js/Pages/Search/Index.jsx`
- `resources/js/Pages/Search/Show.jsx`
- `resources/js/Pages/Prospect/Show.jsx`
- `resources/js/Pages/Niches/Index.jsx`
- `resources/js/Pages/Outreach/Index.jsx`
- `resources/js/Pages/Settings/Index.jsx`
- `resources/js/Components/AgencyBookingSettingsCard.jsx`

- [ ] **Step 1: Check ui/ primitive usage**

```bash
rg "from '@/Components/ui" resources/js/Pages --glob "*.jsx" -l
rg 'className="btn |className="card |style=\{\{' resources/js/Pages --glob "*.jsx" -n
```

Pages still using raw CSS classes instead of `Components/ui/`: flag as F1 findings (max P2).

- [ ] **Step 2: Check duplicated poll/fetch logic**

```bash
rg "setInterval|usePoll|router\.reload" resources/js/Pages --glob "*.jsx" -n
```

Flag duplicated polling patterns between Search and Niches pages.

- [ ] **Step 3: Check loading and error states**

For each page with async actions (scan trigger, outreach generate, booking): is there a loading indicator and error display?

- [ ] **Step 4: Append F1 findings (cap at P2 unless operator failure)**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): frontend light pass findings"
```

---

### Task 14: Synthesis — executive summary, Keep, PR schedule

**Files:**
- Modify: `docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md`

- [ ] **Step 1: Write Keep section**

List positive patterns identified in Tasks 3–4 (minimum):
- `RepairAuditsCommand` dry-run/execute
- `Support/*Query` classes for repair selection
- `ProspectAuditService::repairSiteAudit()` separate path
- `Components/ui/` design system (implemented)

- [ ] **Step 2: Dedupe cross-cutting findings**

Ensure `casts()` and Form Request findings appear once in S10, referenced (not repeated) in subsystem sections.

- [ ] **Step 3: Rank all findings**

Sort by tier (P1 → P4). Within tier: O score desc, then M score desc. Assign IDs if any gaps.

- [ ] **Step 4: Build PR schedule (8–15 slices)**

Group findings into medium PRs. Example structure (adjust based on actual findings):

| PR | Subsystem | Title | Priority | Depends on |
|----|-----------|-------|----------|------------|
| PR-01 | S1 | Fix DirectUrlScanJob test drift and constructor injection | P1 | — |
| PR-02 | S2 | Fix ScanNichesCommand output assertion drift | P1 | — |
| PR-03 | S10 | Migrate models to casts() method | P2 | — |
| PR-04 | S10 | Add Form Requests for Search, Outreach, Settings mutations | P2 | — |
| PR-05 | S5 | Decompose ReportBuilderService into section builders | P2 | — |
| PR-06 | S7 | Extract OAuth token grant actions from OAuthServerController | P2 | — |
| PR-07 | S1 | Split WebsiteDiscoveryService by discovery source | P2 | — |
| PR-08 | S4 | Extract GbpScoringService flag detection | P3 | — |
| PR-09 | S10 | Expand policy coverage for mutating routes | P2 | PR-04 |
| PR-10 | F1 | Consolidate search/niche polling into shared hook | P3 | — |

Target 8–15 rows. Each PR should be completable in one review session (~2–8 hours).

- [ ] **Step 5: Write executive summary**

Include:
- Total findings by priority (P1/P2/P3/P4 counts)
- Top 3 recommendations (highest-scored P1/P2 items)
- Test health snapshot from Task 1
- Spec drift summary (gap count by severity)
- Estimated total refactor effort (sum of PR efforts)

- [ ] **Step 6: Remove all _TBD_ placeholders**

Search backlog for `_TBD` — must be zero before commit.

```bash
rg "_TBD" docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
```

Expected: no matches.

- [ ] **Step 7: Commit**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs: complete backend refactor audit backlog with PR schedule"
```

---

### Task 15: Final self-review

- [ ] **Step 1: Spec coverage check**

Verify the backlog contains:
- [ ] Executive summary
- [ ] Keep section (≥3 items)
- [ ] Spec gap register (≥1 entry from test failures)
- [ ] Findings for all subsystems S1–S10 (≥1 finding each OR explicit "no findings" note)
- [ ] F1 section
- [ ] PR schedule (8–15 rows)
- [ ] Appendix with test/Pint/file-size signals

- [ ] **Step 2: Scoring consistency**

Pick 3 random findings; manually verify M+R+L+O = Total and tier matches spec table.

- [ ] **Step 3: No application code changed**

```bash
git diff main --stat -- app/ resources/ routes/ tests/ config/
```

Expected: no changes outside `docs/superpowers/audits/`.

- [ ] **Step 4: Final commit if self-review fixes needed**

```bash
git add docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md
git commit -m "docs(audit): self-review fixes for refactor backlog"
```

---

## Expected outcomes

After completing all tasks:

1. `docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md` — complete, no placeholders
2. 8–15 medium PR slices ordered by priority
3. At least 2 P1 items from known test drift (DirectUrlScanJob, ScanNichesCommand)
4. Keep section documenting repair-audit patterns
5. No changes to application source code
