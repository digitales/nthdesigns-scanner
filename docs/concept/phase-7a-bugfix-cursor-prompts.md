# Phase 7a Bugfix — Cursor Prompts

Companion to `warmup-feature-detailed-notes.md`. Phase 7a (mailbox management +
warmup engine) is already implemented in the working tree but uncommitted, and
a review against the spec turned up seven issues worth fixing before this
ships. Each prompt below is scoped to a coherent set of files so it can be run
as its own Cursor composer session and reviewed as its own diff.

Run them in order — P1 and P4 touch `WarmupSendService` first, and the later
prompts assume that file is already in its fixed state. Commit after each one
rather than batching them; some of these touch the same files and you want
clean diffs to review against the existing unit tests.

After every prompt: `php artisan test --filter=Warmup`.

---

## P1 — Provider-agnostic spam/junk folder detection + reliable Message-ID

**Context:** `WarmupSendService::processInbox()` checks a hardcoded folder
list (`Spam`, `Junk`, `INBOX.Spam`, `INBOX.Junk`). Gmail's spam folder is
actually `[Gmail]/Spam` and Outlook's is `Junk Email` — neither matches, so
the spam-rescue signal (the centerpiece of this feature) silently never
fires for the two providers we explicitly support. Separately,
`sendWarmupEmail()` reads the Message-ID back off the Symfony `Email` object
after sending and falls back to `uniqid()` if it's missing; if that fallback
ever fires, the ID stored in `warmup_sends.message_id` won't match the ID
actually in the sent email's headers, so `processInbox()` can never match the
received message back to its `WarmupSend` row.

**Required changes:**

1. In `WarmupSendService::processInbox()`, stop hardcoding folder names.
   Instead, list the account's actual folders (`$client->getFolders()` from
   webklex/laravel-imap) and treat any folder whose name contains `spam` or
   `junk` (case-insensitive) as a spam folder, excluding INBOX itself. Keep
   the existing try/catch per folder for providers with no spam folder at
   all.
2. In `sendWarmupEmail()`, generate the Message-ID yourself *before* sending
   instead of reading it back afterward: something like
   `sprintf('<%s@%s>', (string) Str::uuid(), parse_url(config('app.url'), PHP_URL_HOST))`,
   set it via `$email->getHeaders()->addIdHeader('Message-ID', $messageId)`,
   then store that exact same string (minus angle brackets) on the
   `WarmupSend` row. Delete the post-send read-back and the `uniqid()`
   fallback entirely — there should be no path where the stored ID can
   diverge from the header actually sent.
3. Update `WarmupSendServiceTest`: add cases for folder detection against
   folder name fixtures `"[Gmail]/Spam"`, `"Junk Email"`, `"INBOX.Junk"`, and
   one account with no spam folder. Add a case asserting the stored
   `message_id` exactly matches the value set on the outgoing header (not
   whatever the transport reports back).

---

## P2 — Respect send window / weekend settings, fix seed pileup

**Context:** `ProcessWarmupJob` ignores `send_window_start`,
`send_window_end`, and `send_on_weekends` entirely — it dispatches regardless
of the day, and staggers sends with unbounded `rand(5,30)` minute gaps. At
the default volume (50/day) that's ~14.6 hours of cumulative delay, which
blows straight through the 08:00–18:00 default window and into the evening;
at higher tiers (up to 200/day) it's worse. Separately, `getSeedPool()` only
excludes seeds used in the last 24h at the *start* of the job — within a
single run, `$seeds->random()` is independent per send, so once volume
exceeds seed count, the same seed mailbox can get hit a dozen+ times in one
day, which is its own anomaly signal on the seed side.

**Required changes:**

1. At the top of the per-outbox loop in `ProcessWarmupJob::handle()`, skip
   the mailbox entirely if `! $mailbox->send_on_weekends && now()->isWeekend()`.
2. Replace the unbounded stagger with a bounded distribution across the
   mailbox's actual send window: compute `windowMinutes` from
   `send_window_start`/`send_window_end`, divide by `volume` to get a base
   gap, and add jitter within each slot (cap jitter so no send can land past
   `send_window_end`). If `volume` doesn't fit the window even at a 1-minute
   floor, log a warning and compress proportionally rather than overflowing
   into the next day.
