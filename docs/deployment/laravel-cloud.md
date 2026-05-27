# Laravel Cloud deployment guide

Deploy checklist and audit fallback plan for the nthdesigns prospect scanner.

---

## Architecture on Cloud

```mermaid
flowchart LR
    subgraph app [App cluster]
        Web[Inertia / PHP]
    end

    subgraph worker [Worker cluster]
        QueueWorker["php artisan queue:work"]
        Scheduler[schedule:run]
    end

    subgraph data [Managed resources]
        PG[(Serverless Postgres)]
        R2[(Object Storage)]
    end

    subgraph external [External APIs]
        Places[Google Places]
        Claude[OpenRouter / Anthropic]
        CF[Cloudflare Browser Rendering]
    end

    Web --> PG
    QueueWorker --> PG
    QueueWorker --> R2
    QueueWorker --> Places
    QueueWorker --> Claude
    QueueWorker --> CF
    Scheduler --> PG
```

| Workload | Where it runs |
|----------|---------------|
| Web UI, auth, settings | App cluster |
| `scraping` queue (Places, scoring) | Worker cluster via `queue:work` (jobs in Postgres `jobs` table) |
| `auditing` queue (audit, screenshots, reports) | Worker cluster via `queue:work` |
| Daily `scanner:purge-expired` | Scheduler on App or Worker cluster |
| Report / violation images | Laravel Object Storage (R2) |

**Queue driver:** production uses `QUEUE_CONNECTION=database` (not Redis). Horizon is not used — it only supports the Redis driver. Run `php artisan queue:work` on the worker cluster instead.

---

## Pre-flight checklist

Before connecting the repo to Laravel Cloud:

- [ ] App uses PostgreSQL locally (Cloud does not support SQLite in production)
- [ ] `composer.json` / `composer.lock` committed
- [ ] `package-lock.json` committed (root + `scripts/`)
- [ ] Google Places API key with Places API (New) enabled
- [ ] OpenRouter API key (Anthropic models)
- [ ] Cloudflare account (optional now, needed for Browser Rendering fallback)
- [ ] First admin user seeder or registration flow ready
- [ ] `jobs` and `failed_jobs` tables migrated (`php artisan migrate` includes them)

---

## 1. Create resources (infrastructure canvas)

Attach these to your production environment:

| Resource | Purpose |
|----------|---------|
| **Serverless Postgres** | Primary database **and** queue (`jobs` table) |
| **Laravel Object Storage** | Report and violation screenshots |

**Optional:** Laravel Valkey / Redis — only if you switch cache or sessions off the database driver. Not required for queues with `QUEUE_CONNECTION=database`.

### Object storage

1. Create a bucket (type: Laravel Object Storage).
2. Attach it to the environment.
3. Cloud injects `AWS_*` vars automatically — do not override unless you know why.

Set in environment variables:

```env
REPORTS_DISK=s3
```

The settings page storage health check writes a temp file to this disk on save.

---

## 2. Compute clusters

### App cluster

- **Purpose:** HTTP traffic only (or HTTP + scheduler if you prefer)
- **PHP:** 8.4 (or 8.3)
- **Node:** 22 or 24 (used during build; may also be available at runtime)
- **Hibernation:** Off for production (hibernating envs stop workers and scheduler)
- **Scheduler:** Enable if not running scheduler on worker cluster
- **HTTP basic auth:** Optional on staging

Suggested size: 1 GB RAM minimum for a single-operator tool.

### Worker cluster (recommended)

- **Purpose:** `queue:work` + heavy audit jobs
- **Size:** 2 GB RAM minimum if running Playwright locally
- **Background processes:** see section 4

Keep auditing off the App cluster so 90–150s browser jobs do not compete with page loads. Use a single auditing worker process on smaller instances (one Playwright run at a time).

---

## 3. Build and deploy commands

Configure in **Settings → Deployments**.

### Build commands

Cloud runs npm for the Laravel frontend by default. Extend with audit script dependencies:

```bash
composer install --no-dev --optimize-autoloader

npm ci
npm run build

cd scripts
npm ci
PLAYWRIGHT_BROWSERS_PATH=0 npx playwright install chromium
cd ..

php artisan optimize
```

