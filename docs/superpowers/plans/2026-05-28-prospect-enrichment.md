# Prospect Enrichment — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let operators edit prospect contact/profile fields, keep a private note log, rescore GBP on save, auto-queue site audits when `website_url` changes, and manually regenerate public reports after audit completion.

**Architecture:** `ProspectEnrichmentService` orchestrates persist → GBP rescore → combine → optional audit reset/dispatch. `GbpScoringService` overlays manual `phone`/`website_url` onto `raw_gbp_payload`. `suppress_auto_report` on `prospects` prevents `CombineScoresJob` from auto-dispatching `GenerateProspectReportJob` for operator-triggered audits. UI lives on existing `Prospect/Show.jsx`.

**Tech Stack:** Laravel 13, Inertia.js, React, PHPUnit, Horizon/queue (auditing).

**Spec:** `docs/superpowers/specs/2026-05-28-prospect-enrichment-design.md`

---

## File map

| File | Action | Responsibility |
|------|--------|----------------|
| `database/migrations/2026_05_28_*_create_prospect_notes_table.php` | Create | Notes table |
| `database/migrations/2026_05_28_*_add_suppress_auto_report_to_prospects_table.php` | Create | Boolean flag |
| `app/Models/ProspectNote.php` | Create | Note model |
| `app/Models/Prospect.php` | Modify | Relations, fillable, casts |
| `app/Models/User.php` | Modify | `prospectNotes()` if needed |
| `app/Services/GbpScoringService.php` | Modify | `overlayProspectFields`, `scoreProspect` |
| `app/Services/ProspectEnrichmentService.php` | Create | Update orchestration |
| `app/Http/Requests/UpdateProspectRequest.php` | Create | PATCH validation |
| `app/Http/Requests/StoreProspectNoteRequest.php` | Create | Note validation |
| `app/Http/Controllers/ProspectController.php` | Modify | `update`, show props |
| `app/Http/Controllers/ProspectNoteController.php` | Create | `store` |
| `app/Jobs/CombineScoresJob.php` | Modify | Respect `suppress_auto_report` |
| `routes/web.php` | Modify | PATCH + POST routes |
| `tests/Unit/GbpScoringServiceTest.php` | Modify | Overlay/scoreProspect tests |
| `tests/Unit/ProspectEnrichmentServiceTest.php` | Create | Service unit tests (optional; can fold into feature) |
| `tests/Feature/ProspectEnrichmentTest.php` | Create | PATCH, notes, queue, suppress flag |
| `tests/Feature/AutoGenerateReportTest.php` | Modify | Suppress-flag test case |
| `resources/js/Pages/Prospect/Show.jsx` | Modify | Edit form, notes, status hints |
| `database/factories/ProspectFactory.php` | Modify | `suppress_auto_report` default |

---

### Task 1: Database migrations

**Files:**
- Create: `database/migrations/2026_05_28_100000_create_prospect_notes_table.php`
- Create: `database/migrations/2026_05_28_100001_add_suppress_auto_report_to_prospects_table.php`

- [ ] **Step 1: Create `prospect_notes` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['prospect_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_notes');
    }
};
```

- [ ] **Step 2: Create `suppress_auto_report` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->boolean('suppress_auto_report')->default(false)->after('audit_status');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('suppress_auto_report');
        });
    }
};
```

- [ ] **Step 3: Run migrations**

```bash
php artisan migrate
```

