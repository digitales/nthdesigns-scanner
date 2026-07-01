# Outreach Report Refresh — Design Spec

**Date:** 2026-07-01  
**Status:** Approved  
**Screens:** D — `/outreach` (`Outreach/Index.jsx`), Lists — `/lists/pipeline` (`Lists/Pipeline.jsx`)

**Approach:** Hybrid — bulk refresh actions on the outreach page; read-only outreach pipeline view under Lists for browsing and filtering. Shared backend query powers both surfaces.

---

## Goal

Give operators a way to **manually refresh stale audit reports** for prospects in the outreach queue that have **not yet been contacted**, and **regenerate outreach drafts** in the same action — so public report timestamps and outreach copy stay current before first send.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Eligible cohort | In outreach queue **and** no outreach marked sent (`sent_at` is null on all channels for this user) |
| Not eligible | Any prospect with at least one sent outreach — excluded from refresh selection |
| No report | Shown in queue but not selectable for refresh (same as generate today) |
| Booked filter | Booked prospects follow the same unsent rule — refreshable only if outreach not yet sent |
| Selection | Manual checkboxes; operator picks which queue items to refresh |
| Report age display | Show `generated_at` date on queue chips and pipeline table; visual **stale** hint when older than 30 days (informational only — not auto-selected) |
| Refresh action | Always: regenerate report → delete unsent drafts → regenerate outreach for all channels |
| Pitch options | Reuse existing generate form fields (pitch angle, agency name, CPC benchmark) |
| UX placement | **Hybrid:** refresh controls on `/outreach`; read-only pipeline under `/lists/pipeline` |
| Lists pipeline | Read-only — no remove, refresh, or generate; link to prospect detail and `/outreach` |
| Report token | Preserved on regenerate — existing `/r/{token}` URLs stay valid |
| View count | Preserved on report regenerate |
| MCP exposure | Out of scope |

---

## UX

### Outreach page (`/outreach`)

#### Queue chips — new fields

Each chip shows (in addition to existing content):

| Field | Display |
|-------|---------|
| Report age | `Report · 12 Mar` from `report_data.generated_at` (fallback: `prospect_reports.created_at`) |
| Stale hint | Subtle visual when report age > 30 days (e.g. muted warning colour or "Stale" micro label) |
| Outreach status | `No draft` / `Drafted` / `Sent` |
| Checkbox | Visible only when `refresh_eligible` (in queue, has report, no sent outreach) |

Checkbox click must not trigger navigation to prospect detail (`stopPropagation` on checkbox, same pattern as remove button).

#### Selection controls

Above the queue list (or in queue header row):

- **Select all refreshable** — checks all eligible chips in current filter (All or Booked)
- **Clear selection**
- Selection count micro label: `N selected`

#### Refresh action

New secondary button beside **Generate for N prospects**:

- Label: **Refresh selected (N)**
- Disabled when: nothing selected, processing, or any selected prospect lacks a report
- Uses pitch angle, agency name, and CPC benchmark from the existing generate form (same `useForm` state)
- On submit: `POST /outreach/refresh` with `{ prospect_ids[], pitch_angle, agency_name?, cpc_benchmark? }`
- Flash success: `N prospect(s) queued for report refresh.`
- Flash skipped: prospects that failed validation (not in queue, already sent, no report)

After submit, operator reloads page to see updated report ages and regenerated drafts (same pattern as generate today).

#### Sent prospects in queue

Prospects with sent outreach remain in the queue (unchanged) but show **Sent** badge and have no checkbox. They are not refreshable.

---

### Lists pipeline (`/lists/pipeline`)

#### Navigation

Add sub-nav under Lists (on pipeline page and optionally on index/browse headers):

**My lists · Browse · Outreach pipeline**

Route: `GET /lists/pipeline` → `OutreachPipelineController@index`  
Name: `lists.pipeline`

#### Tabs

Segmented control mirroring `/outreach`:

| Tab | Filter |
|-----|--------|
| Outreach | All queue members |
| Booked | Queue members with `prospect.report.booking` |

Query param: `?booked=1` for Booked tab (same convention as `/outreach`).

#### DataTable columns

| Column | Source |
|--------|--------|
| Business | `business_name` — link to `/prospects/{id}` |
| Niche | `search.niche` |
| City | `search.city` |
| Score | `combined_score` (`ScoreBadge`) |
| Report age | `report_age_label` |
| Outreach status | `outreach_status` label |
| Booked | `booked_label` or em dash |

#### Row actions (read-only)

- **View** → `/prospects/{id}`
- **Open in outreach** → `/outreach` (no deep-link to individual chip in v1)

#### Filters

| Filter | Type |
|--------|------|
| Niche | Text input |
| City | Text input |
| Min score | Number input |
| Outreach status | Select: All / No draft / Drafted / Sent |
| Sort | Default: report age oldest first; options: score desc, name asc |

Pagination: 25 per page (consistent with browse).

#### Empty states

| Condition | Message |
|-----------|---------|
| No queue members | "Outreach queue is empty." + link to Search |
| Filters match nothing | "No prospects match these filters." |

No bulk actions, no checkboxes, no refresh button on this page.

---

## Backend

### Shared query — extend `OutreachQueueLoader`

Add method or extend `selections()` to eager-load outreach emails needed for status:

```php
'prospect.outreachEmails' => fn ($q) => $q
    ->where('user_id', $user->id)
    ->latest(),
```

Add helper on loader (or new `OutreachPipelineService`):

```php
public function outreachStatus(User $user, Prospect $prospect): string
// Returns: 'none' | 'drafted' | 'sent'

public function refreshEligible(User $user, Prospect $prospect): bool
// In queue + has report + outreachStatus !== 'sent'
```

### `OutreachSelectionResource` — new fields