> **Do not use `--with-deps` on Laravel Cloud.** That flag runs `playwright install-deps`, which calls `su` to install apt packages. Build containers are non-root, so you get `su: Authentication failure` and the deploy fails.
>
> `PLAYWRIGHT_BROWSERS_PATH=0` installs Chromium under `scripts/node_modules` so browsers ship with the build artifact instead of `~/.cache` (which is not deployed).
>
> Build timeout is 15 minutes. Playwright browser install can take several minutes — that is normal.

If Cloud's default `npm ci && npm run build` is already present, merge rather than duplicate:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cd scripts && npm ci && PLAYWRIGHT_BROWSERS_PATH=0 npx playwright install chromium && cd ..
php artisan optimize
```

### Deploy commands

```bash
php artisan migrate --force
```

Do **not** add:

- `php artisan storage:link` — ephemeral filesystem; use object storage
- `php artisan optimize:clear` — clears caches unexpectedly

---

## 4. Background processes

On the **Worker cluster**, add a queue worker (database driver):

| Process | Command |
|---------|---------|
| Queue worker | `php artisan queue:work --queue=scraping,auditing --timeout=180 --tries=3 --sleep=3 --max-jobs=50` |

- **`--timeout=180`** — must exceed `AuditSiteJob` timeout (150s).
- **`--queue=scraping,auditing`** — scraping jobs are processed first when both are pending.
- **`--max-jobs=50`** — restarts the worker periodically to release memory after Playwright runs (adjust down on small instances).

**Optional — two processes** on a 2 GB+ worker (isolates long audits from fast scraping jobs):

| Process | Command |
|---------|---------|
| Scraping | `php artisan queue:work --queue=scraping --timeout=90 --tries=3 --sleep=3` |
| Auditing | `php artisan queue:work --queue=auditing --timeout=180 --tries=3 --sleep=3 --max-jobs=10` |

Do **not** run `php artisan horizon` unless you also set `QUEUE_CONNECTION=redis` and attach Valkey/Redis.

On **App cluster** or **Worker cluster** (one only):

| Process | Setting |
|---------|---------|
| Scheduler | Enable **Scheduler** toggle |

Scheduled task already registered:

```php
Schedule::command('scanner:purge-expired')->daily();
```

If you scale to multiple App/Worker replicas, add `->onOneServer()` to that schedule entry.

---

## 5. Environment variables

Set these in **Settings → Environment variables**. Re-deploy after changes.

### Application

```env
APP_NAME="nthdesigns Scanner"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.laravel.cloud
```

Generate `APP_KEY` locally (`php artisan key:generate --show`) or let Cloud inject on first deploy.

### Database

Cloud injects Postgres credentials when the resource is attached. Verify:

```env
DB_CONNECTION=pgsql
```

### Queue, cache, session

```env
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=200