Expected: both migrations OK.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_28_100000_create_prospect_notes_table.php database/migrations/2026_05_28_100001_add_suppress_auto_report_to_prospects_table.php
git commit -m "feat(prospects): add notes table and suppress_auto_report column"
```

---

### Task 2: Models and relations

**Files:**
- Create: `app/Models/ProspectNote.php`
- Modify: `app/Models/Prospect.php`
- Modify: `database/factories/ProspectFactory.php`

- [ ] **Step 1: Create `ProspectNote` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectNote extends Model
{
    protected $fillable = ['prospect_id', 'user_id', 'body'];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 2: Update `Prospect` model**

Add to `$fillable`: `'suppress_auto_report'`

Add to `$casts`:

```php
'suppress_auto_report' => 'boolean',
```

Add relations:

```php
public function notes(): HasMany
{
    return $this->hasMany(ProspectNote::class)->latest();
}
```

(import `HasMany` if missing)

- [ ] **Step 3: Update factory**

In `database/factories/ProspectFactory.php` add:

```php
'suppress_auto_report' => false,
```

- [ ] **Step 4: Commit**

```bash
git add app/Models/ProspectNote.php app/Models/Prospect.php database/factories/ProspectFactory.php
git commit -m "feat(prospects): ProspectNote model and relations"
```

---

### Task 3: `GbpScoringService` prospect overlay

**Files:**
- Modify: `app/Services/GbpScoringService.php`
- Modify: `tests/Unit/GbpScoringServiceTest.php`

- [ ] **Step 1: Write failing unit tests**

Add to `tests/Unit/GbpScoringServiceTest.php`:

```php
public function test_overlay_adds_website_and_phone_to_payload(): void
{
    $prospect = new \App\Models\Prospect([
        'website_url' => 'https://custom.example',
        'phone' => '+441234567890',
    ]);

    $overlaid = $this->service->overlayProspectFields([], $prospect);

    $this->assertSame('https://custom.example', $overlaid['websiteUri']);
    $this->assertSame('+441234567890', $overlaid['nationalPhoneNumber']);
}

public function test_overlay_clears_website_when_operator_cleared(): void
{
    $prospect = new \App\Models\Prospect([
        'website_url' => '',
        'phone' => null,
    ]);

    $overlaid = $this->service->overlayProspectFields(['websiteUri' => 'https://old.com'], $prospect);

    $this->assertArrayNotHasKey('websiteUri', $overlaid);
}

