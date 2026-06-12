# Site Unreachable Preflight Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fast-fail dead URLs via HTTP preflight before Fly audit work; set `audit_status: failed` with "Site unreachable" label; preserve GBP combined scores.

**Architecture:** `WebsiteReachabilityService` classifies permanent vs transient errors; `SiteScanPreflightGate` runs before `AuditRunnerService` in `AuditSiteJob`; failure recorded via `SiteScanFailureRecorder`; screenshot path hardened for page-load errors.

**Tech Stack:** Laravel 11, Http client, PHPUnit, React/Inertia UI

**Spec:** `docs/superpowers/specs/2026-06-12-site-unreachable-preflight-design.md`

---

### Task 1: Reachability core

**Files:**
- Create: `app/Support/ReachabilityResult.php`
- Create: `app/Support/ProspectSiteScan.php`
- Create: `app/Services/WebsiteReachabilityService.php`
- Create: `tests/Unit/WebsiteReachabilityServiceTest.php`
- Modify: `config/scanner.php`

### Task 2: Gate + recorder

**Files:**
- Create: `app/Services/SiteScanFailureRecorder.php`
- Create: `app/Services/SiteScanPreflightGate.php`
- Create: `tests/Unit/SiteScanPreflightGateTest.php`

### Task 3: AuditSiteJob integration

**Files:**
- Modify: `app/Jobs/AuditSiteJob.php`
- Modify: `tests/Feature/AuditSiteJobTest.php` (disable preflight in existing tests)
- Create: `tests/Feature/SiteScanPreflightJobTest.php`

### Task 4: Screenshot hardening

**Files:**
- Create: `app/Exceptions/ScreenshotPageLoadException.php`
- Modify: `app/Services/Browser/BrowserScreenshotGateway.php`
- Modify: `app/Jobs/CaptureScreenshotJob.php`
- Modify: `tests/Feature/CaptureScreenshotJobTest.php`
- Modify: `tests/Unit/BrowserServiceClientTest.php`

### Task 5: API + UI

**Files:**
- Modify: `app/Http/Resources/SearchProspectResource.php`
- Modify: `app/Http/Resources/ProspectShowResource.php`
- Modify: `app/Services/ProgressFlowService.php`
- Modify: `resources/js/Pages/Search/Show.jsx`
- Modify: `resources/js/Pages/Prospect/Show.jsx`
- Create: `tests/Unit/SearchProspectResourceTest.php` (or extend existing)

### Task 6: Verification

Run: `php artisan test --filter='WebsiteReachability|SiteScanPreflight|CaptureScreenshot|BrowserServiceClient|AuditSiteJob|SearchProspect|ProgressFlow'`