CACHE_STORE=database
SESSION_DRIVER=database
```

- **`QUEUE_CONNECTION=database`** — jobs live in the Postgres `jobs` table; workers use `queue:work`, not Horizon.
- **`DB_QUEUE_RETRY_AFTER=200`** — must be greater than the longest job timeout (audits use 150s). Default `90` will re-queue jobs while Playwright is still running.

Redis/Valkey is optional. Only attach it if you prefer `CACHE_STORE=redis` or `SESSION_DRIVER=redis` instead of the database drivers above.

### Storage

After attaching Object Storage:

```env
REPORTS_DISK=s3
```

Cloud sets `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`, etc.

### Scanner APIs

```env
GOOGLE_PLACES_API_KEY=
OPENROUTER_API_KEY=
OPENROUTER_MODEL=anthropic/claude-sonnet-4
```

### Scanner behaviour

```env
REPORT_BOOKING_URL=https://tidycal.com/yourhandle
REPORT_EXPIRY_DAYS=30
SEARCH_RATE_LIMIT_SECONDS=30
AUDIT_TIMEOUT=120
```

### Node / audit scripts (Playwright path)

```env
NODE_BINARY=node
AUDIT_SCRIPT_PATH=
LIGHTHOUSE_BINARY=lighthouse
PLAYWRIGHT_BROWSERS_PATH=0
```

Set `PLAYWRIGHT_BROWSERS_PATH=0` on **worker** environments (not only at build time) so queue workers resolve the Chromium binary installed during build.

Leave `AUDIT_SCRIPT_PATH` empty to use `scripts/audit.js` from project root.

Lighthouse is optional — audits still run without it; performance/SEO scores will be null.

### Cloudflare Browser Rendering (fallback path)

Only needed if Playwright fails on Cloud or you skip local browser install:

```env
AUDIT_DRIVER=cloudflare
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_ACCOUNT_ID=
```

See [Fallback: Cloudflare Browser Rendering](#fallback-cloudflare-browser-rendering) below.

---

## 6. Post-deploy verification

Run these from the Cloud **Commands** tab after the first successful deploy.

### Core app

```bash
php artisan migrate:status
php artisan schedule:list
php artisan queue:monitor scraping auditing
```

Check pending work:

```bash
php artisan tinker --execute="echo 'pending jobs: '.DB::table('jobs')->count().PHP_EOL;"
php artisan queue:failed
```

### Node availability

```bash
which node && node --version
ls -la scripts/node_modules/.cache/ms-playwright 2>/dev/null || ls scripts/node_modules/playwright
```

### Playwright smoke test

```bash
mkdir -p /tmp/audit-test
node scripts/audit.js https://example.com /tmp/audit-test
echo "exit: $?"
ls -la /tmp/audit-test
```

**Success:** JSON on stdout, exit code 0, optional PNG files in `/tmp/audit-test`.

**Failure:** note the stderr — common causes are missing Chromium, sandbox errors, or `node` not on PATH.

### Screenshot smoke test

```bash
mkdir -p /tmp/ss-test
node scripts/screenshot.js https://example.com /tmp/ss-test
ls -la /tmp/ss-test
```

### End-to-end in the app

1. Log in, open **Settings** — all three health checks green (Places, OpenRouter/Anthropic, storage).
2. Run a small search (1–2 results).
3. Wait for pipeline: scoring → audit → combine → report → screenshot.
4. Open prospect detail and public report link — verify grade, violations, desktop screenshot.
5. Confirm the worker process is running (Cloud **Background processes** tab) and `jobs` table count drops after a search.
6. If jobs stall, run `php artisan queue:failed` and inspect `failed_jobs.payload` / `exception`.

---

## 7. Playwright on Cloud — if smoke tests fail

### Build fails: `su: Authentication failure` / `Failed to install browsers`

Your build command includes `playwright install … --with-deps`. Remove `--with-deps` and use:

```bash
cd scripts && npm ci && PLAYWRIGHT_BROWSERS_PATH=0 npx playwright install chromium && cd ..
```

Redeploy. If audits later fail with “missing dependencies”, use the [Cloudflare fallback](#fallback-cloudflare-browser-rendering) or an external audit worker — Cloud build nodes cannot run `install-deps`.

Try the steps below before switching to the Cloudflare fallback.

### A. Add container-safe Chromium flags

Update `scripts/audit.js` and `scripts/screenshot.js`:

```js
const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
});
```

Redeploy and re-run the smoke test.

### B. Confirm NODE_BINARY path

```bash
which node
```

Set `NODE_BINARY` to the full path if `node` is not on the default PATH for queue workers.

### C. Increase worker memory

Playwright often needs more than the default PHP worker limit:

- Worker cluster: **2 GB RAM** minimum
- Use a **single** `queue:work` process for `auditing` on small instances
- Lower `--max-jobs` (e.g. `10`) so the worker restarts after each audit batch and frees memory

### D. Reduce parallelism

On a 2 GB worker, run **one** auditing queue worker (separate background process with `--queue=auditing` only, or a single combined worker with `--max-jobs=10`).

### E. Skip Lighthouse

Do not install Lighthouse in build commands unless you need it — Playwright + axe is the critical path.

---

## 8. Operational notes

### Ephemeral disk

- Temp audit files live under `storage/app/temp/` and are deleted after upload.
- Do not rely on `storage/app/public` persisting — always use `REPORTS_DISK=s3` in production.

### Failed audits

Prospects with websites show `audit_status: failed` when Playwright fails. GBP scoring and reports for no-website prospects still work. Check `php artisan queue:failed`, the `failed_jobs` table, and Cloud worker logs.

### Rate limiting

Search is limited to one search per user every 30 seconds (`SEARCH_RATE_LIMIT_SECONDS`). Adjust via env if needed.

### Purge job

`scanner:purge-expired` runs daily. Requires scheduler enabled. Purges expired report payloads per `REPORT_EXPIRY_DAYS`.

### Queue monitoring

With the database driver there is no `/horizon` dashboard. Use:

- `php artisan queue:failed` / `failed_jobs` in the database
- Cloud worker logs for `AuditSiteJob failed` entries
- Settings → health checks (Node, Playwright)

### Staging

Replicate production environment in Cloud, attach separate logical DB schema, enable HTTP basic auth, use a smaller worker or `QUEUE_CONNECTION=sync` only for UI testing without audits.

### Optional: Redis + Horizon

If you later attach Valkey/Redis and set `QUEUE_CONNECTION=redis`, switch the worker background process to `php artisan horizon` and add your email to the `viewHorizon` gate in `HorizonServiceProvider`.

---

## Fallback: Cloudflare Browser Rendering

Use this if Playwright cannot run reliably on Laravel Cloud workers.

Laravel Cloud's own docs recommend Cloudflare for browser tasks ([Generating PDFs](https://cloud.laravel.com/docs/knowledge-base/generating-pdfs)) because runtime instances are PHP-oriented and do not ship with Chrome.

### What Cloudflare can replace

| Feature | Cloudflare API | Notes |
|---------|----------------|-------|
| Desktop report screenshot | `POST /browser-rendering/screenshot` | Direct replacement for `CaptureScreenshotJob` |
| Full-page capture | Same endpoint + `screenshotOptions.fullPage` | Optional |
| HTML snapshot | `POST /browser-rendering/snapshot` | Returns HTML + base64 screenshot |

### What Cloudflare cannot drop in easily

| Feature | Challenge |
|---------|-----------|
| axe-core WCAG violation scan | No built-in a11y API — needs custom script injection + result extraction |
| Per-violation element screenshots | Requires axe selectors + clipped screenshots; doable but non-trivial |
| Lighthouse performance/SEO scores | Not available via Browser Rendering — keep optional or use PageSpeed Insights API |

### Recommended fallback tiers

#### Tier 1 — Screenshots only (fastest)

**Scope:** ~1 day of work.

- Add `CloudflareBrowserService` wrapping the screenshot endpoint.
- Switch `CaptureScreenshotJob` to call Cloudflare when `AUDIT_DRIVER=cloudflare`.
- Leave `AuditSiteJob` on Playwright if it works; otherwise mark audits as skipped/degraded.

**Env:**

```env
AUDIT_DRIVER=cloudflare
CLOUDFLARE_API_TOKEN=   # Account.Browser Rendering permission
CLOUDFLARE_ACCOUNT_ID=
```

**Screenshot request shape:**

```php
Http::withToken(config('services.cloudflare.api_token'))
    ->post(
        'https://api.cloudflare.com/client/v4/accounts/'
        .config('services.cloudflare.account_id')
        .'/browser-rendering/screenshot',
        [
            'url' => $url,
            'viewport' => ['width' => 1280, 'height' => 800],
            'gotoOptions' => [
                'waitUntil' => 'networkidle0',
                'timeout' => 45000,
            ],
        ],
    );
