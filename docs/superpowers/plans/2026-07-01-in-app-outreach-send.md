# In-app outreach send — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let operators send cold outreach email one prospect at a time from `/outreach` via the warmed outreach mailbox, with accurate `sent_at`, tiered readiness gates, editable drafts, and generated vs sent copy history.

**Architecture:** Extract SMTP transport into `MailboxTransportFactory`; new `OutreachSendService` handles tier resolution, guardrails, sync send, and persistence; extend `outreach_emails` with `generated_*` / `sent_*` columns; email cards get **Send** + draft PATCH; form/LinkedIn keep Mark sent.

**Tech Stack:** Laravel 13, Symfony Mailer (existing warmup SMTP), Inertia + React, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-01-in-app-outreach-send-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `config/outreach.php` | `soft_daily_cap`, `smtp_timeout_seconds` |
| `app/Enums/OutreachSendSource.php` | `app`, `manual` |
| `app/Services/MailboxTransportFactory.php` | SMTP transport from `WarmupMailbox` |
| `app/Services/Outreach/OutreachSendReadiness.php` | Tier value object (`blocked` / `warn` / `allowed`) |
| `app/Services/Outreach/OutreachSendService.php` | Send tier, guardrails, SMTP send, persist |
| `database/migrations/*_add_outreach_send_columns.php` | New columns + backfill |
| `app/Models/OutreachEmail.php` | Fillable, casts, `fromMailbox()` |
| `app/Jobs/GenerateOutreachEmailJob.php` | Set `generated_*` on create |
| `app/Http/Controllers/OutreachEmailController.php` | `update`, `send`, restrict `markSent` |
| `app/Http/Requests/UpdateOutreachEmailRequest.php` | Draft validation |
| `app/Http/Requests/SendOutreachEmailRequest.php` | `confirm_warned` boolean |
| `app/Http/Resources/OutreachEmailResource.php` | History + send tier props |
| `app/Services/ProspectUnsubscribeService.php` | `bodyContainsUnsubscribeFooter()` |
| `app/Services/WarmupSendService.php` | Delegate to `MailboxTransportFactory` |
| `routes/web.php` | PATCH draft, POST send |
| `resources/js/Components/OutreachEmailCard.jsx` | Edit, Send, history UI |
| `resources/js/Pages/Warmup/components/WarmupReadinessBanner.jsx` | In-app send copy |
| `database/factories/OutreachEmailFactory.php` | `generated_*` defaults |
| `database/factories/WarmupMailboxFactory.php` | `ready()` state |
| `tests/Unit/Outreach/OutreachSendServiceTest.php` | Tier + guardrails |
| `tests/Feature/OutreachSendTest.php` | HTTP send flow |
| `tests/Feature/OutreachEmailControllerTest.php` | Draft PATCH, markSent restrictions |

---

### Task 1: Migration, enum, and model

**Files:**
- Create: `config/outreach.php`
- Create: `app/Enums/OutreachSendSource.php`
- Create: `database/migrations/2026_07_01_100000_add_outreach_send_columns_to_outreach_emails_table.php`
- Modify: `app/Models/OutreachEmail.php`

- [ ] **Step 1: Add config**

```php
<?php

return [
    'soft_daily_cap' => (int) env('OUTREACH_SOFT_DAILY_CAP', 20),
    'smtp_timeout_seconds' => (int) env('OUTREACH_SMTP_TIMEOUT', 15),
];
```

- [ ] **Step 2: Add enum**

```php
<?php

namespace App\Enums;

enum OutreachSendSource: string
{
    case App = 'app';
    case Manual = 'manual';
}
```

