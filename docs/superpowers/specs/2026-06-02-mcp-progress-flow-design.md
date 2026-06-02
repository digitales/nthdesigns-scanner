# MCP progress flow (shared web + MCP) — Design Spec

**Date:** 2026-06-02  
**Status:** Approved (brainstorming)  
**Scope:** Introduce a shared progress flow contract for scans so both web UI and MCP clients can show consistent per-search and per-site progress, with MCP snapshot APIs in v1 and MCP native progress notifications in v1.1.

---

## Goal

Provide a single progress model across:

1. Web search detail UI (`/searches/{id}`)
2. MCP tools for chat-based monitoring

The feature must support:

- Direct URL audits and niche/area search scans in v1
- Per-site progress detail including status, pipeline step, and time buckets
- Backward-compatible MCP response enrichment
- Optional MCP native progress notifications in v1.1

---

## Requirements summary

| Topic | Decision |
|-------|----------|
| Primary surface | Both web + MCP together |
| Search scope (v1) | Direct URL + niche/area scans |
| Per-site detail | Status + current step + timestamps/duration buckets |
| Time detail style | Coarse duration buckets (not exact ETA math) |
| MCP rollout | New dedicated progress tool + additive enrichment on existing tools |
| MCP streaming | v1.1 includes `progressToken` + `notifications/progress` support |

---

## Architecture

### Chosen approach

Introduce a shared `ProgressFlowService` that computes progress DTOs once and is reused by:

- `SearchController` (web responses)
- `McpSearchService` (existing MCP tools)
- New MCP progress-focused tools

This keeps web and MCP behavior aligned while allowing a gradual MCP rollout.

### Components

| Component | Responsibility |
|-----------|----------------|
| `ProgressFlowService` (new) | Compute search-level and prospect-level progress flow DTOs from `Search`, `Prospect`, and minimal job metadata |
| `McpSearchService` (update) | Additive fields to `get_search` and `list_search_prospects` |
| `McpController` (update) | Register/dispatch new progress tools; support optional `_meta.progressToken` handling in streamable tools/call flow |
| `SearchController` / search show payload (update) | Use shared DTO instead of duplicating phase math in frontend |
| `resources/js/Pages/Search/Show.jsx` (refactor) | Consume server-provided flow contract for display |
| MCP docs (update) | Document snapshot + optional notification usage patterns |

---

## Progress contract

### Search-level flow DTO

Embedded as `progress_flow` in relevant responses.

| Field | Type | Notes |
|------|------|-------|
| `phase` | enum | `queued`, `discovering`, `auditing`, `complete`, `failed` |
| `progress` | number | Monotonic completed units so far |
| `total` | number\|null | Nullable when unknown during discovery |
| `percent` | integer\|null | Rounded 0-100 when total known and > 0 |
| `duration_bucket` | enum | `<30s`, `30-120s`, `2-5m`, `5m+` |
| `message` | string | Human-readable status message |
| `search_complete` | bool | True for terminal search statuses |

### Prospect-level flow DTO

Returned per prospect as `progress_flow`.

| Field | Type | Notes |
|------|------|-------|
| `audit_status` | enum | Existing status: `pending`, `complete`, `failed`, `skipped` |
| `current_step` | enum | `discovery`, `gbp`, `a11y`, `performance`, `scoring`, `report`, `done` |
| `step_started_at` | ISO8601\|null | Best-effort start timestamp for current step |
| `step_duration_bucket` | enum | `<30s`, `30-120s`, `2-5m`, `5m+` |
| `status_message` | string | Short message for operators/agents |

### Derivation rules

- Direct URL flows can skip discovery-oriented states where not applicable.
- `current_step` derives from persisted fields already present (scores/payload/report and job outcomes).
- If exact step start time is unavailable, `step_started_at` stays `null` and bucket derives from best-known fallback.

---

## MCP API design

### Existing tools (additive updates)

1. `get_search`
   - Add `progress_flow`
   - If `include_prospects: true`, include `prospects[].progress_flow`
2. `list_search_prospects`
   - Add `progress_flow` per prospect row

These changes must be additive and preserve existing fields for compatibility.

### New tools

1. `get_search_progress_flow` (v1)
   - Focused progress snapshot (search flow + prospect flow rows)
   - Lighter payload than full prospect detail
2. `watch_search_progress` (v1.1)
   - Long-poll/stream-oriented progress watch for a bounded window
   - Returns snapshot response and may emit progress notifications in streamable mode

### v1.1 MCP native progress notifications

For streamable MCP requests, support optional `_meta.progressToken` in request metadata and emit `notifications/progress` as described by MCP progress utility.

Reference: [MCP Progress utility](https://modelcontextprotocol.io/specification/2025-03-26/basic/utilities/progress)

Behavior:

- Accept token only when request is active and transport supports notifications
- Emit monotonic `progress` values (with optional `total` and `message`)
- Throttle emissions to at most once every 2 seconds per token
- Stop emitting on completion, failure, timeout, or disconnect
- Legacy JSON-RPC continues snapshot-only behavior (token ignored)

---

## Data flow

### v1 snapshot flow

1. Client calls `get_search`, `list_search_prospects`, or `get_search_progress_flow`
2. Service loads minimal needed rows/relations
3. `ProgressFlowService` computes normalized flow payload
4. Response returns shared contract used by web and MCP

### v1.1 notification flow

1. Client calls streamable tool with `_meta.progressToken`
2. Server validates token and starts bounded watch cycle
3. Server emits `notifications/progress` updates while operation is in progress
4. Server returns final snapshot response

Snapshot remains baseline for resilience and backfill if notifications are missed.

---

## Error handling and constraints

- If `total` unknown during discovery, emit `total: null` and `percent: null`.
- If timestamps are missing, degrade gracefully without failing request.
- On terminal status (`complete`/`failed`), emit final progress update (if streaming) then stop.
- Add watch duration cap of 45 seconds per call to avoid unbounded server work.
- Enforce per-token uniqueness across active requests to satisfy MCP semantics.

---

## Testing strategy

### Unit tests

- `ProgressFlowService` phase mapping for direct URL and niche/area searches
- Prospect `current_step` derivation and bucket boundaries
- Monotonic search progress calculations

### Feature tests (MCP)

- `tools/list` includes `get_search_progress_flow` and `watch_search_progress`
- Existing tools remain backward-compatible with additive flow fields
- Streamable requests with `_meta.progressToken` can produce valid `notifications/progress`
- Legacy JSON-RPC ignores token and returns snapshots only

### Feature tests (web)

- Search show payload includes shared `progress_flow` contract
- Existing running/completed UX remains correct after frontend refactor

### Performance/regression checks

- No N+1 regressions for large prospect lists
- Notification throttling behaves under rapid polling/watch calls

---

## Delivery phasing

### v1 (snapshot parity)

- Add shared `ProgressFlowService`
- Additive updates to existing MCP tools
- Add `get_search_progress_flow`
- Refactor web search detail to consume shared contract
- Update docs for snapshot polling workflows

### v1.1 (native MCP progress)

- Add `watch_search_progress`
- Add optional `_meta.progressToken` handling in streamable path
- Emit `notifications/progress` with throttling and termination guarantees
- Update MCP integration docs with notification-capable client examples and fallback guidance

---

## Out of scope

- ETA prediction models
- Full job-level trace UI in web
- WebSocket push for browser UI
- New scan creation workflows beyond existing single-site audit MCP write path
- Public unauthenticated progress access