```

Response body is raw PNG bytes — write to temp file, upload via existing `ScreenshotStorageService`.

#### Tier 2 — External Playwright worker (full parity)

**Scope:** ~2–3 days.

Run Playwright on a platform that supports browsers (Fly.io, Render, Railway):

```
Laravel Cloud worker                    Fly.io audit service
─────────────────────                   ────────────────────
AuditSiteJob ──HTTP POST /audit──►      node scripts/audit.js (always works)
         ◄── JSON payload ──────        Playwright + axe + Lighthouse
CaptureScreenshotJob ──HTTP──►         or separate /screenshot route
```

**Benefits:**

- No change to audit logic — reuse `scripts/audit.js` as-is
- Cloud workers stay lightweight PHP-only
- Scale audit service independently

**Implementation sketch:**

1. Add `config('scanner.audit_service_url')` and `AUDIT_SERVICE_TOKEN`.
2. Replace `Process::run([node, ...])` in `AuditSiteJob` with `Http::timeout(150)->post(...)`.
3. Deploy `scripts/` as a minimal Node Docker image on Fly.io with 1–2 GB RAM.
4. Authenticate with a shared bearer token.

#### Tier 3 — Cloudflare for audits (partial)

**Scope:** ~3–5 days; reduced fidelity.

- Use `/snapshot` with `addScriptTag` to inject axe-core from CDN.
- Run axe in-page; encode results into a hidden DOM node or `window.__auditResults`.
- Fetch rendered HTML and parse results — fragile compared to Playwright.
- Skip per-violation screenshots in v1.
- Skip Lighthouse entirely or add Google PageSpeed Insights API separately.

Only choose this if you want zero extra infrastructure and can accept a simpler a11y signal.

### Suggested decision flow

```mermaid
flowchart TD
    A[Deploy to Laravel Cloud] --> B{Playwright smoke test passes?}
    B -->|Yes| C[Production ready — local Playwright path]
    B -->|No| D{Need full axe + violation screenshots?}
    D -->|Yes| E[Tier 2: Fly.io audit worker]
    D -->|No| F[Tier 1: Cloudflare screenshots only]
    F --> G[GBP + AI outreach still work; a11y scores degraded]
