# Browser service (Fly.io)

HTTP wrapper around `scripts/audit.js` and `scripts/screenshot.js` for Laravel Cloud workers that cannot run Playwright locally.

**Full deployment guide and troubleshooting:** [docs/deployment/laravel-cloud.md §10](../../docs/deployment/laravel-cloud.md#10-deploy-the-flyio-browser-service) and [Fly troubleshooting](../../docs/deployment/laravel-cloud.md#fly-troubleshooting).

## Endpoints

| Method | Path | Body | Response |
|--------|------|------|----------|
| `GET` | `/health` | — | `{ "ok": true }` |
| `POST` | `/audit` | `{ "url": "https://…" }` | audit.js JSON; violation PNGs include `content_base64` |
| `POST` | `/screenshot` | `{ "url": "https://…" }` | `{ "desktop": "desktop.png", "content_base64": "…" }` |

Send `Authorization: Bearer <token>` on `POST /audit` and `POST /screenshot` when `BROWSER_SERVICE_TOKEN` or `AUDIT_SERVICE_TOKEN` is set. `GET /health` is always public (required for Fly health checks).

## Deploy (from repository root)

Build context must be the **repository root** so the Dockerfile can copy `scripts/`.

```bash
fly apps create nth-scanner-browser   # once
fly secrets set BROWSER_SERVICE_TOKEN="$(openssl rand -hex 32)" --config scripts/browser-service/fly.toml
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
  -d '{"url":"https://example.com"}' http://127.0.0.1:8080/health
```
