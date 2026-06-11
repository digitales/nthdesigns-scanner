# Browser service (Fly.io)

HTTP wrapper around `scripts/audit.js` and `scripts/screenshot.js` for Laravel Cloud workers that cannot run Playwright locally.

**Full deployment guide and troubleshooting:** [docs/deployment/laravel-cloud.md §10](../../docs/deployment/laravel-cloud.md#10-deploy-the-flyio-browser-service) and [Fly troubleshooting](../../docs/deployment/laravel-cloud.md#fly-troubleshooting).

## Audit pipeline

`POST /audit` runs `scripts/audit.js`:

1. **Lighthouse** starts immediately (async) — runs in parallel with Playwright.
2. Playwright loads the page and runs **axe** accessibility checks.
3. Top violation screenshots are captured.
4. If local Lighthouse returns null, **PageSpeed Insights** is called when `PAGESPEED_API_KEY` is set (mobile strategy).

Typical duration: **90–180s**. Laravel Cloud should set `AUDIT_TIMEOUT=210` (HTTP client) and queue workers `--timeout=270` (above `AuditSiteJob` at 240s).

**Concurrency:** Fly runs on a single 2 GB VM by default. `server.mjs` limits concurrent Playwright jobs via `BROWSER_SERVICE_MAX_CONCURRENT` (default **2**). Laravel staggers `AuditSiteJob` dispatches per search via `AUDIT_DISPATCH_STAGGER_SECONDS` (default **30**) so HTTP clients do not time out while jobs queue on Fly. Dispatching hundreds of repairs at once without `--delay` can still overwhelm Fly; use `scanner:repair-audits --only=screenshots --delay=10 --limit=50 --execute` and re-run until clear.

Prospects audited before Lighthouse/PSI worked on Fly keep `performance_score = 0` until re-audited — see [backfill](../../docs/deployment/laravel-cloud.md#e-lighthouse-on-fly-production) in the deployment guide.

## Endpoints

| Method | Path | Body | Response |
|--------|------|------|----------|
| `GET` | `/health` | — | `{ "ok": true }` |
| `POST` | `/audit` | `{ "url": "https://…" }` | audit.js JSON; violation PNGs include `content_base64` |
| `POST` | `/detect-cms` | `{ "url": "https://…" }` | cms-detect.js JSON (`platform`, `confidence`, `signals`, …) |
| `POST` | `/screenshot` | `{ "url": "https://…" }` | `{ "desktop": "desktop.png", "content_base64": "…" }` |

Send `Authorization: Bearer <token>` on `POST /audit`, `POST /detect-cms`, and `POST /screenshot` when `BROWSER_SERVICE_TOKEN` or `AUDIT_SERVICE_TOKEN` is set. `GET /health` is always public (required for Fly health checks).

## Deploy (from repository root)

Build context must be the **repository root** so the Dockerfile can copy `scripts/`.

```bash
fly apps create nth-scanner-browser   # once
fly secrets set BROWSER_SERVICE_TOKEN="$(openssl rand -hex 32)" --config scripts/browser-service/fly.toml
# Optional: PSI fallback when local Lighthouse fails on Fly
fly secrets set PAGESPEED_API_KEY="your-google-api-key" --config scripts/browser-service/fly.toml
fly deploy . --config scripts/browser-service/fly.toml
```

The `.` is the Docker **build context** (repository root). `dockerfile = "Dockerfile"` in `fly.toml` is relative to this config file’s directory. Do not pass `--dockerfile scripts/browser-service/Dockerfile` — that path is resolved twice and fails.

Set the same token on Laravel Cloud:

```env
AUDIT_SERVICE_URL=https://nth-scanner-browser.fly.dev
AUDIT_SERVICE_TOKEN=<same secret>
```

`AUDIT_DRIVER` becomes `http` automatically when `AUDIT_SERVICE_URL` is set. `SCREENSHOT_DRIVER` defaults to `http` as well.

## Local smoke test

```bash
cd scripts && npm ci
BROWSER_SERVICE_TOKEN=test node browser-service/server.mjs
```

```bash
curl -s -H "Authorization: Bearer test" -H "Content-Type: application/json" \
  -d '{"url":"https://example.com"}' http://127.0.0.1:8080/detect-cms | jq '.platform'
```

Expect a platform string (`wordpress`, `unknown`, etc.).

```bash
curl -s -H "Authorization: Bearer test" -H "Content-Type: application/json" \
  -d '{"url":"https://example.com"}' http://127.0.0.1:8080/audit | jq '.lighthouse'
```

Expect non-null `lighthouse.performance` (1–100).
