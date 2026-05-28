# Production Lighthouse performance scores — Design Spec

**Date:** 2026-05-28  
**Status:** Approved  
**Scope:** Enable Lighthouse performance scores on the Fly.io browser service (production audit path) and backfill existing prospects missing performance data.

**Approach:** Install Lighthouse on Fly (Option A). Reuse existing `audit.js` pipeline, `AuditSiteJob` storage, and `scanner:backfill-audits` command. No Laravel application logic changes. No PageSpeed Insights API.

---

## Goal

Production prospects complete site audits (axe violations, reports) but show **—** in the performance column because Lighthouse never runs on the Fly browser service. Operators need real Lighthouse performance scores in the search UI, combined scoring, and reports — for new audits and for prospects already audited in production.

---

## Context

| Layer | Current behaviour |
|-------|-------------------|
| Production audits | Laravel Cloud → `POST {AUDIT_SERVICE_URL}/audit` → Fly runs `scripts/audit.js` |
| Lighthouse | Not installed on Fly; `runLighthouse()` returns `null` |
| Storage | `A11yScoringService::extractPerformanceScore()` stores `0` when Lighthouse absent |
| UI | `PerfScore` treats `0` as missing → displays **—** |
| Backfill | `scanner:backfill-audits` + `IncompleteAuditQuery` already select prospects with missing Lighthouse data |

**Confirmed constraints (from operator):**

- Issue is **production only** (Laravel Cloud + Fly), not local dev
- **Backfill all** existing incomplete prospects after infrastructure fix

---

## Decisions

| Topic | Decision |
|-------|----------|
| Data source | Lighthouse via existing `audit.js` (not PageSpeed Insights API) |
| Where to install | Fly browser service (`scripts/package.json` + `fly.toml` env) |
| Laravel code changes | None — pipeline already handles Lighthouse when present |
| UI changes | None |
| Backfill | Existing `scanner:backfill-audits --execute` after Fly redeploy |
| Chrome binary | Set `CHROME_PATH` on Fly only if smoke test returns `lighthouse: null` |
| Fallback (PSI API) | Out of scope |

---

## Root cause

The Fly Dockerfile runs `npm ci` for Playwright and axe-core only. `scripts/package.json` does not include `lighthouse`. At runtime, `audit.js` checks for the Lighthouse binary; when absent, it skips Lighthouse and returns `lighthouse: null`. Audits still complete with axe data, so prospects reach `audit_status = complete` and reports generate — but `performance_score` remains `0`.

---

## Infrastructure changes

### 1. Add Lighthouse dependency

**File:** `scripts/package.json`

Add `lighthouse` as a pinned dependency (e.g. `^12.0.0`, compatible with Node on the Playwright Noble image).

### 2. Configure Fly environment

**File:** `scripts/browser-service/fly.toml`

Add to `[env]`:

```env
LIGHTHOUSE_BINARY=/app/scripts/node_modules/.bin/lighthouse
```

If smoke test fails (Lighthouse cannot launch Chrome), add:

```env
CHROME_PATH=<path to Playwright Chromium under /ms-playwright>
```

Determine `CHROME_PATH` from a one-off Fly SSH session or deploy logs — do not hardcode without verification.

### 3. Dockerfile

**File:** `scripts/browser-service/Dockerfile`

No structural change. Existing `npm ci` step installs Lighthouse once added to `package.json`.

### 4. Documentation

**File:** `docs/deployment/laravel-cloud.md`

Update Fly / Lighthouse sections:

- Production Fly service **does** run Lighthouse when dependency and env are configured
- Remove or qualify statements that production performance scores will always be null
- Add deploy smoke-test step and backfill instructions (cross-link to this spec)

---

## Data flow (unchanged application code)

