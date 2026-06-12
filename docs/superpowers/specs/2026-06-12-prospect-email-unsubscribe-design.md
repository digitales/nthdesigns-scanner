# Prospect contact email & unsubscribe — Design Spec

**Date:** 2026-06-12  
**Status:** Approved  
**Scope:** Manual contact email on prospects, per-operator email suppression, operator and self-service unsubscribe, outreach integration.

**Approach:** Add `email` to `prospects`, new `suppressed_emails` table for per-user email blocking, `ProspectUnsubscribeService` as central owner, extend existing ignore/outreach patterns.

---

## Goal

Operators need a contact email address on each prospect for cold outreach (copy-paste workflow). When a recipient asks to be removed — either by replying to the operator or via a link in the outreach email — the system must:

1. Stop using that email for outreach across all of the operator's prospects.
2. Ignore the originating prospect (same behaviour as today's ignore flow).
3. Remove affected prospects from the outreach queue.

Google Places API does not provide email addresses. Entry is manual only (same pattern as phone/website on the prospect detail page).

---

## Decisions

| Topic | Decision |
|-------|----------|
| Email source | Manual entry only on prospect detail page |
| Email field | `prospects.email` — nullable, validated as email |
| Unsubscribe triggers | Operator action on prospect detail **and** public signed link in generated outreach emails |
| Unsubscribe scope | Per-operator (`user_id`); blocks email across all prospects sharing that address |
| Prospect on unsubscribe | Whole prospect ignored via existing `ProspectExclusionService` with new `Unsubscribed` reason |
| Reversal | "Include in scans again" on an `Unsubscribed` ignore also lifts email suppression (one action undoes both) |
| Outreach without email | Skip generation; report in flash `skipped` array |
| Suppressed email outreach | Skip generation; report in flash `skipped` array |
| Unsubscribe link placement | Appended to generated email body after AI generation (not left to LLM) |
| `to_email` in UI | Wire from `prospect.email` through API resources (`OutreachEmailCard` already displays it) |
| CSV export | Include `email` column |

---

## Architecture

### New components

| Component | Responsibility |
|-----------|----------------|
| Migration: `prospects.email` | Nullable contact email column |
| Migration: `suppressed_emails` | Per-user email suppression list |
| `SuppressionSource` enum | `operator`, `self_service` |
| `IgnoredProspectReason::Unsubscribed` | New ignore reason label: "Unsubscribed" |
| `ProspectUnsubscribeService` | Normalize email, suppress, ignore prospect, clean outreach selections, generate signed URLs, lift suppression |
| `ProspectUnsubscribeController` | `POST /prospects/{prospect}/unsubscribe` (auth) |
| `PublicUnsubscribeController` | `GET /unsubscribe` (signed URL, no auth) |
| `Public/Unsubscribe.jsx` | Confirmation page for self-service unsubscribe |
| `UnsubscribeProspectRequest` | Validates prospect has email before operator unsubscribe |

### Extended components

| Component | Change |
|-----------|--------|
| `Prospect` model | Add `email` to `$fillable` |
| `ProspectEnrichmentService` | Include `email` in editable fields |
| `UpdateProspectRequest` | Add `email` validation rule |
| `ProspectShowResource` | Expose `email`, `email_suppressed` flag |
| `OutreachEmailResource` | Add `to_email` from `prospect.email` |
| `GenerateOutreachEmailJob` | Skip if no email or suppressed; append unsubscribe footer to body |
| `OutreachController::generate` | Skip suppressed/no-email prospects in batch; report reasons in flash |
| `ProspectExclusionService::includeInScans` | When lifting an `Unsubscribed` ignore, call `ProspectUnsubscribeService::liftSuppression` |
| `ExportController` | Add `email` to CSV columns |
| `Prospect/Show.jsx` | Email field in edit form, suppression badge, unsubscribe button |

### Unchanged (reused)

- `ProspectExclusionService::ignore` — called by unsubscribe service
- `OutreachEmailCard.jsx` — already renders `to_email`
- Copy-paste outreach workflow — no in-app email sending
- Public route pattern — same as `/r/{token}` report links

---

## Data model

### `prospects` — new column

```
email   string, nullable, max 255
```

Position: after `phone`.

### `suppressed_emails`

```
id
user_id         FK → users, cascade on delete
email           string (normalized: lowercase, trimmed)
source          enum: operator, self_service
prospect_id     FK → prospects, nullable, null on delete
created_at
updated_at
```

Unique index: `(user_id, email)`.

### `IgnoredProspectReason` — new case

```
Unsubscribed = 'unsubscribed'   → label: "Unsubscribed"
```

---

## Unsubscribe behaviour

### `ProspectUnsubscribeService`

```php
isSuppressed(User $user, ?string $email): bool
unsubscribe(User $user, Prospect $prospect, SuppressionSource $source): void
signedUnsubscribeUrl(User $user, Prospect $prospect, string $email): string
liftSuppression(User $user, string $email): void
```

**`unsubscribe()` steps:**

1. Normalize email (lowercase, trim). Abort with validation error if prospect has no email.
2. `SuppressedEmail::firstOrCreate(['user_id' => $user->id, 'email' => $normalized], ['source' => $source, 'prospect_id' => $prospect->id])`.
3. `ProspectExclusionService::ignore($user, $prospect, IgnoredProspectReason::Unsubscribed)`.
4. Delete all `OutreachSelection` rows for this user where the prospect's email (normalized) matches.

Idempotent: repeating unsubscribe for the same email succeeds without error.

**`liftSuppression()`:** Delete `suppressed_emails` row for `(user_id, email)`. Called when operator reverses an `Unsubscribed` ignore via "Include in scans again".

### Operator unsubscribe

- **Route:** `POST /prospects/{prospect}/unsubscribe`
- **Auth:** `ProspectPolicy::update`
- **UI:** "Unsubscribe email" button on prospect detail — visible when email is set and not suppressed; confirmation dialog
- **Flash:** "Email unsubscribed. This contact won't receive outreach."

### Self-service unsubscribe

- **Route:** `GET /unsubscribe?prospect={id}&email={hash}&signature=...` (Laravel signed URL)
- **Auth:** None — public route alongside `/r/{token}`
- **Token:** `URL::signedRoute('unsubscribe', ['prospect' => $id, 'email' => $normalizedEmail])` — no expiry (permanent signed route, same durability expectation as report tokens)
- **On valid visit:** Resolve prospect owner (`prospect.search.user_id`), call `unsubscribe()` with `source: self_service`
- **Response:** Inertia `Public/Unsubscribe.jsx` — "You've been unsubscribed. You won't receive further emails from us."
- **Invalid signature:** 403 with friendly error page
- **Idempotent:** Revisiting shows same confirmation

### Cross-prospect blocking

Outreach eligibility checks (queue display, add-to-queue, `GenerateOutreachEmailJob`, batch generate):

| Condition | Result |
|-----------|--------|
| No email on prospect | Skip — reason: "no email" |
| Email on `suppressed_emails` for user | Skip — reason: "unsubscribed" |
| Prospect ignored | Skip (existing behaviour) |

When operator saves an email matching a suppressed address: allow save (record-keeping); show **"Email unsubscribed"** badge on detail page; outreach remains blocked.

### Reversal

When operator clicks **Include in scans again** and the ignore reason is `Unsubscribed`:

1. Existing `ProspectExclusionService::includeInScans()` removes the ignore row.
2. Also call `liftSuppression()` for the prospect's email (if set).

Prospect re-appears in scans and outreach is permitted again for that email.

---

## Outreach integration

### `to_email` wiring

`OutreachEmailResource::format()` adds:

```php
'to_email' => $email->prospect->email,
```

Eager-load `prospect` in `OutreachQueueLoader` if not already loaded.

### Unsubscribe footer

After AI generation in `GenerateOutreachEmailJob`, append before saving:

```
---
If you'd prefer not to hear from us, unsubscribe here: {signed_url}
```

Signed URL from `ProspectUnsubscribeService::signedUnsubscribeUrl()`. Footer is deterministic — never included in LLM prompt.

### Batch generate skipped reporting

Extend existing `skipped` flash array in `OutreachController::generate()`:

```
"Acme Ltd (no email)"
"Beta Co (unsubscribed)"
```

---

## UI

### Prospect detail (`Prospect/Show.jsx`)

| Element | Behaviour |
|---------|-----------|
| Email input | In editable details form alongside phone, website, address |
| Suppression badge | Shown when `email_suppressed` is true |
| Unsubscribe button | Visible when email set and not suppressed; POST with confirmation |
| Ignored banner | Existing banner shows "Unsubscribed" reason label |

### Public unsubscribe (`Public/Unsubscribe.jsx`)

Minimal standalone page (no auth layout). Confirmation message only.

### Outreach queue

No structural changes. `OutreachEmailCard` displays `To:` once backend supplies `to_email`.

---

## Error handling

| Scenario | Behaviour |
|----------|-----------|
| Unsubscribe prospect with no email | 422 validation error |
| Already suppressed | Idempotent success |
| Invalid signed URL | 403 friendly error page |
| Generate with suppressed/no email | Skip; include in flash |
| Save email matching suppressed address | Allow; show warning badge |

---

## Testing

| Test file | Covers |
|-----------|--------|
| `ProspectUnsubscribeServiceTest` (unit) | Normalization, idempotent suppression, cross-prospect selection cleanup, signed URL generation |
| `ProspectUnsubscribeTest` (feature) | Operator unsubscribe creates suppression + ignore; selections removed |
| `PublicUnsubscribeTest` (feature) | Signed URL visit, idempotent revisit, invalid signature rejected |
| `GenerateOutreachEmailJobTest` (extend) | Skips no-email and suppressed; footer appended |
| `ProspectShowTest` (extend) | Email CRUD, suppression badge, unsubscribe button |
| `ExportProspectsTest` (extend) | CSV includes email column |
| `ProspectExclusionServiceTest` or feature (extend) | Include-in-scans lifts suppression when reason was Unsubscribed |

---

## Out of scope (v1)

- Automated email discovery (website scraping)
- In-app email sending (remains copy-paste)
- Global cross-user email suppression
- Separate "lift suppression without un-ignoring" action
- Unsubscribe analytics/reporting dashboard