3. Fix seed distribution: instead of `$seeds->random()` per iteration, shuffle
   the eligible seed collection once per job run and cycle through it
   (reshuffling each full lap), so no seed receives more than
   `ceil($volume / $seeds->count())` sends in a single run.
4. Update `ProcessWarmupJobTest`: assert no dispatch happens on a weekend when
   `send_on_weekends` is false, assert every dispatched delay resolves to a
   time within the configured window, and assert per-seed send counts stay
   within the expected ceiling for a volume greater than seed count.

---

## P3 — Surface connection failures instead of failing silently

**Context:** `SendWarmupEmailJob` and `ProcessWarmupInboxJob` retry a few
times on failure (IMAP/SMTP errors — e.g. a revoked app password) and then
land in `failed_jobs`. Nothing updates the mailbox's `status`, even though
`failed` is a documented status value, and nothing gives the user any
visibility — the mailbox just quietly stops warming.

**Required changes:**

1. Migration: add `consecutive_failures` (`unsignedSmallInteger`, default 0)
   to `warmup_mailboxes`. Add a new `warmup_alerts` table: `id`,
   `warmup_mailbox_id` (FK, cascade delete), `type` (enum:
   `connection_failed`, `ready`, `at_risk`), `message` (text),
   `created_at`, `read_at` (nullable timestamp).
2. Add a `WarmupAlert` model with a `belongsTo(WarmupMailbox::class)`
   relation.
3. Add `failed(Throwable $e)` to both `SendWarmupEmailJob` and
   `ProcessWarmupInboxJob`: load the relevant outreach mailbox, increment
   `consecutive_failures`, and once it crosses 3, set
   `status = 'failed'`, `warmup_enabled = false`, and create a
   `connection_failed` `WarmupAlert` with a plain-English message (sanitised
   per P4 — no raw exception text). On a successful `handle()` completion,
   reset `consecutive_failures` to 0.
4. `WarmupController::index()` includes unread alert counts per
   mailbox (`has_alert`) so the dashboard can flag it. Full
   notification UI is delivered in Phase 7b (`NotificationBell`,
   `WarmupNotifierService`) — P3 makes failures durable and queryable.
5. Tests: add cases to `tests/Unit` covering the failure-threshold
   transition to `status = failed`, and the reset back to 0 on a subsequent
   success.

---

## P4 — Stop credentials from leaking into exceptions and logs

**Context:** `Transport::fromDsn()` builds a DSN string containing the
URL-encoded SMTP password. If a send throws — anywhere between
`Transport::fromDsn()` and `$mailer->send()` — the exception message can
carry that DSN straight into `failed_jobs` or the application log, which
directly contradicts the notes' "never stored plain-text, never logged"
requirement. The existing `testImap`/`testSmtp` catch blocks have the same
exposure: they pass the underlying provider error message through via
`$e->getMessage()` untouched.

**Required changes:**

1. Add a small helper (e.g. `WarmupCredentialScrubber::scrub(string $message): string`)
   that strips anything matching a `scheme://user:pass@host` pattern, and run
   every caught exception message through it before it's used in a new
   exception, logged, or stored in a `WarmupAlert`.
2. Wrap every `Transport::fromDsn(...)` call and `$mailer->send($email)` call
   in `WarmupSendService` in try/catch; re-throw a scrubbed
   `WarmupTransportException` rather than letting the original exception
   propagate.
3. Apply the same scrubbing inside `WarmupMailboxService::testImap()` and
   `testSmtp()` before the `RuntimeException` is constructed.
4. Test: force a transport failure with a fixture DSN containing a known
   password string, and assert that string never appears anywhere in the
   exception chain that reaches the caller.

---

## P5 — Status integrity and reply threading

**Context:** `WarmupHealthCheckJob` only ever promotes a mailbox to `ready`;
if the score later drops (a seed account gets suspended, deliverability
craters), the mailbox stays `ready` forever with a red score sitting next to
a green badge. Separately, `replyToWarmupEmail()` sets `In-Reply-To` but not
`References`, which Gmail's threading algorithm weights more heavily.

