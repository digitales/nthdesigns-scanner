# Production Lighthouse Performance Scores Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Install Lighthouse on the Fly.io browser service so production audits populate `performance_score`, then backfill existing prospects missing Lighthouse data.

**Architecture:** Add `lighthouse` to `scripts/package.json`; configure `LIGHTHOUSE_BINARY` (and auto-detect `CHROME_PATH`) on Fly; harden `audit.js` Lighthouse flags for container runtime; update deployment docs; deploy Fly; run existing `scanner:backfill-audits`. No Laravel PHP changes.

**Tech Stack:** Node 20 (Playwright Noble image), Lighthouse 12, Fly.io, existing `scripts/audit.js` + `scanner:backfill-audits`.

**Spec:** `docs/superpowers/specs/2026-05-28-production-lighthouse-performance-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `scripts/package.json` | Add `lighthouse` npm dependency |
| `scripts/package-lock.json` | Lockfile updated by `npm ci` |
| `scripts/audit.js` | Container-safe Lighthouse chrome flags |
| `scripts/browser-service/fly.toml` | `LIGHTHOUSE_BINARY` env for Fly runtime |
| `scripts/browser-service/start.sh` | Auto-export `CHROME_PATH` from Playwright image when unset |
| `docs/deployment/laravel-cloud.md` | Production Lighthouse + backfill runbook |
| `docs/superpowers/specs/2026-05-28-production-lighthouse-performance-design.md` | Approved design (reference only) |

---

### Task 1: Add Lighthouse dependency

**Files:**
- Modify: `scripts/package.json`
- Modify: `scripts/package-lock.json` (generated)

- [ ] **Step 1: Add lighthouse to package.json**

In `scripts/package.json`, add to `dependencies`:

```json
"lighthouse": "^12.6.1"
```

- [ ] **Step 2: Install and verify binary exists**

Run:

```bash
cd scripts && npm ci
test -x node_modules/.bin/lighthouse && echo "lighthouse OK"
```

Expected: `lighthouse OK`

- [ ] **Step 3: Commit**

```bash
git add scripts/package.json scripts/package-lock.json
git commit -m "chore: add lighthouse dependency for production audits"
```

---

### Task 2: Harden Lighthouse for Fly container

**Files:**
- Modify: `scripts/audit.js:35-68`
- Modify: `scripts/browser-service/start.sh`

- [ ] **Step 1: Update `runLighthouse` chrome flags in audit.js**

Replace the Lighthouse `execFileSync` args array in `scripts/audit.js`:

```javascript
function runLighthouse(targetUrl) {
    if (!existsSync(lighthouseBinary) && lighthouseBinary === 'lighthouse') {
        try {
            execFileSync('which', ['lighthouse'], { stdio: 'pipe' });
        } catch {
            return null;
        }
    }

    const chromeFlags = [
        '--headless',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
    ].join(' ');

    try {
        const output = execFileSync(
            lighthouseBinary,
            [
                targetUrl,
                '--quiet',
                `--chrome-flags=${chromeFlags}`,
                '--only-categories=performance,accessibility,seo',
                '--output=json',
            ],
            {
                encoding: 'utf8',
                timeout: 90000,
                maxBuffer: 10 * 1024 * 1024,
                env: process.env,
            },
        );

        const report = JSON.parse(output);
        const categories = report.categories ?? {};

        return {
            performance: Math.round((categories.performance?.score ?? 0) * 100),
            accessibility: Math.round((categories.accessibility?.score ?? 0) * 100),
            seo: Math.round((categories.seo?.score ?? 0) * 100),
        };
    } catch {
        return null;
    }
}
```

Changes vs current: container chrome flags, explicit `env: process.env` (for `CHROME_PATH`), timeout 90s.

- [ ] **Step 2: Auto-detect Chrome path in start.sh**

Add before `exec node` in `scripts/browser-service/start.sh`:

```sh
if [ -z "${CHROME_PATH:-}" ]; then
    CHROME_PATH="$(find /ms-playwright -name chrome -type f 2>/dev/null | head -1)"
    if [ -n "$CHROME_PATH" ]; then
        export CHROME_PATH
        echo "[browser-service] CHROME_PATH=$CHROME_PATH"
    fi