- [ ] **Step 3: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_emails', function (Blueprint $table) {
            $table->string('generated_subject')->nullable()->after('subject_line');
            $table->text('generated_body')->nullable()->after('email_body');
            $table->string('sent_subject')->nullable()->after('generated_body');
            $table->text('sent_body')->nullable()->after('sent_subject');
            $table->foreignId('from_mailbox_id')->nullable()->after('sent_body')
                ->constrained('warmup_mailboxes')->nullOnDelete();
            $table->string('smtp_message_id')->nullable()->after('from_mailbox_id');
            $table->string('send_source')->nullable()->after('smtp_message_id');
        });

        DB::table('outreach_emails')->whereNull('generated_body')->update([
            'generated_subject' => DB::raw('subject_line'),
            'generated_body' => DB::raw('email_body'),
        ]);

        DB::table('outreach_emails')
            ->whereNotNull('sent_at')
            ->whereNull('sent_body')
            ->update([
                'sent_subject' => DB::raw('subject_line'),
                'sent_body' => DB::raw('email_body'),
                'send_source' => 'manual',
            ]);
    }

    public function down(): void
    {
        Schema::table('outreach_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_mailbox_id');
            $table->dropColumn([
                'generated_subject', 'generated_body', 'sent_subject', 'sent_body',
                'smtp_message_id', 'send_source',
            ]);
        });
    }
};
```

- [ ] **Step 4: Update model**

Add to `$fillable`: `generated_subject`, `generated_body`, `sent_subject`, `sent_body`, `from_mailbox_id`, `smtp_message_id`, `send_source`.

Add cast: `'send_source' => OutreachSendSource::class`.

Add relation:

```php
public function fromMailbox(): BelongsTo
{
    return $this->belongsTo(WarmupMailbox::class, 'from_mailbox_id');
}
```

Add helper:

```php
public function wasEditedBeforeSend(): bool
{
    if ($this->sent_body === null) {
        return $this->generated_body !== null
            && $this->email_body !== $this->generated_body;
    }

    return $this->generated_body !== null
        && $this->sent_body !== $this->generated_body;
}
```

- [ ] **Step 5: Run migration**

Run: `php artisan migrate`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add config/outreach.php app/Enums/OutreachSendSource.php database/migrations app/Models/OutreachEmail.php
git commit -m "feat: add outreach send history columns and send source enum"
```

---

### Task 2: MailboxTransportFactory

**Files:**
- Create: `app/Services/MailboxTransportFactory.php`
- Modify: `app/Services/WarmupSendService.php`
- Test: `tests/Unit/MailboxTransportFactoryTest.php` (optional smoke — warmup tests cover transport)

- [ ] **Step 1: Create factory**

Move SMTP construction from `WarmupMailboxService::makeSmtpTransport` into `MailboxTransportFactory`, or delegate:

```php
<?php

namespace App\Services;

use App\Models\WarmupMailbox;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MailboxTransportFactory
{
    public function __construct(
        private WarmupMailboxService $mailboxService,
    ) {}

    public function make(WarmupMailbox $mailbox): TransportInterface
    {
        return $this->mailboxService->makeSmtpTransport($mailbox);
    }
}
```

- [ ] **Step 2: Refactor WarmupSendService**

Replace `createTransport()` body with:

```php
protected function createTransport(WarmupMailbox $from): TransportInterface
{
    try {
        return app(MailboxTransportFactory::class)->make($from);
    } catch (\Throwable $e) {
        throw WarmupTransportException::fromThrowable($e);
    }
}
```

- [ ] **Step 3: Run warmup tests**