```php
'report_generated_at' => ?string ISO8601,
'report_age_label'      => ?string,  // e.g. '12 Mar'
'report_stale'          => bool,     // generated_at older than 30 days
'outreach_status'       => string,   // 'none' | 'drafted' | 'sent'
'outreach_status_label' => string,   // 'No draft' | 'Drafted' | 'Sent'
'refresh_eligible'      => bool,
```

Outreach status logic:

- **sent** — any `outreach_emails` row for this user/prospect has `sent_at` not null
- **drafted** — has unsent outreach row(s), none sent
- **none** — no outreach rows for this user/prospect

### Route — refresh

```
POST /outreach/refresh → OutreachController::refresh
```

Name: `outreach.refresh`

Request validation (extend or mirror `GenerateOutreachEmailRequest`):

```php
'prospect_ids'   => ['required', 'array', 'min:1'],
'prospect_ids.*' => ['integer', 'exists:prospects,id'],
'pitch_angle'    => ['required', 'string', 'in:auto,gbp,accessibility,combined'],
'agency_name'    => ['nullable', 'string', 'max:255'],
'cpc_benchmark'  => ['nullable', 'numeric', 'min:0'],
```

### `OutreachController::refresh`

For each `prospect_id`:

1. Authorize `view` on prospect
2. Verify prospect is in user's outreach selections — skip with message if not
3. Verify no sent outreach for this user — skip with message if sent
4. Verify prospect has a report — skip with message if missing
5. Delete unsent outreach emails: `OutreachEmail` where `user_id`, `prospect_id`, `sent_at` is null
6. Dispatch job chain (auditing queue):

```php
Bus::chain([
    new GenerateProspectReportJob($prospect),
    new RegenerateOutreachForProspectJob($prospect, $user, $options),
])->dispatch();
```

Return redirect with success + skipped arrays (same flash pattern as `generate`).

### New job — `RegenerateOutreachForProspectJob`

Runs after report generation completes. Responsibilities:

1. Fresh-load prospect with report
2. Resolve channels via `OutreachChannelResolver`
3. Skip email channel if unsubscribed
4. Dispatch `GenerateOutreachEmailJob` per channel with `force: true` in options

### `GenerateOutreachEmailJob` — force flag

Add optional `$options['force']` (default `false`).

When `force === true`, skip the dedup early-return:

```php
if (! ($this->options['force'] ?? false) && OutreachEmail::query()->...->exists()) {
    return;
}
```

Unsent drafts are deleted before the chain runs, so force mainly guards against race conditions and makes intent explicit.

### Lists pipeline controller

```php
// OutreachPipelineController@index
GET /lists/pipeline
```

Reuses `OutreachQueueLoader::selections()` with filters applied in controller or dedicated query class. Returns paginated `OutreachSelectionResource` rows plus filter meta.

Authorization: authenticated user only; data scoped to user's outreach selections.

---

## Data flow

```
Operator selects prospects on /outreach
        ↓
POST /outreach/refresh
        ↓
Per prospect: delete unsent outreach_emails
        ↓
Bus::chain([
  GenerateProspectReportJob,      → updates report_data.generated_at, expires_at
  RegenerateOutreachForProspectJob → dispatches GenerateOutreachEmailJob × channels (force)
])
        ↓
Operator reloads /outreach → fresh report ages + new drafts in right column
```

Public report at `/r/{token}` shows updated "Audit · {date}" from new `generated_at`.

---

## Error handling

| Case | Behaviour |
|------|-----------|
| Report job fails | Chain stops; outreach not regenerated; log error; operator sees stale draft removed, no new draft until manual retry |
| Outreach generation fails | Report still updated; partial channel drafts possible; log per channel |
| Prospect removed from queue mid-job | Jobs no-op safely (prospect/report checks) |
| Mixed batch (some invalid) | Valid prospects queued; invalid listed in `flash.skipped` |
| Unsubscribed email channel | Skip email channel only; form/LinkedIn still generated |
| No contact path | Skip outreach regeneration; report still refreshed |

---

## Testing

### Feature tests

- `POST /outreach/refresh` queues chain for eligible prospect
- Rejects prospect not in queue
- Rejects prospect with sent outreach
- Rejects prospect without report
- Deletes unsent drafts before chain
- Skips unsubscribed email channel
- Mixed batch: dispatches valid, skips invalid with flash
- `GET /lists/pipeline` returns only user's queue members
- Booked tab filters correctly
- Pipeline filters (niche, city, min score, outreach status) work

### Unit tests

- `OutreachQueueLoader` outreach status: none / drafted / sent
- `refreshEligible` logic
- `GenerateOutreachEmailJob` with `force: true` bypasses dedup

---

## Out of scope

- Auto-select stale reports by age threshold
- Refresh prospects not in outreach queue
- Regenerate outreach without refreshing report
- Deep-link from pipeline row to specific queue chip
- MCP tools for bulk refresh
- Table layout toggle on `/outreach` (queue chips sufficient for v1)
- Refresh sent outreach or post-send report updates

---

## Files (expected)

| Area | Files |
|------|-------|
| Routes | `routes/web.php` |
| Controller | `OutreachController.php`, new `OutreachPipelineController.php` |
| Job | `RegenerateOutreachForProspectJob.php` |
| Job change | `GenerateOutreachEmailJob.php` (force flag) |
| Service | `OutreachQueueLoader.php` (status helpers) |
| Resource | `OutreachSelectionResource.php` |
| Request | `RefreshOutreachReportsRequest.php` |
| Frontend | `Outreach/Index.jsx`, new `Lists/Pipeline.jsx`, Lists sub-nav |
| Tests | `OutreachRefreshTest.php`, `OutreachPipelineTest.php`, unit tests for loader |
