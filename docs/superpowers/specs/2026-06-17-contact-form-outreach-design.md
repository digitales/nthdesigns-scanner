# Contact form & LinkedIn outreach — Design Spec

**Date:** 2026-06-17  
**Status:** Approved  
**Scope:** Auto-detect contact forms and LinkedIn URLs during site audits; operator confirmation/overrides; alternative outreach channels (contact form message + LinkedIn DM) in the existing outreach queue when email is unavailable or operator switches channel.

**Approach:** Unified outreach records with a `channel` column on `outreach_emails`; shared Playwright detection module (`contact-detect.js`) piggybacking on audit/CMS paths; AI-generated form messages and templated LinkedIn DMs.

---

## Goal

Some prospects publish a contact form on their website but no public email address. Today these prospects are skipped at outreach generate time with reason `"no email"`, even though they are otherwise good leads with reports ready.

Operators need to:

1. **Detect** contact forms and LinkedIn profiles automatically during existing site loads.
2. **Confirm or override** detection on the prospect detail page.
3. **Generate outreach copy** for contact form submission and LinkedIn DM when email is not the chosen channel.
4. **Batch alongside email prospects** in the same outreach queue, with separate cards per channel.

Google Places API does not provide email addresses. This spec does **not** attempt automated email discovery — it provides alternative channels instead.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Primary goal | Alternative channels, not email scraping |
| Channels (v1) | Contact form message + LinkedIn DM; no phone scripts |
| Form detection | Auto-detect during Playwright audit/CMS pass; operator confirms or overrides |
| LinkedIn URL | Auto-suggest from website social links; manual entry; operator override |
| Workflow | Same outreach queue; separate cards per channel (not mixed into email card) |
| Channel routing | Email takes priority when `prospects.email` is set and channel is `auto` or `email`; form + LinkedIn when no email (form path confirmed) or operator sets channel to `alternative` |
| Form message generation | AI (Claude via OpenRouter), adapted prompt — ≤120 words, message-box tone |
| LinkedIn DM generation | Fixed template with pitch-angle variant; no LLM call |
| Unsubscribe | Email channel only; no unsubscribe footer on form or LinkedIn copy |
| Form submission | Copy-paste only; no automated submission |

---

## Architecture

### New components

| Component | Responsibility |
|-----------|----------------|
| `scripts/contact-detect.js` | Heuristics: contact forms, contact page URL, visible/mailto emails, LinkedIn links |
| `scripts/contact-detect.test.js` | Node unit tests on HTML fixtures |
| `ContactDetectionPayload` (PHP) | Normalise detection JSON from audit/detect script |
| `OutreachChannelResolver` | Determine which channels to generate per prospect |
| `OutreachFormMessageGeneratorService` | AI prompt for contact form messages |
| `OutreachLinkedInTemplateService` | Template DM per pitch angle |
| `OutreachFormCard.jsx` | Contact form card on `/outreach` and prospect detail |
| `OutreachLinkedInCard.jsx` | LinkedIn card on `/outreach` and prospect detail |

### Extended components

| Component | Change |
|-----------|--------|
| `scripts/audit.js` | Call `detectContactSignals(page)` after navigation; add `contact` to payload |
| `scripts/detect-cms.js` | Run contact detection alongside CMS (same page load) |
| `DetectCmsJob` | Persist `contact_signals` from detect script output (consider rename to `DetectSiteMetadataJob` in implementation) |
| `AuditSiteJob` | Persist `contact_signals` from audit payload when present |
| `ScorePlaceJob` | Ensure contact detection runs when URL known and no full audit |
| `outreach_emails` table | Add `channel` enum column (`email`, `contact_form`, `linkedin`); nullable `subject_line` for non-email channels |
| `prospects` table | Add `linkedin_url`, `contact_page_url`, `use_form_outreach`, `outreach_channel`, `contact_signals` |
| `GenerateOutreachEmailJob` | Accept `channel` param; dispatch form/LinkedIn generation; skip email when channel routing excludes it |
| `OutreachController::generate` | Resolve channels per prospect; extend skip reasons |
| `ProspectEnrichmentService` | Include new editable fields |
| `ProspectShowResource` | Expose contact signals, channel fields, suggested emails |
| `OutreachQueueLoader` | Load latest outreach row per channel per prospect |
| `ExportController` | Add contact/LinkedIn columns |
| `ProspectUnsubscribeService::outreachSkipReason` | Replace bare `"no email"` with channel-aware check via `OutreachChannelResolver` |

### Unchanged (reused)

- Copy-paste outreach workflow — no in-app sending or form submission
- `suppressed_emails` / unsubscribe — email channel only
- Pitch angle resolution, CPC benchmark injection, report URL embedding
- Outreach queue selection model (`outreach_selections`)
- Public report page — no contact detection surfaced