```
AuditSiteJob
  → BrowserServiceClient::fetchAudit()  POST /audit
    → Fly: audit.js (axe + lighthouse)
  → A11yScoringService::score() + extractPerformanceScore()
  → prospect update:
      a11y_score, a11y_flags, performance_score,
      raw_a11y_payload, raw_lighthouse_payload
  → CombineScoresJob (performance weakness in combined scans)
  → GenerateProspectReportJob
  → Search UI PerfScore shows numeric score
```

---

## Backfill procedure

After Fly redeploy and successful smoke test:

```bash
# 1. Dry run — list affected prospects (includes missing raw_lighthouse_payload)
php artisan scanner:backfill-audits

# 2. Execute — reset audit fields, re-queue with stagger
php artisan scanner:backfill-audits --execute --delay=5

# Optional: batch or single prospect
php artisan scanner:backfill-audits --execute --limit=50 --delay=5
php artisan scanner:backfill-audits --execute --prospect=ID --delay=0
```

**Per prospect, backfill:**

1. Sets `audit_status` → `pending`
2. Clears `raw_a11y_payload`, `raw_lighthouse_payload`, scores
3. Dispatches `AuditSiteJob` with delay between prospects
4. Full pipeline re-runs: audit → combine → report → screenshot

**Operator notes:**

- Prospects briefly show "Auditing site" during backfill
- Reports regenerate when audit completes
- Prospects in outreach queue remain selectable; scores update in place
- Use `--delay=5` (default) to avoid overloading the Fly VM during bulk backfill

---

## Error handling & operations

| Scenario | Behaviour |
|----------|-----------|
| Lighthouse fails for one URL | `audit.js` catches error, returns `lighthouse: null`; audit still completes with axe data; performance stays **—** for that row |
| Fly OOM / timeout | Check Fly metrics during backfill; increase VM memory or `--delay` if needed |
| Large backfill volume | Run in batches with `--limit`; monitor auditing queue on Laravel Cloud |
| Audit HTTP timeout | Default `AUDIT_TIMEOUT=180` should cover axe (~45s) + Lighthouse (~60s); increase only if smoke tests prove insufficient |
| `audit_driver = skip` | Prospects intentionally skipped — excluded from backfill selection |

---

## Rollout checklist

1. Merge infrastructure changes (`package.json`, `fly.toml`, docs)
2. `fly deploy . --config scripts/browser-service/fly.toml`
3. Smoke test:

   ```bash
   curl -s -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d '{"url":"https://example.com"}' \
     https://nth-scanner-browser.fly.dev/audit | jq '.lighthouse'
   ```

   Expected: `{ "performance": <1-100>, "accessibility": <n>, "seo": <n> }`

4. If `lighthouse` is null, diagnose Chrome path and redeploy with `CHROME_PATH`
5. On Laravel Cloud: dry-run then execute backfill
6. Verify performance column in search UI (e.g. Good Fabric) and combined scores for `combined` scans

---

## Testing

| Test | Acceptance |
|------|------------|
| Local audit script | `LIGHTHOUSE_BINARY=./node_modules/.bin/lighthouse node audit.js https://example.com /tmp/audit` → JSON includes `lighthouse.performance` |
| Fly smoke test | `POST /audit` returns non-null `lighthouse.performance` |
| Backfill dry-run | `scanner:backfill-audits` lists production incomplete prospects |
| Backfill execute | Spot-check 3+ prospects: `performance_score > 0`, `raw_lighthouse_payload` populated |
| Existing PHPUnit | `IncompleteAuditQueryTest`, `BackfillAuditsCommandTest` remain passing — no new tests required for infra-only change |

---

## Out of scope

- PageSpeed Insights API integration
- UI changes to distinguish "score 0" vs "not measured"
- Changing combined-score weighting
- Local dev Lighthouse setup (already works when binary is installed)
- Cloudflare-only audit path (no Lighthouse available)

---

## Related specs

- [2026-05-27-audit-backfill-design.md](./2026-05-27-audit-backfill-design.md) — backfill command and selection rules
- [docs/deployment/laravel-cloud.md](../../deployment/laravel-cloud.md) — Fly deploy and env reference