Run: `php artisan test --filter=WarmupSendService`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/MailboxTransportFactory.php app/Services/WarmupSendService.php
git commit -m "refactor: extract MailboxTransportFactory for shared SMTP transport"
```

---

### Task 3: OutreachSendReadiness + OutreachSendService (unit tests)

**Files:**
- Create: `app/Services/Outreach/OutreachSendReadiness.php`
- Create: `app/Services/Outreach/OutreachSendService.php`
- Modify: `app/Services/ProspectUnsubscribeService.php`
- Create: `tests/Unit/Outreach/OutreachSendServiceTest.php`
- Modify: `database/factories/WarmupMailboxFactory.php`

- [ ] **Step 1: Add `ready()` factory state**

```php
public function ready(): static
{
    return $this->state(fn () => [
        'is_outreach_mailbox' => true,
        'is_seed_mailbox' => false,
        'warmup_enabled' => true,
        'warmup_started_at' => now()->subDays(14),
        'warmup_ramp_days' => 14,
        'status' => 'ready',
        'deliverability_score' => 85,
    ]);
}
```

- [ ] **Step 2: Add unsubscribe footer check**

In `ProspectUnsubscribeService`:

```php
public function bodyContainsUnsubscribeFooter(string $body, User $user, Prospect $prospect, string $email): bool
{
    $url = $this->signedUnsubscribeUrl($user, $prospect, $email);

    return str_contains($body, $url);
}
```

- [ ] **Step 3: Write failing unit tests**

```php
<?php

namespace Tests\Unit\Outreach;

use App\Enums\OutreachChannel;
use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\Outreach\OutreachSendService;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachSendServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_tier_blocked_when_score_below_sixty(): void
    {
        $user = User::factory()->create();
        WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
            'status' => 'warming',
            'deliverability_score' => 45,
        ]);

        $tier = app(OutreachSendService::class)->resolveTier($user);

        $this->assertSame('blocked', $tier->tier);
    }

    public function test_resolve_tier_warn_when_ready_score_not_eighty(): void
    {
        $user = User::factory()->create();
        WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
            'status' => 'warming',
            'deliverability_score' => 72,
            'warmup_started_at' => now()->subDays(5),
            'warmup_ramp_days' => 14,
        ]);

        $tier = app(OutreachSendService::class)->resolveTier($user);

        $this->assertSame('warn', $tier->tier);
    }

    public function test_resolve_tier_allowed_when_mailbox_ready(): void
    {
        $user = User::factory()->create();
        WarmupMailbox::factory()->ready()->create(['user_id' => $user->id]);

        $tier = app(OutreachSendService::class)->resolveTier($user);

        $this->assertSame('allowed', $tier->tier);
    }

    public function test_rejects_body_missing_unsubscribe_footer(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => 'owner@example.com',
        ]);
        $email = OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
            'channel' => OutreachChannel::Email,
            'email_body' => 'Hello without footer',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(OutreachSendService::class)->validateDraft($user, $email->fresh('prospect'));
    }
}
```

- [ ] **Step 4: Run tests — expect FAIL**

Run: `php artisan test tests/Unit/Outreach/OutreachSendServiceTest.php`
Expected: FAIL — classes not found

- [ ] **Step 5: Implement OutreachSendReadiness**

```php
<?php

namespace App\Services\Outreach;

final class OutreachSendReadiness
{
    public function __construct(
        public readonly string $tier,
        public readonly string $reason,
        public readonly bool $requiresConfirmation = false,
    ) {}
}
```

- [ ] **Step 6: Implement OutreachSendService (tier + validate only first)**

```php
<?php

namespace App\Services\Outreach;

