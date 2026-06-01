# MCP scan monitoring — Implementation Plan

> **For agentic workers:** Implement task-by-task with tests after each step.

**Goal:** Hosted remote MCP with OAuth so AI clients can monitor operator searches and start single-site URL audits.

**Architecture:** Port ideatub `oauth-mcp` module; slim `McpController` with four scanner tools; Inertia Connected apps UI.

**Tech Stack:** Laravel 13, firebase/php-jwt, JSON-RPC + Streamable HTTP MCP.

---

## Completed in this branch

- [x] OAuth migrations, services, well-known routes, consent view
- [x] `McpController` + `McpSearchService` + `McpSingleSiteAuditService`
- [x] Settings → Connected apps (Inertia)
- [x] Feature tests + `docs/mcp-integration-guide.md`

## Deploy checklist

- [ ] `php artisan scanner:oauth-mcp-keys` (or set `OAUTH_MCP_*_B64` on Cloud)
- [ ] `OAUTH_MCP_ENABLED=true`, `APP_URL` HTTPS
- [ ] Run migrations on production
- [ ] Add remote MCP in Cursor with OAuth to `{APP_URL}/api/mcp`
