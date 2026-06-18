# MCP integration (prospect scanner)

Connect Cursor, Claude, or ChatGPT to monitor operator searches, email warmup health, and start single-site URL audits from agent chat.

## Prerequisites

1. Scanner account (Breeze login).
2. OAuth keys on the server (`php artisan scanner:oauth-mcp-keys` locally; `OAUTH_MCP_*_B64` on Laravel Cloud).

## Endpoint

- **URL:** `https://your-scanner.example.com/api/mcp`
- **Auth:** OAuth 2.1 (PKCE) recommended. Scope: `scanner:mcp`. Or personal **MCP keys** via `x-scanner-key` header.

## Cursor setup

1. **Settings → Tools & MCP → Add MCP server** (remote / streamable HTTP).
2. URL: `https://your-scanner.example.com/api/mcp`
3. Choose **OAuth** when prompted and sign in to the scanner.
4. Revoke OAuth access under **Settings → Connected apps**.

### MCP key (header auth)

For clients that only support a static header:

1. **Settings → MCP keys** → Create key (shown once).
2. Add header `x-scanner-key: scanner_…` on requests to `/api/mcp`.
3. Revoke individual keys on the same page.

## Tools

| Tool | Description |
|------|-------------|
| `list_searches` | Recent operator searches |
| `get_search` | Status + progress; `include_prospects` for detail |
| `list_search_prospects` | Prospect summaries for one search |
| `get_search_progress_flow` | Search/prospect progress flow snapshot (phase, step, coarse duration buckets) |
| `watch_search_progress` | Bounded progress watch (supports streamable progress notifications) |
| `start_single_site_audit` | Submit a URL (`direct_url` scan) |
| `list_warmup_mailboxes` | Email warmup mailboxes with plan limits and setup status |
| `get_warmup_mailbox` | Warmup mailbox detail: weekly stats, recent sends, alerts |

## Scan monitoring workflow

1. `start_single_site_audit` → `search_id`
2. Poll `get_search_progress_flow` (or `get_search`) until `progress_flow.search_complete` is true
3. Use `list_search_prospects` or `get_search` with `include_prospects: true` for failures/scores
4. Open `app_url` in the browser for full UI

## Warmup monitoring workflow

1. `list_warmup_mailboxes` → check `plan.setup_complete`, scan for `has_unread_alerts` or `status: at_risk`
2. `get_warmup_mailbox` with `mailbox_id` → inspect stats, recent sends, alert messages
3. Open `app_url` in the browser for connect, pause, and configuration

### Streamable progress notifications (v1.1)

When calling tools over streamable MCP transport, clients can include:

```json
{
  "_meta": {
    "progressToken": "search-42"
  }
}
```

Supported tools (`get_search`, `get_search_progress_flow`, `watch_search_progress`) may emit `notifications/progress` with monotonic `progress`, optional `total`, and `message`.

**Worker note:** Streamable watches hold a PHP **app worker** for up to `timeout_seconds` (max 45s), polling every `MCP_PROGRESS_POLL_SECONDS` (default 2s) in 200ms slices so client disconnects release the worker promptly. Prefer `get_search_progress_flow` for lightweight agent polling when you do not need SSE `notifications/progress`. On Laravel Cloud, size the app cluster for expected concurrent watches (e.g. two agents each running one watch ≈ two busy workers). Disconnecting the client ends the watch early (`connection_aborted`).

## Revocation

| Method | Where |
|--------|--------|
| OAuth sessions | **Settings → Connected apps** |
| MCP API keys | **Settings → MCP keys** → Revoke |

OAuth access tokens expire within about an hour after disconnect; MCP keys stop working immediately when revoked.

## Protocol

- Legacy JSON-RPC: `Accept: application/json`
- Streamable HTTP: `Accept: application/json, text/event-stream` (requires `initialize` + `Mcp-Session-Id`)

Set `MCP_STREAMABLE_ALLOWED_HOSTS` if your client Origin is blocked.