```

### Config additions for fallback (when implemented)

Add to `config/services.php`:

```php
'cloudflare' => [
    'api_token'  => env('CLOUDFLARE_API_TOKEN'),
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
],
```

Add to `config/scanner.php`:

```php
'audit_driver' => env('AUDIT_DRIVER', 'playwright'), // playwright | cloudflare | http
'audit_service_url' => env('AUDIT_SERVICE_URL'),
'audit_service_token' => env('AUDIT_SERVICE_TOKEN'),
```

Branch in `AuditSiteJob::handle()` and `ScreenshotCaptureService` on `audit_driver` / `screenshot_driver` — **implemented**.

### Laravel Cloud quick start

```env
AUDIT_DRIVER=cloudflare
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_ACCOUNT_ID=
REPORTS_DISK=s3
```

Or for full audits via external worker:

```env
AUDIT_DRIVER=http
AUDIT_SERVICE_URL=https://your-audit-worker.fly.dev
AUDIT_SERVICE_TOKEN=
SCREENSHOT_DRIVER=cloudflare
```

---

## Quick reference — production env template

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://scanner.example.com

DB_CONNECTION=pgsql

QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=200
CACHE_STORE=database
SESSION_DRIVER=database

REPORTS_DISK=s3

GOOGLE_PLACES_API_KEY=
OPENROUTER_API_KEY=
OPENROUTER_MODEL=anthropic/claude-sonnet-4

REPORT_BOOKING_URL=
REPORT_EXPIRY_DAYS=30
SEARCH_RATE_LIMIT_SECONDS=30
AUDIT_TIMEOUT=120

NODE_BINARY=node

# Fallback (optional)
# AUDIT_DRIVER=cloudflare
# CLOUDFLARE_API_TOKEN=
# CLOUDFLARE_ACCOUNT_ID=
```

---

## Related docs

- [Laravel Cloud environments](https://cloud.laravel.com/docs/environments) — build commands, Node version, ephemeral filesystem
- [Laravel Cloud queues](https://cloud.laravel.com/docs/queues) — worker clusters, `queue:work`
- [Laravel Object Storage](https://cloud.laravel.com/docs/resources/object-storage) — R2 bucket setup
- [Cloudflare Browser Rendering — screenshot](https://developers.cloudflare.com/browser-rendering/rest-api/screenshot-endpoint/)