public function test_overlay_removes_no_website_flag_when_website_set(): void
{
    $prospect = new \App\Models\Prospect([
        'website_url' => 'https://example.com',
        'phone' => '+441234',
        'raw_gbp_payload' => [],
    ]);
    $prospect->setRelation('search', new \App\Models\Search(['city' => 'London', 'benchmark_snapshot' => null]));

    $result = $this->service->scoreProspect($prospect);

    $this->assertNotContains('No website listed', $result['flags']);
    $this->assertNotContains('No phone number listed', $result['flags']);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=overlay
```

Expected: FAIL — methods missing.

- [ ] **Step 3: Implement in `GbpScoringService.php`**

```php
use App\Models\Prospect;

/**
 * Apply operator-edited phone/website onto a Places payload for rescoring.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
public function overlayProspectFields(array $payload, Prospect $prospect): array
{
    if ($prospect->website_url !== null) {
        if ($prospect->website_url === '') {
            unset($payload['websiteUri']);
        } else {
            $payload['websiteUri'] = $prospect->website_url;
        }
    }

    if ($prospect->phone !== null) {
        if ($prospect->phone === '') {
            unset($payload['nationalPhoneNumber']);
        } else {
            $payload['nationalPhoneNumber'] = $prospect->phone;
        }
    }

    return $payload;
}

/**
 * @return array{score: int, flags: string[]}
 */
public function scoreProspect(Prospect $prospect): array
{
    $search = $prospect->search;
    $payload = $this->overlayProspectFields($prospect->raw_gbp_payload ?? [], $prospect);

    return $this->score(
        $payload,
        $search?->benchmark_snapshot,
        $search?->city ?? '',
    );
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Unit/GbpScoringServiceTest.php
```

Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/GbpScoringService.php tests/Unit/GbpScoringServiceTest.php
git commit -m "feat(scoring): rescore prospect with operator phone/website overlay"
```

---

### Task 4: `CombineScoresJob` suppress auto-report

**Files:**
- Modify: `app/Jobs/CombineScoresJob.php`
- Modify: `tests/Feature/AutoGenerateReportTest.php`

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/AutoGenerateReportTest.php`:

```php
public function test_combine_scores_skips_report_when_suppress_auto_report_set(): void
{
    Bus::fake();

    $user = User::factory()->create();
    $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'gbp_only']);
    $prospect = Prospect::factory()->create([
        'search_id'            => $search->id,
        'gbp_score'            => 70,
        'a11y_score'           => 0,
        'combined_score'       => 0,
        'audit_status'         => 'pending',
        'suppress_auto_report' => true,
    ]);

    $job = new CombineScoresJob($prospect);
    $job->handle(
        app(\App\Services\CombineScoresService::class),
        app(\App\Services\SearchStatusService::class),
    );

    Bus::assertNotDispatched(GenerateProspectReportJob::class);

    $prospect->refresh();
    $this->assertFalse($prospect->suppress_auto_report);
    $this->assertSame('complete', $prospect->audit_status);
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=test_combine_scores_skips_report_when_suppress
```

Expected: FAIL — report still dispatched.

- [ ] **Step 3: Update `CombineScoresJob` handle() report dispatch block**

Replace lines 56–58 with:

```php
if ($prospect && in_array($prospect->audit_status, ['complete', 'skipped'], true)) {
    if ($prospect->suppress_auto_report) {
        $prospect->update(['suppress_auto_report' => false]);
    } else {
        GenerateProspectReportJob::dispatch($prospect);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Feature/AutoGenerateReportTest.php
```

Expected: all PASS (existing + new).

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/CombineScoresJob.php tests/Feature/AutoGenerateReportTest.php
git commit -m "feat(prospects): suppress auto-report after operator-triggered audit"
```

---

### Task 5: `ProspectEnrichmentService`

**Files:**
- Create: `app/Services/ProspectEnrichmentService.php`
- Create: `tests/Feature/ProspectEnrichmentTest.php` (service covered via feature tests in Task 7; optional quick unit test here)

- [ ] **Step 1: Create service**

```php
<?php

namespace App\Services;

use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProspectEnrichmentService
{
    public function __construct(
        private GbpScoringService $gbpScorer,
        private CombineScoresService $combiner,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Keys: business_name?, phone?, website_url?, address?
     * @return array{audit_queued: bool}
     */
    public function update(Prospect $prospect, array $data): array
    {
        if ($prospect->audit_status === 'pending') {
            throw ValidationException::withMessages([
                'website_url' => 'A site audit is already in progress. Wait for it to finish before saving changes.',
            ]);
        }

        $prospect->loadMissing('search');
        $previousWebsite = $this->normalizeWebsiteUrl($prospect->website_url);

        $prospect->fill(collect($data)->only([
            'business_name', 'phone', 'website_url', 'address',
        ])->all());

        $newWebsite = $this->normalizeWebsiteUrl($prospect->website_url);
        $websiteChanged = $previousWebsite !== $newWebsite;

        $scored = $this->gbpScorer->scoreProspect($prospect);
        $combined = $this->combiner->combine($prospect, $prospect->search->scan_type);

        $updates = array_merge($combined, [
            'gbp_score'  => $scored['score'],
            'gbp_flags'  => $scored['flags'],
        ]);

        $auditQueued = false;

        if ($websiteChanged && $this->shouldAudit($prospect)) {
            $updates = array_merge($updates, $this->auditResetFields(), [
                'suppress_auto_report' => true,
            ]);
            $auditQueued = true;
        }

        $prospect->update($updates);
        $prospect->refresh();

        if ($auditQueued) {
            AuditSiteJob::dispatch($prospect);
        }

        return ['audit_queued' => $auditQueued];
    }

    private function shouldAudit(Prospect $prospect): bool
    {
        $scanType = $prospect->search->scan_type;

        return in_array($scanType, ['accessibility_only', 'combined'], true)
            && ! empty($prospect->website_url);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditResetFields(): array
    {
        return [
            'audit_status'           => 'pending',
            'raw_a11y_payload'       => null,
            'raw_lighthouse_payload' => null,
            'a11y_score'             => 0,
            'a11y_flags'             => null,
            'performance_score'      => 0,
        ];
    }

    private function normalizeWebsiteUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return Str::lower(rtrim($url, '/'));
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/ProspectEnrichmentService.php
git commit -m "feat(prospects): ProspectEnrichmentService for profile updates"
```

---

### Task 6: Form requests, controllers, routes

**Files:**
- Create: `app/Http/Requests/UpdateProspectRequest.php`
- Create: `app/Http/Requests/StoreProspectNoteRequest.php`
- Create: `app/Http/Controllers/ProspectNoteController.php`
- Modify: `app/Http/Controllers/ProspectController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: `UpdateProspectRequest`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('prospect'));
    }

    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'website_url'   => ['sometimes', 'nullable', 'url', 'max:500'],
            'address'       => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasAny(['business_name', 'phone', 'website_url', 'address'])) {
                $validator->errors()->add('business_name', 'Provide at least one field to update.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('website_url') && filled($this->input('website_url'))) {
            $url = trim((string) $this->input('website_url'));
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                $url = 'https://'.$url;
            }
            $this->merge(['website_url' => $url]);
        }
    }
}
```

- [ ] **Step 2: `StoreProspectNoteRequest`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProspectNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('prospect'));
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
```

- [ ] **Step 3: `ProspectNoteController`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProspectNoteRequest;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;

class ProspectNoteController extends Controller
{
    public function store(StoreProspectNoteRequest $request, Prospect $prospect): RedirectResponse
    {
        $prospect->notes()->create([
            'user_id' => $request->user()->id,
            'body'    => $request->validated('body'),
        ]);

        return back()->with('success', 'Note added.');
    }
}
```

- [ ] **Step 4: Add `update` to `ProspectController`**

```php
use App\Http\Requests\UpdateProspectRequest;
use App\Services\ProspectEnrichmentService;

public function update(UpdateProspectRequest $request, Prospect $prospect, ProspectEnrichmentService $enrichment): RedirectResponse
{
    $result = $enrichment->update($prospect, $request->validated());

    $message = $result['audit_queued']
        ? 'Details saved. Site audit queued.'
        : 'Details saved.';

    return back()->with('success', $message);
}
```

- [ ] **Step 5: Extend `show()` — load notes, pass `audit_status` for UI**

In `show()`, add eager load:

```php
'notes' => fn ($q) => $q->with('user:id,name')->latest(),
```

Add to Inertia `prospect` array:

```php
'audit_status' => $prospect->audit_status,
```

Add new prop:

```php
'notes' => $prospect->notes->map(fn ($n) => [
    'id'         => $n->id,
    'body'       => $n->body,
    'author'     => $n->user?->name ?? 'You',
    'created_at' => $n->created_at->diffForHumans(),
]),
```

Ensure `notes` relation is loaded: `$prospect->load([..., 'notes.user']);`

- [ ] **Step 6: Routes in `routes/web.php`** (inside auth group)

```php
Route::patch('/prospects/{prospect}', [ProspectController::class, 'update'])->name('prospects.update');
Route::post('/prospects/{prospect}/notes', [ProspectNoteController::class, 'store'])->name('prospects.notes.store');
```

Add `use App\Http\Controllers\ProspectNoteController;`

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/UpdateProspectRequest.php app/Http/Requests/StoreProspectNoteRequest.php app/Http/Controllers/ProspectNoteController.php app/Http/Controllers/ProspectController.php routes/web.php
git commit -m "feat(prospects): PATCH profile and POST notes endpoints"
```

---

### Task 7: Feature tests

**Files:**
- Create: `tests/Feature/ProspectEnrichmentTest.php`

- [ ] **Step 1: Write feature tests**

```php
<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\CombineScoresJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProspectEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_patch_prospect_fields(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'gbp_only']);
        $prospect = Prospect::factory()->create([
            'search_id'       => $search->id,
            'phone'           => null,
            'website_url'     => null,
            'gbp_flags'       => ['No phone number listed', 'No website listed'],
            'raw_gbp_payload' => [],
        ]);

        $this->actingAs($user)
            ->patch("/prospects/{$prospect->id}", [
                'phone'       => '+441234567890',
                'website_url' => 'https://example.com',
            ])
            ->assertRedirect();

        $prospect->refresh();
        $this->assertSame('+441234567890', $prospect->phone);
        $this->assertSame('https://example.com', $prospect->website_url);
        $this->assertNotContains('No phone number listed', $prospect->gbp_flags);
        $this->assertNotContains('No website listed', $prospect->gbp_flags);
    }

    public function test_other_user_cannot_patch_prospect(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $owner->id])->id,
        ]);

        $this->actingAs($other)
            ->patch("/prospects/{$prospect->id}", ['phone' => '+441234'])
            ->assertForbidden();
    }

    public function test_website_change_dispatches_audit_for_combined_search(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined']);
        $prospect = Prospect::factory()->create([
            'search_id'          => $search->id,
            'website_url'        => null,
            'audit_status'       => 'skipped',
            'raw_a11y_payload'   => ['violations' => []],
            'a11y_score'         => 50,
        ]);

        $this->actingAs($user)
            ->patch("/prospects/{$prospect->id}", ['website_url' => 'https://new.example'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Details saved. Site audit queued.');

        $prospect->refresh();
        $this->assertSame('pending', $prospect->audit_status);
        $this->assertNull($prospect->raw_a11y_payload);
        $this->assertTrue($prospect->suppress_auto_report);

        Queue::assertPushed(AuditSiteJob::class);
    }

    public function test_patch_rejected_when_audit_pending(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id'    => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->patch("/prospects/{$prospect->id}", ['phone' => '+441234'])
            ->assertSessionHasErrors('website_url');
    }

    public function test_owner_can_add_note(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/notes", ['body' => 'Called — no answer'])
            ->assertRedirect();

        $this->assertDatabaseHas('prospect_notes', [
            'prospect_id' => $prospect->id,
            'user_id'     => $user->id,
            'body'        => 'Called — no answer',
        ]);
    }

    public function test_show_includes_notes_newest_first(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $prospect->notes()->create(['user_id' => $user->id, 'body' => 'First', 'created_at' => now()->subHour()]);
        $prospect->notes()->create(['user_id' => $user->id, 'body' => 'Second', 'created_at' => now()]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notes', 2)
                ->where('notes.0.body', 'Second')
                ->where('notes.1.body', 'First'));
    }
}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test tests/Feature/ProspectEnrichmentTest.php
```

Expected: all PASS (fix `User` model `name` column if factory uses different attribute — use `$user->name` or email per your `users` table).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/ProspectEnrichmentTest.php
git commit -m "test(prospects): feature tests for enrichment and notes"
```

---

### Task 8: Prospect detail UI

**Files:**
- Modify: `resources/js/Pages/Prospect/Show.jsx`

- [ ] **Step 1: Add state and handlers at top of component**

```jsx
const [editing, setEditing] = useState(false);
const [form, setForm] = useState({
    business_name: prospect.business_name ?? '',
    phone: prospect.phone ?? '',
    website_url: prospect.website_url ?? '',
    address: prospect.address ?? '',
});
const [noteBody, setNoteBody] = useState('');

const saveDetails = (e) => {
    e.preventDefault();
    router.patch(`/prospects/${prospect.id}`, form, {
        preserveScroll: true,
        onSuccess: () => setEditing(false),
    });
};

const addNote = (e) => {
    e.preventDefault();
    if (!noteBody.trim()) return;
    router.post(`/prospects/${prospect.id}/notes`, { body: noteBody }, {
        preserveScroll: true,
        onSuccess: () => setNoteBody(''),
    });
};
```

Add `notes` and ensure `prospect.audit_status` in destructured props:

```jsx
export default function ProspectShow({ prospect, search, report, outreachEmails, audit, lighthouse, notes = [] }) {
```

- [ ] **Step 2: Replace Profile card with edit/view modes**

In the Profile `Card`, when `editing`:

```jsx
<form onSubmit={saveDetails} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
    <label className="micro">Business name</label>
    <input className="input" value={form.business_name} onChange={(e) => setForm({ ...form, business_name: e.target.value })} required />
    <label className="micro">Phone</label>
    <input className="input" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
    <label className="micro">Website</label>
    <input className="input" type="url" value={form.website_url} onChange={(e) => setForm({ ...form, website_url: e.target.value })} placeholder="https://..." />
    <label className="micro">Address</label>
    <input className="input" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
    <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
        <Button kind="primary" size="sm" type="submit">Save</Button>
        <Button kind="ghost" size="sm" type="button" onClick={() => setEditing(false)}>Cancel</Button>
    </div>
</form>
```

When not editing, show existing dl rows (always show phone/website rows, use em dash if empty) plus:

```jsx
<Button kind="secondary" size="sm" onClick={() => setEditing(true)} className="mt-4">Edit details</Button>
```

Use existing `.input` class from the design system (check `components.css` — if missing, use inline styles matching other forms e.g. `Profile/Edit.jsx`).

- [ ] **Step 3: Add Private notes card** (below Profile card in aside)

```jsx
<Card title="Private notes">
    <p className="micro" style={{ marginBottom: 12 }}>Not included on public reports.</p>
    {notes.length === 0 ? (
        <p className="micro" style={{ marginBottom: 12 }}>No notes yet.</p>
    ) : (
        <ul style={{ listStyle: 'none', padding: 0, margin: '0 0 16px', display: 'flex', flexDirection: 'column', gap: 12 }}>
            {notes.map((n) => (
                <li key={n.id} style={{ borderBottom: '1px solid var(--color-stone-200)', paddingBottom: 12 }}>
                    <p style={{ fontSize: 13, margin: '0 0 4px', whiteSpace: 'pre-wrap' }}>{n.body}</p>
                    <span className="micro">{n.author} · {n.created_at}</span>
                </li>
            ))}
        </ul>
    )}
    <form onSubmit={addNote}>
        <textarea
            className="input"
            rows={3}
            value={noteBody}
            onChange={(e) => setNoteBody(e.target.value)}
            placeholder="Add a note…"
            style={{ width: '100%', marginBottom: 8 }}
        />
        <Button kind="secondary" size="sm" type="submit" disabled={!noteBody.trim()}>Add note</Button>
    </form>
</Card>
```

- [ ] **Step 4: Enhance Public report card**

Before regenerate button, when `prospect.audit_status === 'pending'`:

```jsx
<p className="micro" style={{ marginBottom: 8, color: 'var(--color-stone-500)' }}>
    Site audit in progress…
</p>
```

Disable regenerate while pending:

```jsx
<Button
    kind="secondary"
    size="sm"
    onClick={generateReport}
    disabled={prospect.audit_status === 'pending'}
>
    {report ? 'Regenerate report' : 'Generate report'}
</Button>
```

When `prospect.audit_status === 'complete'` and report exists, optional hint:

```jsx
{prospect.audit_status === 'complete' && report && (
    <p className="micro" style={{ marginTop: 8 }}>
        Regenerate after editing the website to refresh audit results in the report.
    </p>
)}
```

- [ ] **Step 5: Manual smoke test**

1. Open a prospect with no website on a combined search.
2. Edit details → add website → confirm flash “Site audit queued”.
3. Wait for audit (or run `php artisan queue:work` locally).
4. Click Regenerate report → preview `/r/{token}`.
5. Add a note → confirm it appears newest-first.

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Prospect/Show.jsx
git commit -m "feat(ui): prospect edit form and private notes on detail page"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Editable name, phone, website, address | 5–8 |
| Private note log, add-only, newest first | 1–2, 6–8 |
| Auto audit on website change | 5, 7 |
| Manual report regen | 4, 8 (existing button + pending disable) |
| GBP rescore on save | 3, 5 |
| `suppress_auto_report` | 1, 4, 5 |
| Reject save when audit pending | 5, 7 |
| `gbp_only` no audit dispatch | 5 (`shouldAudit`) |
| Notes never on public report | 8 (no backend change to report builder) |
| Authorization owner-only | 6, 7 |

---

## Out of scope (do not implement)

- Search table inline edit
- Note edit/delete
- Auto-report after operator audit
- Places API re-fetch