fi
```

- [ ] **Step 3: Local smoke test**

Run (requires local Chrome/Chromium for Lighthouse):

```bash
cd scripts
LIGHTHOUSE_BINARY=./node_modules/.bin/lighthouse node audit.js https://example.com /tmp/nth-audit-test | node -e "
  const d = JSON.parse(require('fs').readFileSync(0,'utf8'));
  if (!d.lighthouse || typeof d.lighthouse.performance !== 'number') {
    console.error('FAIL: lighthouse missing', d.lighthouse);
    process.exit(1);
  }
  console.log('PASS: performance =', d.lighthouse.performance);
"
```

Expected: `PASS: performance = <number>`

If local Lighthouse fails (no Chrome), skip with note — Fly smoke test in Task 4 is authoritative.

- [ ] **Step 4: Commit**

```bash
git add scripts/audit.js scripts/browser-service/start.sh
git commit -m "fix: run lighthouse with container-safe flags on fly"
```

---

### Task 3: Configure Fly environment

**Files:**
- Modify: `scripts/browser-service/fly.toml:8-11`

- [ ] **Step 1: Add LIGHTHOUSE_BINARY to fly.toml**

In `scripts/browser-service/fly.toml`, extend `[env]`:

```toml
[env]
  PORT = '8080'
  NODE_ENV = 'production'
  PLAYWRIGHT_BROWSERS_PATH = '/ms-playwright'
  LIGHTHOUSE_BINARY = '/app/scripts/node_modules/.bin/lighthouse'
```

Do **not** hardcode `CHROME_PATH` here — `start.sh` discovers it at runtime.

- [ ] **Step 2: Commit**

```bash
git add scripts/browser-service/fly.toml
git commit -m "chore: point fly browser service at lighthouse binary"
```

---

### Task 4: Update deployment documentation

**Files:**
- Modify: `docs/deployment/laravel-cloud.md`

- [ ] **Step 1: Replace optional-Lighthouse wording (local Playwright section ~line 539)**

Change:

```markdown
Lighthouse is optional — audits still run without it; performance/SEO scores will be null.
```

To:

```markdown
Lighthouse is optional for **local** Playwright audits — install the npm package and set `LIGHTHOUSE_BINARY` if you want performance scores in dev. On **production Fly**, Lighthouse is installed by default (see §10).
```

- [ ] **Step 2: Update §7E Skip Lighthouse (~line 713)**

Replace section **E. Skip Lighthouse** with:

```markdown
### E. Lighthouse on Fly (production)

The Fly browser service installs `lighthouse` via `scripts/package.json` and sets `LIGHTHOUSE_BINARY` in `fly.toml`. `start.sh` auto-exports `CHROME_PATH` from the Playwright image.

After deploy, verify:

```bash
curl -s -H "Authorization: Bearer $AUDIT_SERVICE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com"}' \
  "$AUDIT_SERVICE_URL/audit" | jq '.lighthouse'
```

Expected: `{ "performance": <1-100>, "accessibility": <n>, "seo": <n> }` — not `null`.

