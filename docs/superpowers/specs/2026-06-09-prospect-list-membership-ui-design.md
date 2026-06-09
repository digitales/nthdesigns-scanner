# Prospect list membership UI

**Date:** 2026-06-09  
**Status:** Approved and implemented

## Goal

Let operators add prospects to manual lists from the prospect detail page and search results, and see existing list membership with pipeline status.

## Decisions

| Area | Choice |
|------|--------|
| Detail — membership | List name + pipeline status, linked to list detail |
| Detail — remove | View/navigate only; remove stays on list page |
| Search — bulk add | Header action when rows selected + list picker |
| Search — single add | Expanded row, alongside outreach |
| Search — row badges | Show list name (1 list) or "On N lists" (multiple) |
| Add picker | Hide lists the prospect is already on |

## Architecture

### Backend

`ProspectListMembershipService` centralises:

- Loading manual list memberships for one or many prospects
- Formatting `{ list_id, list_name, status, status_label }`
- Computing `addableLists` (manual lists minus current memberships)

**Prospect detail** (`ProspectController@show`):

- `listMembership` — current manual list rows
- `addableLists` — lists available to add

**Search results** (`SearchController@show`):

- `manualLists` — all user manual lists (for pickers)
- `prospects[].list_memberships` — per-row membership for badges and filtering

Existing `POST /lists/{list}/items` with `{ prospect_ids: [] }` handles single and bulk adds.

### Frontend

**`ListPicker`** — shared select control; resets after selection; posts via caller.

**Prospect detail** — "Lists" sidebar card:

1. Membership rows (link + status badge)
2. Add picker when addable lists remain
3. "On all your manual lists" when fully covered

**Search results**:

1. Row badges mirroring outreach ("In outreach")
2. Header: outreach button + list picker when rows selected (picker excludes lists where every selected prospect is already a member)
3. Expanded row: outreach button + list picker (lists already joined hidden)

## Out of scope

- Remove from list on prospect detail
- Smart list membership display
- Create-new-list from picker

## Testing

- Unit: `ProspectListMembershipServiceTest`
- Feature: `ProspectShowTest`, `SearchShowTest` membership props