---

## Contact detection

### When

During any Playwright navigation that already loads the prospect site:

- Full accessibility audit (`audit.js`) — same page load as axe/CMS
- CMS-only path (`detect-cms.js`) — same page load as CMS detection

No additional page fetch beyond what audit/CMS already performs.

### Signals detected

| Signal | Method |
|--------|--------|
| Contact form | `<form>` with email/message fields; known plugins (Contact Form 7, Gravity Forms, WPForms); generic patterns (`action` containing `contact`, `wpcf7`, etc.) |
| Contact page URL | URL of page where form found, or `/contact` (and variants) from nav/footer links |
| Visible emails | Regex on page text + `mailto:` hrefs — stored as **suggestions only**, never auto-written to `prospects.email` |
| LinkedIn URL | `a[href*="linkedin.com/company"]` or `a[href*="linkedin.com/in/"]` in footer/social areas |

### Stored JSON (`prospects.contact_signals`)

```json
{
  "status": "detected",
  "url": "https://example.com/contact",
  "has_contact_form": true,
  "contact_page_url": "https://example.com/contact",
  "suggested_emails": ["info@example.com"],
  "linkedin_url": "https://linkedin.com/company/acme",
  "confidence": "high",
  "signals": ["form:contact-form-7", "link:footer-linkedin"],
  "detected_at": "2026-06-17T12:00:00+00:00"
}
```

On failure: `{ "status": "failed", "error": "...", "url": "..." }`.

### Operator fields

| Field | Type | Default | Purpose |
|-------|------|---------|---------|
| `linkedin_url` | string, nullable | Pre-filled from detection | Editable LinkedIn profile/company URL |
| `contact_page_url` | string, nullable | Pre-filled from detection | Editable contact form page URL |
| `use_form_outreach` | enum: `auto`, `yes`, `no` | `auto` | Operator override for form path eligibility |
| `outreach_channel` | enum: `auto`, `email`, `alternative` | `auto` | Force email-only or form+LinkedIn |

### Form path confirmed logic

Form outreach is eligible when **all** of:

1. `use_form_outreach` is not `no`
2. AND one of:
   - `use_form_outreach` is `yes`
   - OR (`use_form_outreach` is `auto` AND `contact_signals.has_contact_form` is true AND `contact_signals.suggested_emails` is empty)

### Prospect detail UI

- **Contact signals block** (operator-only, like Technology/CMS): form detected, confidence, contact page link, suggested emails with one-click "Use as email" (copies to email field, does not auto-save)
- **Confirmation banner** when detection suggests form and operator has not confirmed: *"Contact form detected — use form outreach?"* with Confirm / Dismiss (sets `use_form_outreach` to `yes` / `no`)
- **Channel selector**: Auto / Email / Form + LinkedIn
- Editable `linkedin_url`, `contact_page_url`

---

## Channel routing

`OutreachChannelResolver::channelsFor(Prospect $prospect): array` returns channel list to generate.

| Condition | Channels generated |
|-----------|-------------------|
| `outreach_channel` is `email`, OR (`auto` AND email present AND not forced to `alternative`) | `[email]` |
| `outreach_channel` is `alternative`, OR (`auto` AND no email AND form path confirmed) | `[contact_form, linkedin]` |
| Otherwise | `[]` — skip with `"no contact path"` |

**Email priority:** When `prospects.email` is set and `outreach_channel` is `auto`, generate email only. Form and LinkedIn cards are hidden unless operator sets `outreach_channel` to `alternative`.

**Unsubscribe:** `suppressed_emails` blocks email channel only. Form and LinkedIn generation proceeds regardless.

**LinkedIn without URL:** Generate form card; LinkedIn card renders with *"Add LinkedIn URL"* prompt linking to prospect detail. Does not skip the prospect.

---

## Outreach generation & storage

### `outreach_emails.channel`

```
channel   enum: email, contact_form, linkedin   (default: email)
```

Non-email rows reuse `email_body` for paste-ready message. `subject_line` nullable (optional form subject line if operator wants one; otherwise null).

Unique constraint per generation: one row per `(prospect_id, user_id, pitch_angle, channel)` — same dedup pattern as email today.

### Contact form message (AI)

New `OutreachFormMessageGeneratorService`:

- Inputs: business name, niche, city, pitch angle, combined score, flags, CPC benchmark, report URL, agency name
- Prompt rules: British English, ≤120 words, message-box tone (no assumed recipient name), one CTA, report link when available
- **No unsubscribe footer**
- Returns: `{ message_body, model_used, prompt_tokens, completion_tokens, pitch_angle }`

### LinkedIn DM (template)

`OutreachLinkedInTemplateService::render(Prospect, options)`:

