# API Usage Tracking & Quotas — Design Spec

**Date:** 2026-06-05  
**Status:** Approved

## Goal

Track and limit outbound API usage for Google Places and Brave Search to prevent runaway batch jobs and steady budget creep, with visibility on Settings.

## Decisions

| Topic | Choice |
|-------|--------|
| Goal | Batch runaway + steady budget creep |
| Enforcement | Warn ~80%, hard block at 100% |
| Windows | Daily + monthly (`Europe/London`) |
| Counting | Call counts for limits; cost estimates for display |
| v1 scope | Google Places + Brave |
| Limits config | Env ceiling + Settings override (can only lower) |
| Warnings | Settings page only |

## Architecture

- **`ApiUsageGate`** — check before billable HTTP; record after completed response
- **`ApiUsageLimiter`** — resolve effective limits; return `ok` / `warning` / `blocked`
- **`ApiUsageRecorder`** — atomic increment on `api_usage_counters`
- **`ApiUsageDashboard`** — snapshot for Settings UI

Integration in `GooglePlacesService`, `PlacesTextSearchClient`, and `BraveSearchService` only. Cache hits and health probes are not counted.

## Operations

| Provider | Operation | HTTP surface |
|----------|-----------|--------------|
| `google_places` | `text_search` | `places:searchText` |
| `google_places` | `place_details` | `GET places/{id}` |
| `brave` | `web_search` | Brave web search API |

## Data model

**`api_usage_counters`** — unique `(provider, operation, period_type, period_key)` with `count`.

**`api_quota_settings`** — singleton row; nullable override columns per operation × period. Null = env default. Values must be ≤ env ceiling.

## Config

Env ceilings, unit costs (pence, display only), `API_QUOTA_WARNING_PERCENT=80`, `API_QUOTA_ENFORCEMENT=true|false`.

## Settings UI

New card on Settings: quota bars per operation (daily + monthly), count/limit, estimated cost, warning/blocked status. Collapsible limit adjustment form.

## Error handling

- `ApiQuotaExceededException` on block; queue middleware calls `$job->fail()` (no retry)
- Increment on any completed HTTP response (including 4xx); skip cache hits and health checks
- Minor overshoot under concurrency acceptable for v1

## Out of scope (v1)

OpenRouter, Google CSE, Cloudflare; per-user attribution; app-wide banners; billing API sync; historical charts.
