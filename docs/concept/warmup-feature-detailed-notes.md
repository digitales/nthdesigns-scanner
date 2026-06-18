# Email Warmup Feature — Detailed Notes

Phase 7 of the nthdesigns Prospect Scanner build.
Last updated: 18 June 2026

---

## What It Is and Why It Exists

The warmup feature automatically builds sending reputation for a new outreach domain (`nthdesign.co.uk`) before cold emails go out. It exchanges low-volume, human-looking emails between the outreach mailbox and a set of seed mailboxes, training Gmail and other providers to trust the domain. Without it, cold emails from a new domain land in spam regardless of copy quality.

Built into the scanner rather than using a third-party tool (Instantly, Lemwarm) because:
- Needed now for nthdesign.co.uk
- Natural fit for the SaaS tier
- Seed network becomes a network effect -- every user's mailbox improves warmup quality for all other users

---

## The Three Layers

1. **Mailbox management** -- connecting and configuring mailboxes
2. **The warmup engine** -- the daily send/receive/rescue/reply cycle
3. **The seed network** -- where the other side of the exchange comes from (self-seeded in v1, shared pool in SaaS)

---

## Layer 1: Mailbox Management

Every mailbox is one of three types, or a combination:

- **Outreach mailbox** -- the domain being warmed (ross@nthdesign.co.uk). Will eventually send cold outreach.
- **Seed mailbox** -- receives and replies to warmup emails. Can be any account you control (Gmail, Outlook, etc.).
- **Both** -- a single mailbox can be both. Common for internal v1 use.

Credentials stored encrypted at rest via Laravel `encrypt()`. Never stored plain-text, never logged.

### Supported Providers

| Provider | IMAP | SMTP | Note |
|----------|------|------|------|
| Fastmail | imap.fastmail.com:993 | smtp.fastmail.com:587 | Requires app password |
| Gmail | imap.gmail.com:993 | smtp.gmail.com:587 | Requires app password + IMAP enabled |
| Outlook | outlook.office365.com:993 | smtp.office365.com:587 | Standard credentials |
| Generic | Manual entry | Manual entry | Any IMAP/SMTP provider |

### Connection Testing

Live IMAP login and SMTP auth attempted before save. Returns pass/fail per protocol. Save disabled until both pass.

### Per-Mailbox Configuration

- Target daily send volume (default 50, range 20-200)
- Ramp duration in days (default 14, range 7-30)
- Send window: start/end time (default 08:00-18:00)
- Weekend sending: on/off (default on at 50% weekday volume)
- Pool participation: opt in/out of shared seed network (Agency+ only, default on)

### Mailbox Statuses

`pending` → `warming` → `ready` (or `paused` / `failed` at any point)

---

## Layer 2: The Warmup Engine

Runs entirely via queued jobs on the `warmup` queue. Horizon supervisor: 2 workers, 256MB, 180s timeout. Separate from `scraping` and `auditing` queues.

### Ramp Schedule

Linear interpolation from 5/day on day 1 to `warmup_target_volume` at `warmup_ramp_days`.

| Days | Default volume |
|------|---------------|
| 1-3 | 5/day |
| 4-7 | 10/day |
| 8-10 | 20/day |
| 11-14 | 35/day |
| 15+ | 50/day |

Both target volume and ramp duration are configurable per mailbox.

### Daily Cycle

`ProcessWarmupJob` runs daily at 08:00. For each active outreach mailbox:

1. Calculates today's target volume from ramp schedule
2. Pulls seed pool (see Layer 3)
3. Dispatches one `SendWarmupEmailJob` per send, staggered with random 5-30 minute gaps
4. Schedules `ProcessWarmupInboxJob` to run 2 hours after the last send

Staggering is critical -- sending 20 emails in 3 minutes from a new domain triggers filters. Spreading across a morning looks human.

### Email Content

All warmup emails are plain text. No HTML, no links, no tracking pixels, no unsubscribe footer. Stored in `config/warmup_templates.php`:

- ~15 subject lines ("Quick question", "Following up", "Checking in", etc.)
- ~10 email body templates -- short generic business copy, varied topics, conversational tone
- ~8 reply templates -- short acknowledgement-style responses
- ~16 sender first names -- rotated to vary the from-name

Each send picks randomly from pools. Same subject/body pair avoided in quick succession.

### Inbox Processing

`ProcessWarmupInboxJob` runs 2 hours after sends. For each seed mailbox that received mail:

1. Connects via IMAP
2. Scans inbox and spam/junk folders (checks: Spam, Junk, INBOX.Spam, INBOX.Junk -- exceptions caught for missing folders)
3. Spam: moves to inbox, records `rescued_from_spam_at`
4. Inbox: marks as read, records `opened_at`
5. Dispatches `ReplyToWarmupEmailJob` with random 30-240 minute delay