If `lighthouse` is null, check `fly logs --app nth-scanner-browser` for `[browser-service] CHROME_PATH=…` and see [Fly troubleshooting](#fly-troubleshooting).

**Backfill** prospects audited before Lighthouse was enabled:

```bash
php artisan scanner:backfill-audits              # dry-run
php artisan scanner:backfill-audits --execute --delay=5
```

See `docs/superpowers/specs/2026-05-28-production-lighthouse-performance-design.md`.
```

- [ ] **Step 3: Update Fly container design table (~line 991)**

Add row:

| `LIGHTHOUSE_BINARY` | `/app/scripts/node_modules/.bin/lighthouse` | Lighthouse CLI for performance/SEO scores |

- [ ] **Step 4: Add Lighthouse smoke test to §10 step 3 (~after screenshot curl)**

```bash
curl -s -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com"}' \
  https://nth-scanner-browser.fly.dev/audit | jq '.lighthouse'
```

Expected: non-null object with numeric `performance`.

- [ ] **Step 5: Add Fly troubleshooting entry**

Under `### Fly troubleshooting`, add:

```markdown
#### Symptom: audit completes but `lighthouse` is null

**Cause:** Lighthouse could not launch Chrome, or the URL timed out.

**Fix:**

1. `fly logs --app nth-scanner-browser` — confirm `[browser-service] CHROME_PATH=…` at startup.
2. `fly ssh console --app nth-scanner-browser` — run:
   ```bash
   /app/scripts/node_modules/.bin/lighthouse https://example.com --quiet --output=json --chrome-flags="--headless --no-sandbox" | head -c 200
   ```
3. If that fails, set an explicit secret:
   ```bash
   fly secrets set CHROME_PATH="$(find /ms-playwright -name chrome -type f | head -1)" \
     --config scripts/browser-service/fly.toml
   ```
4. Redeploy and re-test `/audit`.
```

- [ ] **Step 6: Commit**

```bash
git add docs/deployment/laravel-cloud.md
git commit -m "docs: document production lighthouse on fly and backfill"
```

---

### Task 5: Deploy Fly browser service

**Files:** none (operational)

- [ ] **Step 1: Deploy from repository root**

```bash
fly deploy . --config scripts/browser-service/fly.toml
```

Expected: deploy succeeds; health check passes within grace period.

- [ ] **Step 2: Verify health**

```bash
curl -s https://nth-scanner-browser.fly.dev/health
```

Expected: `{"ok":true}`

- [ ] **Step 3: Smoke test Lighthouse via /audit**

```bash
curl -s -H "Authorization: Bearer $AUDIT_SERVICE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com"}' \
  "$AUDIT_SERVICE_URL/audit" | jq '.lighthouse'
```

Expected: JSON object with `"performance": <number>` — **not** `null`.

If `null`: follow troubleshooting in Task 4 / `fly logs`; fix `CHROME_PATH` or flags; redeploy; re-run this step.

- [ ] **Step 4: Smoke test one real prospect URL**

Pick a URL from production (e.g. `https://goodfabric.co.uk/`) and repeat Step 3.

Expected: non-null `lighthouse.performance`.

---

### Task 6: Backfill production prospects

**Files:** none (operational — uses existing `scanner:backfill-audits`)

- [ ] **Step 1: Dry-run on Laravel Cloud**

On a Laravel Cloud app instance (or local against production DB if configured):

```bash
php artisan scanner:backfill-audits
```

Expected: table listing prospects with reasons like `missing raw_lighthouse_payload` or `missing lighthouse performance`. Good Fabric (or equivalent) should appear.

- [ ] **Step 2: Execute backfill with stagger**

```bash
php artisan scanner:backfill-audits --execute --delay=5
```

Expected: `Dispatched N audit job(s).`

Monitor auditing queue until jobs drain. Increase `--delay` or use `--limit=50` batches if Fly shows memory pressure.

- [ ] **Step 3: Verify in database / UI**

Spot-check 3 prospects:

```bash
php artisan tinker --execute="
\App\Models\Prospect::whereIn('id', [ID1, ID2, ID3])
  ->get(['id','business_name','performance_score','raw_lighthouse_payload'])
  ->each(fn(\$p) => dump(\$p->only('id','business_name','performance_score')));
"
```

Expected: `performance_score > 0` and `raw_lighthouse_payload` populated.

In search UI: performance column shows coloured number instead of **—**.

- [ ] **Step 4: Confirm existing tests still pass**

```bash
php artisan test --filter=IncompleteAuditQueryTest
php artisan test --filter=BackfillAuditsCommandTest
```

Expected: all tests PASS (no PHP code changed, but confirms backfill tooling intact).

---

## Rollback

If Fly deploy causes audit failures:

1. Revert `scripts/package.json`, `audit.js`, `start.sh`, `fly.toml` commits
2. `fly deploy . --config scripts/browser-service/fly.toml`
3. Audits resume axe-only behaviour (performance **—** again, but no outage)

Do **not** run backfill until `/audit` smoke test passes.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Add lighthouse to package.json | Task 1 |
| LIGHTHOUSE_BINARY in fly.toml | Task 3 |
| CHROME_PATH if needed | Task 2 (start.sh auto-detect) + Task 5 troubleshooting |
| No Laravel code changes | ✓ |
| Update laravel-cloud.md | Task 4 |
| Fly deploy + smoke test | Task 5 |
| Backfill all incomplete prospects | Task 6 |
| Existing PHPUnit unchanged | Task 6 step 4 |