```
Hi — I put together a quick audit of {business_name}'s online presence
({angle_context}). Worth a look if you're reviewing your digital setup:
{report_url}

— {agency_name}
```

`angle_context` varies by pitch angle (GBP / accessibility / both). Hard cap ~300 characters. No LLM call.

### Jobs

Extend `GenerateOutreachEmailJob` with a `channel` constructor argument. Batch generate dispatches one job per resolved channel:

```php
GenerateOutreachEmailJob::dispatch($prospect, $user, $options, channel: 'email');
GenerateOutreachEmailJob::dispatch($prospect, $user, $options, channel: 'contact_form');
GenerateOutreachEmailJob::dispatch($prospect, $user, $options, channel: 'linkedin');
```

Email job path unchanged for `channel: email` including unsubscribe footer append.

---

## Queue UI (`/outreach`)

Same queue chips on the left. Right column shows **separate cards per channel** per prospect:

| Card | Header | Actions |
|------|--------|---------|
| Email | `To: owner@…` | Copy body, subject line, Mark sent, Got response, Preview report |
| Contact form | `Via: example.com/contact` | Copy message, open contact page (new tab), Mark sent, Got response |
| LinkedIn | `Via: linkedin.com/company/…` | Copy DM, open profile (new tab if URL set), Mark sent, Got response |

Shared across cards: score badge, angle pill, report preview link.

When channel routing hides email (alternative path), email cards for that prospect are not shown in queue even if historically generated.

### Batch skip flash (extended)

```
"Acme Ltd (no contact path)"
"Beta Co (unsubscribed)"
"Gamma Co (no report)"
```

---

## Edge cases

| Scenario | Behaviour |
|----------|-----------|
| No form detected, no email | Skip `"no contact path"`; detail shows guidance |
| Form detected, `use_form_outreach: no` | Form path blocked; skip unless email added or channel changed |
| Suggested email in `contact_signals` | Show hint with one-click copy to email field; never auto-fill |
| Operator switches email → alternative after email generated | Historical email row kept in DB; queue shows form + LinkedIn only |
| Detection missed (cookie banner, lazy load) | Operator sets `contact_page_url` + `use_form_outreach: yes` manually |
| Detection script fails | `contact_signals.status = failed`; manual entry still works |
| Prospect has email + form on site | Email path only (unless operator forces `alternative`) |

---

## CSV export

Add columns:

- `outreach_channel`
- `contact_page_url`
- `linkedin_url`
- Latest `contact_form` message body (if generated)
- Latest `linkedin` message body (if generated)

Existing `email` column unchanged.

---

## Error handling

| Scenario | Behaviour |
|----------|-----------|
| Generate with no viable channel | Skip; `"no contact path"` in flash |
| Generate email with suppressed address | Skip; `"unsubscribed"` (existing) |
| OpenRouter failure on form message | Job retries; log error; no partial row |
| LinkedIn template with missing report URL | Omit report line; still generate DM |
| Invalid LinkedIn URL on save | Validation error on prospect update |

---

## Testing

| Test | Covers |
|------|--------|
| `contact-detect.test.js` | Form heuristics, LinkedIn parsing, mailto extraction |
| `ContactDetectionPayloadTest` | PHP normalisation |
| `OutreachChannelResolverTest` | Email priority, alternative override, form confirmed logic |
| `GenerateOutreachEmailJobTest` (extend) | Multi-channel dispatch; skip reasons |
| `OutreachFormMessageGeneratorTest` | Prompt shape (mock OpenRouter) |
| `OutreachLinkedInTemplateTest` | Template per pitch angle; char limit |
| `OutreachControllerTest` (extend) | Mixed batch; flash messages |
| `ProspectShowTest` (extend) | Detection banner, channel selector, URL CRUD |
| `ExportProspectsTest` (extend) | New CSV columns |

---

## Out of scope (v1)

- Automated contact form submission
- Auto-writing scraped emails to `prospects.email`
- Phone call scripts
- LinkedIn automation (connection requests, InMail API)
- Unsubscribe/opt-out for form submissions
- Per-form field mapping (name, email, phone pre-fill)
- Scoring or pitch angle changes based on contact channel
- Search table filters ("contact form only")
- Public report surfacing of contact detection

---

## Flow summary

```text
Audit/Detect ──► contact_signals JSON + suggested LinkedIn
                      │
                      ▼
              Operator confirms/overrides
                      │
                      ▼
         Outreach queue (same as today)
                      │
                      ▼
         OutreachChannelResolver per prospect
           ├─ email        → AI email + unsubscribe footer
           ├─ contact_form → AI short message
           └─ linkedin     → template DM
                      │
                      ▼
         Separate cards on /outreach + prospect detail
```