**Required changes:**

1. Add `at_risk` to the `warmup_mailboxes.status` enum (migration).
2. Add a single source of truth for the score → status mapping, e.g.
   `WarmupMailbox::statusForScore(int $score, int $daysWarming, int $rampDays): string`,
   implementing: `ready` requires score ≥ 80 AND `daysWarming >= rampDays`;
   `at_risk` is score < 50 once a mailbox has *previously* reached `warming`
   or `ready`; otherwise `warming`. Use this method from
   `WarmupHealthCheckJob` instead of the inline `if` it has now, so the
   dashboard, detail page, and job can't drift out of sync with each other.
3. Allow `WarmupHealthCheckJob` to move a mailbox backward (`ready` →
   `at_risk`/`warming`) when the score drops, not just forward. When this
   happens, create an `at_risk` `WarmupAlert` (from P3) if one doesn't
   already exist for the current dip.
4. In `WarmupSendService::replyToWarmupEmail()`, add a `References` header
   with the same value as `In-Reply-To`.
5. Tests: add `WarmupHealthCheckJobTest` covering ready→at_risk on a score
   drop and at_risk→ready on recovery once ramp days are satisfied; update
   `WarmupSendServiceTest` to assert the `References` header is present.

---

## P6 — Bound IMAP scans to new mail only

**Context:** `WarmupSendService::processInbox()` fetches every message in
every folder on every run with no date or UID filter. Fine at today's
volume; once the shared seed pool (Phase 7c) means a seed mailbox is
receiving warmup traffic from many outboxes, this becomes a linearly growing
scan on every run and an easy way to get throttled by the provider.

**Required changes:**

1. In `processFolder()`, filter the message query using
   webklex/laravel-imap's `since()` builder method, bounded by
   `$mailbox->last_imap_check_at` (fall back to `now()->subDays(2)` if null,
   i.e. first run).
2. Keep the existing match against `WarmupSend` by `message_id` +
   `to_mailbox_id` as the authority for whether a message is ours — the
   `since()` filter is purely to shrink the candidate set, not to replace
   the matching logic.
3. Only update `last_imap_check_at` after all folders for a mailbox have
   been processed without throwing, so a mid-scan failure doesn't cause the
   next run to skip messages that arrived in the gap.
4. Test: assert the IMAP query is built with a `since()` filter matching
   `last_imap_check_at`, and that a message older than the filter is not
   re-processed on a second run.

---

## P7 — Enforce the tier feature matrix

**Context:** The notes document a tier matrix (Solo: 1 outreach / 3 seed
mailboxes, Agency: 3 / 10, White label: 5 / 20, plus send-window
customisation and weekend volume control gated to Agency+), but
`WarmupController::store()` has no concept of it — any user can connect
unlimited mailboxes of either role today.

**Required changes:**

1. First check how subscription tier is modelled elsewhere in this codebase
   (look for an existing `tier`/`plan` column or service on `User`) and reuse
   it — don't introduce a second mechanism.
2. Add a `config/warmup_tiers.php` (or extend an existing tier config)
   mapping each tier to: max outreach mailboxes, max seed mailboxes, pool
   participation allowed, send-window customisation allowed, weekend volume
   control allowed — matching the table in the notes exactly.
3. In `WarmupController::store()`, before creating a mailbox, count the
   user's existing mailboxes by the requested role(s) and reject with a
   422/redirect-back error (clear message: "Your plan allows up to N
   outreach mailboxes") if the new mailbox would exceed the limit.
4. For ineligible tiers, ignore/ reset `send_window_start`/`send_window_end`/
   `send_on_weekends` to defaults server-side rather than erroring — these
   are conveniences, not hard caps, so fail soft.
5. Tests: `WarmupControllerTest` cases for at-limit (succeeds), over-limit
   (rejected) for both roles, across at least two tiers.

---

## Suggested order

P1 → P4 → P2 → P3 → P5 → P6 → P7. P1 and P4 both touch `WarmupSendService`
heavily, so doing them back to back keeps the diff legible. P3 depends on
P4's scrubbing helper existing. P7 is the most independent and can move
anywhere, but it's last here because it's pure policy and lowest risk if
deferred.