use App\Enums\OutreachChannel;
use App\Enums\OutreachSendSource;
use App\Models\OutreachEmail;
use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\MailboxTransportFactory;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class OutreachSendService
{
    public function __construct(
        private ProspectUnsubscribeService $unsubscribe,
        private MailboxTransportFactory $transportFactory,
    ) {}

    public function resolveTier(User $user): OutreachSendReadiness
    {
        $mailbox = $this->primaryOutreachMailbox($user);

        if ($mailbox === null) {
            return new OutreachSendReadiness('blocked', 'No outreach mailbox connected.');
        }

        if (in_array($mailbox->status, ['failed', 'paused'], true)) {
            return new OutreachSendReadiness('blocked', "Mailbox {$mailbox->email} is {$mailbox->status}.");
        }

        $score = (int) ($mailbox->deliverability_score ?? 0);

        if ($score < 60) {
            return new OutreachSendReadiness('blocked', "Deliverability score is {$score}. Send unlocks at 60+.");
        }

        $coldSendsToday = OutreachEmail::query()
            ->where('from_mailbox_id', $mailbox->id)
            ->where('send_source', OutreachSendSource::App)
            ->whereDate('sent_at', today())
            ->count();

        $softCap = config('outreach.soft_daily_cap');

        if ($coldSendsToday >= $softCap) {
            return new OutreachSendReadiness(
                'warn',
                "You've sent {$coldSendsToday} cold emails today (recommended max {$softCap}).",
                requiresConfirmation: true,
            );
        }

        if ($mailbox->status !== 'ready' || $score < 80) {
            return new OutreachSendReadiness(
                'warn',
                "Mailbox score is {$score}. Fully ready at 80+ with completed warmup.",
                requiresConfirmation: true,
            );
        }

        return new OutreachSendReadiness('allowed', 'Mailbox ready.');
    }

    public function validateDraft(User $user, OutreachEmail $email): void
    {
        $prospect = $email->prospect;

        if ($email->channel !== OutreachChannel::Email) {
            throw ValidationException::withMessages(['channel' => 'Only email outreach can be sent in-app.']);
        }

        if ($email->sent_at !== null) {
            throw ValidationException::withMessages(['sent_at' => 'Already sent.']);
        }

        if ($this->unsubscribe->outreachSkipReason($user, $prospect) !== null) {
            throw ValidationException::withMessages(['email' => 'Prospect cannot receive outreach.']);
        }

        if (trim($email->subject_line ?? '') === '' || trim($email->email_body ?? '') === '') {
            throw ValidationException::withMessages(['body' => 'Subject and body are required.']);
        }

        if (! $this->unsubscribe->bodyContainsUnsubscribeFooter(
            $email->email_body,
            $user,
            $prospect,
            $prospect->email,
        )) {
            throw ValidationException::withMessages(['body' => 'Unsubscribe link required before send.']);
        }
    }

    public function send(User $user, OutreachEmail $email, bool $confirmWarned): OutreachEmail
    {
        $this->validateDraft($user, $email->fresh('prospect'));

        $tier = $this->resolveTier($user);

        if ($tier->tier === 'blocked') {
            throw ValidationException::withMessages(['mailbox' => $tier->reason]);
        }

        if ($tier->requiresConfirmation && ! $confirmWarned) {
            throw ValidationException::withMessages([
                'confirm_warned' => $tier->reason,
            ]);
        }

        $mailbox = $this->readyMailbox($user)
            ?? $this->primaryOutreachMailbox($user);

        if ($mailbox === null) {
            throw ValidationException::withMessages(['mailbox' => 'No outreach mailbox available.']);
        }

        $transport = $this->transportFactory->make($mailbox);
        $mailer = new Mailer($transport);

        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $messageId = sprintf('%s@%s', (string) Str::uuid(), $host);

        $message = (new Email)
            ->from(new Address($mailbox->email))
            ->to($email->prospect->email)
            ->subject($email->subject_line)
            ->text($email->email_body);

        $message->getHeaders()->addIdHeader('Message-ID', $messageId);

        $mailer->send($message);

        $email->update([
            'sent_subject' => $email->subject_line,
            'sent_body' => $email->email_body,
            'sent_at' => now(),
            'from_mailbox_id' => $mailbox->id,
            'smtp_message_id' => $messageId,
            'send_source' => OutreachSendSource::App,
        ]);

        return $email->fresh('fromMailbox');
    }

    private function primaryOutreachMailbox(User $user): ?WarmupMailbox
    {
        return $user->warmupMailboxes()
            ->where('is_outreach_mailbox', true)
            ->orderBy('id')
            ->first();
    }

    private function readyMailbox(User $user): ?WarmupMailbox
    {
        return $user->warmupMailboxes()
            ->where('is_outreach_mailbox', true)
            ->where('status', 'ready')
            ->orderBy('id')
            ->first();
    }
}
```

- [ ] **Step 7: Run unit tests**

Run: `php artisan test tests/Unit/Outreach/OutreachSendServiceTest.php`
Expected: PASS (send() not tested yet — mocked in feature tests)

- [ ] **Step 8: Commit**

```bash
git add app/Services/Outreach app/Services/ProspectUnsubscribeService.php tests/Unit/Outreach database/factories/WarmupMailboxFactory.php
git commit -m "feat: add OutreachSendService with tier resolution and draft guardrails"
```

---

### Task 4: GenerateOutreachEmailJob sets generated snapshots

**Files:**
- Modify: `app/Jobs/GenerateOutreachEmailJob.php`
- Modify: `tests/Feature/GenerateOutreachEmailJobTest.php`

- [ ] **Step 1: Write failing test**

Add to `GenerateOutreachEmailJobTest.php`:

```php
public function test_persists_generated_subject_and_body_on_email_channel(): void
{
    // ... existing setup with mocked OpenRouter ...

    $email = OutreachEmail::query()->latest()->first();

    $this->assertSame($email->subject_line, $email->generated_subject);
    $this->assertSame($email->email_body, $email->generated_body);
}
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `php artisan test --filter=test_persists_generated_subject_and_body`
Expected: FAIL — `generated_body` null