The rescue step sends a strong positive signal to Gmail -- a user rescuing an email from spam is an explicit trust signal.

### Replying

`ReplyToWarmupEmailJob` sends a short reply from the seed mailbox back to the outreach mailbox. Sets `In-Reply-To` header correctly for threading. Records `replied_at` on the `WarmupSend`.

### Deliverability Scoring

`WarmupHealthCheckJob` runs daily at 09:00.

```
score = (inbox_delivered + rescued * 0.5) / total_sent * 100
```

Over trailing 7 days. Rescued emails get half weight (reached spam first -- better than bounce, not as good as clean delivery).

| Score | Status | Colour |
|-------|--------|--------|
| < 50 | At risk | Red |
| 50-79 | Warming | Amber |
| 80+ | Ready | Green |

Auto-promotes to `ready` when score >= 80 AND `days_warming` >= `warmup_ramp_days`.

---

## Layer 3: The Seed Network

### V1 -- Self-Seeded

Seed pool is mailboxes you connect yourself. For internal use: a Gmail, an Outlook, a second Fastmail -- all under your own control, connected via the same flow as the outreach mailbox (designated as Seed).

- Minimum recommended: 3 seed mailboxes
- Dashboard prompts if fewer than 3 connected
- `getSeedPool()` excludes any seed used by this outbox in the last 24 hours to avoid repeated pairs

### SaaS -- Shared Pool (Phase 7c)

Every mailbox with `is_pool_participant = true` joins the global seed pool. Selection excludes:

- Mailboxes belonging to the same user
- Mailboxes on the same domain as the outreach mailbox
- Mailboxes used as seeds for this outbox in the last 24 hours

Seed addresses never shown to other users. Sending user sees only: "Your warmup uses a network of X seed mailboxes."

Pool health monitored by a dedicated job. Alerts if pool < 50 active seeds. High-bounce seeds auto-excluded.

Network effect: more Agency+ users = larger shared pool = better warmup quality for all users. Same mechanic as Instantly's warmup network.

---

## Data Models

### `warmup_mailboxes`
```
id
user_id
email                   (string)
provider                (enum: fastmail, gmail, outlook, generic)
imap_host               (string)
imap_port               (smallint, default 993)
smtp_host               (string)
smtp_port               (smallint, default 587)
username                (string)
password_encrypted      (text)          -- Laravel encrypt()
is_outreach_mailbox     (boolean)
is_seed_mailbox         (boolean)
is_pool_participant     (boolean, default true)
warmup_enabled          (boolean, default false)
warmup_started_at       (timestamp, nullable)
warmup_target_volume    (smallint, default 50)
warmup_ramp_days        (smallint, default 14)
send_window_start       (time, default 08:00)
send_window_end         (time, default 18:00)
send_on_weekends        (boolean, default true)
status                  (enum: pending, warming, ready, paused, failed)
deliverability_score    (smallint, nullable)
last_imap_check_at      (timestamp, nullable)
created_at, updated_at
```

### `warmup_sends`
```
id
from_mailbox_id         (FK warmup_mailboxes)
to_mailbox_id           (FK warmup_mailboxes)
message_id              (string, indexed)
subject                 (string)
sent_at                 (timestamp)
opened_at               (timestamp, nullable)
replied_at              (timestamp, nullable)
rescued_from_spam_at    (timestamp, nullable)
status                  (enum: sent, opened, replied, rescued, bounced)
created_at
```

---

## Services

### `WarmupMailboxService`
- `connect(array $credentials): WarmupMailbox` -- validates, encrypts, saves
- `verifyConnection(WarmupMailbox): bool` -- live IMAP + SMTP test, throws RuntimeException on failure
- `getDailyVolume(WarmupMailbox): int` -- linear ramp calculation
- `getSeedPool(WarmupMailbox $outbox): Collection` -- returns eligible seeds, excludes recent pairs

### `WarmupSendService`
- `sendWarmupEmail(WarmupMailbox $from, WarmupMailbox $to): WarmupSend` -- SMTP send via Symfony Mailer with per-mailbox credentials
- `processInbox(WarmupMailbox): void` -- IMAP scan, rescue from spam, record opens
- `replyToWarmupEmail(WarmupSend, WarmupMailbox $from): void` -- sends reply with correct threading headers
- `calculateDeliverabilityScore(WarmupMailbox): int` -- trailing 7-day formula
- `getEstimatedReadyDate(WarmupMailbox): ?Carbon` -- based on days remaining in ramp

---

## Jobs

