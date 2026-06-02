# MCP Progress Flow — Implementation Plan

**Goal:** Implement a shared progress flow contract for web and MCP, with snapshot parity in v1 and native MCP progress notifications support in v1.1.

**Spec:** `docs/superpowers/specs/2026-06-02-mcp-progress-flow-design.md`

---

## Task 1: Shared backend progress contract

**Files**
- Create: `app/Services/ProgressFlowService.php`
- Modify: `app/Http/Controllers/SearchController.php`
- Modify: `app/Services/Mcp/McpSearchService.php`

**Steps**
- [ ] Implement `ProgressFlowService`:
  - search phase/progress/percent/duration buckets/message
  - prospect current step/status message/step duration buckets
- [ ] Use it in `SearchController@show` for `search.progress_flow` and `prospects[].progress_flow`
- [ ] Use it in `McpSearchService` for additive flow fields in existing responses

---

## Task 2: MCP tool expansion

**Files**
- Modify: `app/Http/Controllers/Api/McpController.php`
- Modify: `app/Services/Mcp/McpSearchService.php`

**Steps**
- [ ] Add `get_search_progress_flow` tool definition + dispatch handler
- [ ] Add `watch_search_progress` tool definition + dispatch handler
- [ ] Keep `get_search` and `list_search_prospects` backward-compatible, additive only

---

## Task 3: v1.1 progress notifications (streamable)

**Files**
- Modify: `app/Http/Controllers/Api/McpController.php`

**Steps**
- [ ] Parse optional `_meta.progressToken` for streamable `tools/call`
- [ ] Emit `notifications/progress` lines for active watches
- [ ] Enforce monotonic progress, 2s throttle, and 45s max watch window
- [ ] Keep legacy JSON-RPC behavior snapshot-only

---

## Task 4: MCP docs and tests

**Files**
- Modify: `tests/Feature/McpScanToolsTest.php`
- Modify: `docs/mcp-integration-guide.md`

**Steps**
- [ ] Add tests for new tools appearing in `tools/list`
- [ ] Add tests for additive `progress_flow` fields in `get_search`
- [ ] Add tests for new snapshot flow tool behavior
- [ ] Add focused test for progress watch response shape
- [ ] Update docs with v1 snapshot workflow and v1.1 progress notification notes

---

## Task 5: Verification

**Steps**
- [ ] Run targeted PHPUnit tests for MCP/search progress behavior
- [ ] Run lint diagnostics on touched files
- [ ] Confirm no regressions in existing MCP tool tests