- [ ] **Step 3: Update job create array**

After building `$generated`, for email channel set:

```php
'subject_line' => $generated['subject_line'] ?? null,
'email_body' => $generated['email_body'],
'generated_subject' => $generated['subject_line'] ?? null,
'generated_body' => $generated['email_body'],
```

Only for email channel (contact form / LinkedIn can leave `generated_*` null or mirror body — email only per spec).

- [ ] **Step 4: Run test**

Run: `php artisan test --filter=GenerateOutreachEmailJob`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/GenerateOutreachEmailJob.php tests/Feature/GenerateOutreachEmailJobTest.php
git commit -m "feat: snapshot generated outreach copy at creation time"
```

---

### Task 5: Controller, routes, and form requests

**Files:**
- Create: `app/Http/Requests/UpdateOutreachEmailRequest.php`
- Create: `app/Http/Requests/SendOutreachEmailRequest.php`
- Modify: `app/Http/Controllers/OutreachEmailController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/OutreachEmailControllerTest.php`
- Create: `tests/Feature/OutreachSendTest.php`

- [ ] **Step 1: Form requests**

`UpdateOutreachEmailRequest.php`:

```php
public function rules(): array
{
    return [
        'subject_line' => ['required', 'string', 'max:255'],
        'email_body' => ['required', 'string'],
    ];
}
```

`SendOutreachEmailRequest.php`:

```php
public function rules(): array
{
    return [
        'confirm_warned' => ['sometimes', 'boolean'],
    ];
}
```

- [ ] **Step 2: Write failing feature tests**

`OutreachSendTest.php` — mock `OutreachSendService::send` or bind fake `MailboxTransportFactory` that uses Symfony's `NullTransport`:

```php
public function test_send_persists_sent_metadata_when_allowed(): void
{
    $user = User::factory()->create();
    WarmupMailbox::factory()->ready()->create(['user_id' => $user->id, 'email' => 'ross@nthdesign.co.uk']);
    $prospect = Prospect::factory()->create([
        'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        'email' => 'owner@example.com',
    ]);
    $unsubscribe = app(ProspectUnsubscribeService::class);
    $body = $unsubscribe->appendUnsubscribeFooter('Hello', $user, $prospect, $prospect->email);
    $email = OutreachEmail::factory()->create([
        'user_id' => $user->id,
        'prospect_id' => $prospect->id,
        'channel' => 'email',
        'subject_line' => 'Hi',
        'email_body' => $body,
        'generated_subject' => 'Hi',
        'generated_body' => $body,
    ]);

    $this->mock(MailboxTransportFactory::class, function ($mock) {
        $mock->shouldReceive('make')->andReturn(new \Symfony\Component\Mailer\Transport\NullTransport);
    });

    $this->actingAs($user)
        ->post("/outreach-emails/{$email->id}/send")
        ->assertRedirect();

    $email->refresh();
    $this->assertNotNull($email->sent_at);
    $this->assertSame('app', $email->send_source->value);
    $this->assertSame('Hi', $email->sent_subject);
    $this->assertNotNull($email->from_mailbox_id);
}
```

Add tests for blocked tier (422), warn without confirm (422), draft PATCH.

- [ ] **Step 3: Implement controller**

```php
public function update(UpdateOutreachEmailRequest $request, OutreachEmail $outreachEmail): RedirectResponse
{
    $this->authorize('update', $outreachEmail);

    if ($outreachEmail->sent_at !== null || $outreachEmail->channel !== OutreachChannel::Email) {
        abort(422);
    }

    $outreachEmail->update($request->validated());

    return back();
}

