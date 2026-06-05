# Report inline booking (Fastmail CalDAV) — Design Spec

**Date:** 2026-06-04  
**Status:** Implemented (v1)

---

## Goal

Let prospects book a fixed **30-minute review call** inline on the public audit report (`/r/{token}`), using a **shared agency Fastmail calendar** (CalDAV), with bookings stored in the scanner CRM and a **branded confirmation email** from the app.

## Decisions

| Topic | Decision |
|-------|----------|
| Calendar provider (v1) | Fastmail native calendar via **CalDAV** + app password |
| Event types | Single 30-min “review call” |
| Calendar ownership | One shared agency account (not per-operator) |
| Prospect UX | Inline slot picker on report `#book` section |
| CRM | `report_bookings` linked to `prospect_report_id` / `prospect_id` |
| Confirmation | Scanner Mailable (Fastmail SMTP); CalDAV event includes attendee |
| Fallback | Per-user `booking_url` / TidyCal when native booking disabled |
| Google Calendar | Phase 2 via `CalendarProvider` interface |

## Architecture

- `AgencyBookingSetting` singleton (encrypted app password, calendar path, working hours).
- `CalendarProvider` → `FastmailCalDavProvider` (production), `FakeCalendarProvider` (tests).
- `BookingAvailabilityService` merges working hours, min notice, and busy intervals.
- `ReportBookingService` books slot, persists row, creates CalDAV event, queues confirmation mail.
- Public JSON: `GET /r/{token}/slots`, `POST /r/{token}/book` (throttled).

## Out of scope (v1)

- OAuth with Fastmail (app password only)
- Cancellations/reschedule in UI
- Multiple event types or per-operator calendars
- Google Calendar provider
