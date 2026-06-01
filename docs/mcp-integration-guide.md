# MCP integration (prospect scanner)

Connect Cursor, Claude, or ChatGPT to monitor operator searches and start single-site URL audits from agent chat.

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
| `start_single_site_audit` | Submit a URL (`direct_url` scan) |

## Monitoring workflow

1. `start_single_site_audit` → `search_id`
2. Poll `get_search` until `status` is `complete` or `failed`
3. Use `list_search_prospects` or `get_search` with `include_prospects: true` for failures/scores
4. Open `app_url` in the browser for full UI

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