public function send(SendOutreachEmailRequest $request, OutreachEmail $outreachEmail, OutreachSendService $sender): RedirectResponse
{
    $this->authorize('update', $outreachEmail);

    try {
        $sender->send($request->user(), $outreachEmail, (bool) $request->boolean('confirm_warned'));
    } catch (ValidationException $e) {
        return back()->withErrors($e->errors());
    } catch (WarmupTransportException $e) {
        return back()->with('error', 'Could not connect to your outreach mailbox. Check credentials in Warmup.');
    }

    return back()->with('success', 'Email sent.');
}

public function markSent(Request $request, OutreachEmail $outreachEmail): RedirectResponse
{
    $this->authorize('update', $outreachEmail);

    if ($outreachEmail->channel === OutreachChannel::Email) {
        abort(422, 'Use Send for email outreach.');
    }

    $outreachEmail->update([
        'sent_at' => now(),
        'sent_subject' => $outreachEmail->subject_line,
        'sent_body' => $outreachEmail->email_body,
        'send_source' => OutreachSendSource::Manual,
    ]);

    return back();
}
```

- [ ] **Step 4: Add routes** (inside auth group in `routes/web.php`)

```php
Route::patch('/outreach-emails/{outreachEmail}', [OutreachEmailController::class, 'update'])->name('outreach.update');
Route::post('/outreach-emails/{outreachEmail}/send', [OutreachEmailController::class, 'send'])->name('outreach.send');
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter='OutreachSend|OutreachEmailController'`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http routes tests/Feature
git commit -m "feat: add outreach draft update and in-app send endpoints"
```

---

### Task 6: OutreachEmailResource

**Files:**
- Modify: `app/Http/Resources/OutreachEmailResource.php`
- Modify: `app/Http/Controllers/OutreachController.php` (pass send tier to page if needed)

- [ ] **Step 1: Extend resource**

Add to `format()` return array:

```php
'generated_subject' => $email->generated_subject,
'generated_body' => $email->generated_body,
'sent_subject' => $email->sent_subject,
'sent_body' => $email->sent_body,
'send_source' => $email->send_source?->value,
'from_mailbox_email' => $email->fromMailbox?->email,
'was_edited' => $email->wasEditedBeforeSend(),
```

For unsent email cards, prefer displaying `subject_line` / `email_body` (draft). For sent cards, display `sent_subject` / `sent_body` with fallback to draft columns.

- [ ] **Step 2: Pass send tier on outreach index** (optional prop `send_readiness`)

In `OutreachController::index`, add:

```php
'send_readiness' => app(OutreachSendService::class)->resolveTier($request->user()),
```