| Job | Queue | Schedule | Purpose |
|-----|-------|----------|---------|
| `ProcessWarmupJob` | warmup | Daily 08:00 | Calculates volume, selects seeds, dispatches sends |
| `SendWarmupEmailJob` | warmup | Staggered per pair | Sends one warmup email |
| `ProcessWarmupInboxJob` | warmup | 2hrs after sends | Scans inboxes, rescues spam, queues replies |
| `ReplyToWarmupEmailJob` | warmup | 30-240min delay | Sends reply from seed mailbox |
| `WarmupHealthCheckJob` | warmup | Daily 09:00 | Recalculates scores, auto-promotes to ready |
| `PurgeWarmupSendsJob` | warmup | Weekly | Deletes warmup_sends older than 30 days |

---

## UI Pages

### `/warmup` -- Dashboard

- Grid of mailbox cards: email, provider badge, role tags, status badge, score with traffic light, days warming, today's send count
- "Start Warmup" button (pending outreach mailboxes only, requires 2+ seeds)
- Pause / Resume toggle per card
- Empty state: "Add your outreach mailbox" CTA
- Seed count prompt banner if fewer than 3 seeds connected
- "Add Mailbox" button in header

### `/warmup/connect` -- Add Mailbox

- Provider radio cards (Fastmail / Gmail / Outlook / Other) with auto-fill on select
- App password hint per provider with link to provider's settings page
- Fields: email, username, password
- Role checkboxes: "Use as outreach mailbox" / "Use as seed mailbox" (both selectable)
- "Test connection" button -- live IMAP + SMTP check, inline pass/fail result
- Submit disabled until connection test passes
- Agency+ only: "Join shared seed network" toggle (default on)

### `/warmup/{id}` -- Mailbox Detail

- Score gauge (circular, 0-100) with traffic light colour
- Stats row: days warming, sends this week, replies received, spam rescues
- Estimated ready date (if warming)
- At-risk banner when score < 60: plain-English diagnosis + link to MXToolbox
- Send history table: sent_at, subject (truncated), status badge, opened/replied/rescued timestamps
- Last 30 days of sends retained

### Outreach Integration (`/outreach`)

- Banner if no Ready mailbox: "Your outreach domain isn't ready yet. Estimated ready: [date]." with link to `/warmup`
- If Ready: "Sending from ross@nthdesign.co.uk (Ready)" shown in outreach controls
- Agency+ with multiple Ready mailboxes: domain selector shown
- Soft gate only -- warning but not enforced

---

## Build Phases

### Phase 7a -- Mailbox management + basic engine (Cursor prompt ready)
Migrations, models, WarmupMailboxService, WarmupSendService, all six jobs, template config, /warmup dashboard, /warmup/connect, /warmup/{id}, Horizon warmup supervisor, unit tests.
**Exit criterion:** nthdesign.co.uk warming automatically with 3 seed mailboxes, deliverability score updating daily.

### Phase 7b -- Monitoring and outreach integration
Score trend chart (recharts), estimated ready date display, in-app notifications (database driver) for Ready/At Risk/Connection Failure, readiness gate on /outreach, at-risk banner with DNS copy, PurgeWarmupSendsJob.
**Exit criterion:** notified when domain hits Ready; /outreach reflects readiness state.

### Phase 7c -- SaaS shared seed network
is_pool_participant opt-in toggle, getSeedPool() cross-user exclusion logic, pool size display, admin pool health view at /admin/warmup-pool, pool health monitoring job, auto-exclusion of high-bounce seeds.
**Exit criterion:** two test accounts cross-seeding correctly; pool size displayed accurately.

---

## Dependencies

```bash
composer require webklex/laravel-imap
```

Horizon warmup supervisor config:
```php
'warmup-supervisor' => [
    'connection' => 'redis',
    'queue' => ['warmup'],
    'balance' => 'auto',
    'maxProcesses' => 2,
    'memory' => 256,
    'timeout' => 180,
],
```

---

## Tier Feature Matrix

| Feature | Solo £39/mo | Agency £89/mo | White label £149/mo |
|---------|-------------|---------------|---------------------|
| Outreach mailboxes | 1 | 3 | 5 |
| Seed mailboxes | 3 (self only) | 10 | 20 |
| Shared seed network | No | Yes | Yes |
| Send window customisation | No | Yes | Yes |
| Weekend volume control | No | Yes | Yes |
| Email notifications | No | Yes | Yes |
| Admin pool health view | No | No | Yes |
| Custom seed domain | No | No | Yes |

---

## Current Status

- Phase 7a Cursor prompt: written and saved (phase-7a-cursor-prompt.md)
- Phase 7b: specced, Cursor prompt not yet generated
- Phase 7c: specced, Cursor prompt not yet generated
- nthdesign.co.uk: registered, Fastmail configured, Postmaster Tools registered
- DNS records (SPF/DKIM/DMARC): must be added before warmup can start
