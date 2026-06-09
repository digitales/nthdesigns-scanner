# Calendar dropdown display names — Design Spec

**Date:** 2026-06-09  
**Status:** Implemented

---

## Goal

Show human-readable Fastmail calendar names in the agency booking settings dropdown instead of CalDAV URL UUIDs. Append a short ID suffix only when two or more calendars share the same display name.

## Problem

`AgencyBookingSettingsCard` already renders `cal.name`, but `FastmailCalDavClient::listCalendars()` derives `name` from the URL path basename. Fastmail uses UUID folder names in CalDAV URLs, so the dropdown shows values like `3f2a1b9c-e4d5-6789-abcd-ef0123456789`.

The PROPFIND request already includes `<d:displayname/>`; the response is not parsed.

## Decisions

| Topic | Decision |
|-------|----------|
| Label source | CalDAV `displayname` from PROPFIND multistatus |
| Fallback | URL path basename (`id`), URL-decoded (`%40` → `@`) |
| Collision format | `{display_name} ({shortId})` — e.g. `Work (3f2a1b9c)` |
| When to show short ID | Only when two or more calendars share the same `display_name` |
| Short ID rule | First 8 characters of `id` (URL basename UUID) |
| Option value | Unchanged — `cal.url` |
| Frontend changes | None — backend populates `name` correctly |
| Approach | Backend label formatting (parse XML in `CalDavXmlParser`, format in `FastmailCalDavClient`) |

## Data flow

1. User clicks **Test connection** → `AgencyBookingService::testConnection()` → `FastmailCalDavClient::listCalendars()`.
2. PROPFIND on calendar home (unchanged request body).
3. `CalDavXmlParser::parsePropfindResponses()` returns `{ href, displayname }` pairs from multistatus XML.
4. For each calendar href (excluding home collection):
   - `id` = URL path basename (stable identifier)
   - `display_name` = parsed `displayname`, or `id` if missing/empty
   - `name` = `display_name`, with ` ({shortId})` appended only on display-name collision
   - `url` = full CalDAV calendar URL (unchanged)
5. Calendars flash to session → dropdown shows friendly names.

## Implementation

### `CalDavXmlParser::parsePropfindResponses(string $xml)`

Returns `list<array{href: string, displayname: ?string}>`:

- Split multistatus into individual `<d:response>` blocks (regex, consistent with existing parser style).
- Per block: extract `href` and `displayname` (namespace-agnostic patterns).
- Decode XML entities on `displayname` via `html_entity_decode(..., ENT_XML1)`.
- Return `displayname: null` when property absent or empty.

### `FastmailCalDavClient::listCalendars()`

- Map hrefs to calendars using parser output (match by href).
- Build `{ id, display_name, url }` per calendar.
- Run collision pass via private `formatCalendarLabels()`:
  1. Count `display_name` occurrences.
  2. Where count > 1, set `name = "{display_name} ({shortId})"`.
  3. Otherwise `name = display_name`.

### Files touched

| File | Change |
|------|--------|
| `app/Services/Calendar/CalDavXmlParser.php` | Add `parsePropfindResponses()` |
| `app/Services/Calendar/FastmailCalDavClient.php` | Parse display names; collision formatting |
| `tests/Unit/CalDavXmlParserTest.php` | Parser tests |
| `tests/Unit/FastmailCalDavClientTest.php` (or inline parser tests) | Label collision tests |

## Error handling & edge cases

| Scenario | Behaviour |
|----------|-----------|
| `displayname` missing or empty | Fall back to `id` as `display_name` |
| Two+ calendars share a name | Append ` (shortId)` to all colliding entries |
| PROPFIND returns no calendar hrefs | Return `[]` (unchanged) |
| Home collection href | Skip (unchanged filter) |
| Malformed XML | No `displayname` per response; href-only fallback |
| Special characters in name | Decoded from XML; rendered as-is in `<option>` text |

No new user-facing error messages. Missing `displayname` degrades to current UUID behaviour rather than failing the connection test.

## Testing

**Unit — `CalDavXmlParserTest`:**

1. Extracts `href` + `displayname` from realistic namespaced multistatus XML.
2. Missing `displayname` → `null`.
3. XML entities decode correctly (`Tom &amp; Jerry` → `Tom & Jerry`).

**Unit — label formatting:**

4. Unique display names → `name` equals `display_name`, no suffix.
5. Duplicate display names → both entries get ` (shortId)` with correct 8-char prefix.
6. Empty `displayname` → `display_name` and `name` fall back to `id`.

**Manual smoke test:** Settings → Agency booking → Test connection with 2+ Fastmail calendars → dropdown shows names like `Work`, not UUIDs.

## Out of scope

- Persisting discovered calendars across page reloads (flash session only today)
- Friendly name on single-calendar URL text field
- Frontend label formatting logic
- Per-calendar PROPFIND requests (N+1 HTTP)