Serialized as `tier`, `reason`, `requires_confirmation`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Resources/OutreachEmailResource.php app/Http/Controllers/OutreachController.php
git commit -m "feat: expose outreach send history and readiness in API resources"
```

---

### Task 7: OutreachEmailCard UI

**Files:**
- Modify: `resources/js/Components/OutreachEmailCard.jsx`
- Modify: `resources/js/Pages/Warmup/components/WarmupReadinessBanner.jsx`

- [ ] **Step 1: Editable draft with save on blur**

Replace read-only inputs with controlled state:

```jsx
const [subject, setSubject] = useState(email.subject_line ?? '');
const [body, setBody] = useState(email.email_body ?? '');
const [confirming, setConfirming] = useState(false);
const [sending, setSending] = useState(false);

const saveDraft = () => {
    if (isSent) return;
    router.patch(`/outreach-emails/${email.id}`, {
        subject_line: subject,
        email_body: body,
    }, { preserveScroll: true, preserveState: true });
};

const send = (confirmWarned = false) => {
    setSending(true);
    router.post(`/outreach-emails/${email.id}/send`, {
        confirm_warned: confirmWarned,
    }, {
        preserveScroll: true,
        onFinish: () => setSending(false),
    });
};

const handleSendClick = () => {
    if (sendReadiness?.requires_confirmation && !confirming) {
        setConfirming(true);
        return;
    }
    send(confirming || !sendReadiness?.requires_confirmation);
};
```

Wire `onBlur={saveDraft}` on subject input and body textarea.

- [ ] **Step 2: Replace Mark sent with Send**

Remove `markSent` for email channel. Show Send button when `!isSent`. Disable when `send_readiness?.tier === 'blocked'`.

- [ ] **Step 3: Sent state + history**

When `isSent`, display `email.sent_subject ?? subject` and `email.sent_body ?? body` read-only.

Footer: `Sent {date} from {email.from_mailbox_email}` when `send_source === 'app'`.

If `email.was_edited`, show pill + collapsible block with `generated_subject` / `generated_body`.

- [ ] **Step 4: Update WarmupReadinessBanner copy**

`ready` state: "Sending from {email}. Send cold outreach directly from email cards."

`not_ready`: append "Email send may require confirmation until warmup completes."

- [ ] **Step 5: Build frontend**

Run: `npm run build`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/js
git commit -m "feat: outreach email card send, draft edit, and history UI"
```

---

### Task 8: Factory updates and full verification

**Files:**
- Modify: `database/factories/OutreachEmailFactory.php`

- [ ] **Step 1: Factory defaults**

```php
'generated_subject' => 'Quick question about your online presence',
'generated_body' => 'Hello, we noticed some opportunities to improve your visibility.',
```

Set `generated_*` same as working copy in factory definition.

- [ ] **Step 2: Full test run**

Run:

```bash
php artisan migrate
php artisan test --filter='OutreachSend|OutreachSendService|OutreachEmailController|GenerateOutreachEmailJob|OutreachIndex'
npm run build
```

Expected: all PASS

- [ ] **Step 3: Commit**

```bash
git add database/factories/OutreachEmailFactory.php
git commit -m "test: align outreach email factory with generated snapshots"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Sync SMTP send via warmed mailbox | Task 2, 3, 5 |
| Tiered readiness on Send | Task 3, 5, 6, 7 |
| Inline edit + guardrails | Task 3, 5, 7 |
| `generated_*` / `sent_*` history | Task 1, 4, 6, 7 |
| Soft daily cap warn | Task 3 |
| Form/LinkedIn unchanged | Task 5 (`markSent` restriction) |
| Backfill migration | Task 1 |
| Unsubscribe footer at send | Task 3 |
| Error handling SMTP failure | Task 5 |
| Tests per spec | Tasks 3, 4, 5, 8 |

## Deferred (per spec)

- Batch send, async queue, reply tracking, mailbox picker, HTML bodies, export columns
