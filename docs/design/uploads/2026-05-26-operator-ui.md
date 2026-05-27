# Operator UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship `/saved`, `/reports`, `/outreach`, and CSV export so operators can manage prospects, monitor report views, queue outreach, and batch-generate emails (with reports required).

**Architecture:** Add `outreach_selections` and `exports` tables; centralise prospect listing in `ProspectListQuery`; three Inertia controllers; extend `GenerateOutreachEmailJob` with batch options; reuse table/card patterns from `Search/Show` and `Prospect/Show`.

**Tech Stack:** Laravel 13, Inertia + React, PostgreSQL, PHPUnit, existing Horizon `auditing` queue.

**Spec:** `docs/superpowers/specs/2026-05-26-operator-ui-design.md`

---

## File map

| File | Responsibility |
|---|---|
| `database/migrations/*_create_outreach_selections_table.php` | Queue persistence |
| `database/migrations/*_create_exports_table.php` | Export audit log |
| `database/factories/SearchFactory.php` | Feature test fixtures |
| `database/factories/ProspectFactory.php` | Feature test fixtures |
| `database/factories/ProspectReportFactory.php` | Feature test fixtures |
| `database/factories/OutreachEmailFactory.php` | Feature test fixtures |
| `app/Models/OutreachSelection.php` | Selection model |
| `app/Models/Export.php` | Export model |
| `app/Queries/ProspectListQuery.php` | Shared filters + warm scope |
| `app/Http/Controllers/SavedProspectController.php` | `/saved` |
| `app/Http/Controllers/ReportDashboardController.php` | `/reports` |
| `app/Http/Controllers/OutreachController.php` | `/outreach` + selection CRUD |
| `app/Http/Controllers/ExportController.php` | CSV download |
| `app/Policies/OutreachSelectionPolicy.php` | Selection auth |
| `resources/js/Pages/Saved/Index.jsx` | Saved UI |
| `resources/js/Pages/Reports/Index.jsx` | Reports UI |
| `resources/js/Pages/Outreach/Index.jsx` | Outreach UI |
| `resources/js/Components/OutreachEmailCard.jsx` | Shared email card |
| `tests/Feature/ProspectListQueryTest.php` | Warm + filters |
| `tests/Feature/OutreachSelectionTest.php` | Selection CRUD |
| `tests/Feature/ExportProspectsTest.php` | CSV + Export row |
| `tests/Feature/OutreachGenerateTest.php` | Batch skip without report |

---

### Task 1: Migrations and models

**Files:**
- Create: `database/migrations/2026_05_26_200000_create_outreach_selections_table.php`
- Create: `database/migrations/2026_05_26_200001_create_exports_table.php`
- Create: `app/Models/OutreachSelection.php`
- Create: `app/Models/Export.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Prospect.php`

- [ ] **Step 1: Create outreach_selections migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'prospect_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_selections');
    }
};
```

- [ ] **Step 2: Create exports migration**

```php
Schema::create('exports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('search_id')->nullable()->constrained()->nullOnDelete();
    $table->string('filename');
    $table->unsignedInteger('row_count');
    $table->timestamps();
});
```

- [ ] **Step 3: Create models**

`app/Models/OutreachSelection.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachSelection extends Model
{
    protected $fillable = ['user_id', 'prospect_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
```

`app/Models/Export.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    protected $fillable = ['user_id', 'search_id', 'filename', 'row_count'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(Search::class);
    }
}
```

- [ ] **Step 4: Add relationships**

On `User.php`:

```php
public function outreachSelections(): HasMany
{
    return $this->hasMany(OutreachSelection::class);
}

public function exports(): HasMany
{
    return $this->hasMany(Export::class);
}
```

On `Prospect.php`:

```php
public function outreachSelections(): HasMany
{
    return $this->hasMany(OutreachSelection::class);
}
```

- [ ] **Step 5: Run migrations**

Run: `php artisan migrate`
Expected: both tables created

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models
git commit -m "feat: add outreach selections and exports tables"
```

---

### Task 2: Test factories

**Files:**
- Create: `database/factories/SearchFactory.php`
- Create: `database/factories/ProspectFactory.php`
- Create: `database/factories/ProspectReportFactory.php`
- Create: `database/factories/OutreachEmailFactory.php`

- [ ] **Step 1: SearchFactory**

```php
<?php

namespace Database\Factories;

use App\Models\Search;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Search> */
class SearchFactory extends Factory
{
    protected $model = Search::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'niche'       => 'dental practice',
            'city'        => 'Birmingham',
            'country'     => 'GB',
            'scan_type'   => 'combined',
            'status'      => 'complete',
            'total_found' => 1,
        ];
    }
}
```

Add `use HasFactory;` to `app/Models/Search.php`.

- [ ] **Step 2: ProspectFactory**

```php
public function definition(): array
{
    return [
        'search_id'       => Search::factory(),
        'place_id'        => fake()->uuid(),
        'business_name'   => fake()->company(),
        'combined_score'  => 75,
        'gbp_score'       => 60,
        'a11y_score'      => 80,
        'dominant_angle'  => 'accessibility',
        'audit_status'    => 'complete',
        'review_count'    => 5,
        'photo_count'     => 2,
    ];
}
```

Add `HasFactory` to `Prospect.php`.

- [ ] **Step 3: ProspectReportFactory and OutreachEmailFactory**

`ProspectReportFactory`: `prospect_id`, `token` => `Str::uuid()`, `view_count` => 0.

`OutreachEmailFactory`: `prospect_id`, `user_id`, `pitch_angle` => `'gbp'`, `subject_line`, `email_body`, `sent_at` => null, `response_received` => false.

Add `HasFactory` to `ProspectReport` and `OutreachEmail` models.

- [ ] **Step 4: Commit**

```bash
git add database/factories app/Models
git commit -m "test: add factories for search prospect report outreach"
```

---

### Task 3: ProspectListQuery (warm scope + filters)

**Files:**
- Create: `app/Queries/ProspectListQuery.php`
- Create: `tests/Feature/ProspectListQueryTest.php`

- [ ] **Step 1: Write failing warm-lead test**

`tests/Feature/ProspectListQueryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Queries\ProspectListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectListQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_warm_scope_requires_viewed_report_sent_outreach_and_no_response(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        $warm = Prospect::factory()->create(['search_id' => $search->id, 'business_name' => 'Warm Co']);
        ProspectReport::factory()->create([
            'prospect_id' => $warm->id,
            'viewed_at'   => now(),
        ]);
        OutreachEmail::factory()->create([
            'prospect_id'        => $warm->id,
            'user_id'            => $user->id,
            'sent_at'            => now(),
            'response_received'  => false,
        ]);

        $cold = Prospect::factory()->create(['search_id' => $search->id, 'business_name' => 'Cold Co']);
        ProspectReport::factory()->create(['prospect_id' => $cold->id, 'viewed_at' => now()]);

        $responded = Prospect::factory()->create(['search_id' => $search->id, 'business_name' => 'Done Co']);
        ProspectReport::factory()->create(['prospect_id' => $responded->id, 'viewed_at' => now()]);
        OutreachEmail::factory()->create([
            'prospect_id'       => $responded->id,
            'user_id'           => $user->id,
            'sent_at'           => now(),
            'response_received' => true,
        ]);

        $ids = (new ProspectListQuery($user))
            ->apply(['warm' => true])
            ->query()
            ->pluck('business_name')
            ->all();

        $this->assertSame(['Warm Co'], $ids);
    }

    public function test_min_score_filter(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        Prospect::factory()->create(['search_id' => $search->id, 'combined_score' => 90]);
        Prospect::factory()->create(['search_id' => $search->id, 'combined_score' => 40]);

        $count = (new ProspectListQuery($user))
            ->apply(['min_score' => 80])
            ->query()
            ->count();

        $this->assertSame(1, $count);
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `php artisan test --filter=ProspectListQueryTest`
Expected: class `ProspectListQuery` not found

- [ ] **Step 3: Implement ProspectListQuery**

`app/Queries/ProspectListQuery.php`:

```php
<?php

namespace App\Queries;

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProspectListQuery
{
    private Builder $query;

    public function __construct(private User $user)
    {
        $this->query = Prospect::query()
            ->whereHas('search', fn (Builder $q) => $q->where('user_id', $this->user->id))
            ->with(['search', 'report'])
            ->with(['outreachEmails' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('combined_score');
    }

    public function apply(array $filters): self
    {
        if (!empty($filters['from'])) {
            $this->query->whereDate('prospects.created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $this->query->whereDate('prospects.created_at', '<=', $filters['to']);
        }
        if (!empty($filters['niche'])) {
            $this->query->whereHas('search', fn (Builder $q) => $q
                ->where('niche', 'like', '%'.$filters['niche'].'%'));
        }
        if (!empty($filters['city'])) {
            $this->query->whereHas('search', fn (Builder $q) => $q
                ->where('city', 'like', '%'.$filters['city'].'%'));
        }
        if (!empty($filters['scan_type'])) {
            $this->query->whereHas('search', fn (Builder $q) => $q
                ->where('scan_type', $filters['scan_type']));
        }
        if (isset($filters['min_score']) && $filters['min_score'] !== '') {
            $this->query->where('combined_score', '>=', (int) $filters['min_score']);
        }
        if (!empty($filters['dominant_angle'])) {
            $this->query->where('dominant_angle', $filters['dominant_angle']);
        }
        if (!empty($filters['warm'])) {
            $this->applyWarmScope();
        }

        return $this;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function warmLeads(int $limit = 10): Builder
    {
        return Prospect::query()
            ->whereHas('search', fn (Builder $q) => $q->where('user_id', $this->user->id))
            ->tap(fn (Builder $q) => $this->applyWarmScopeOn($q))
            ->with(['search', 'report'])
            ->with(['outreachEmails' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc(
                ProspectReport::query()
                    ->select('viewed_at')
                    ->whereColumn('prospect_reports.prospect_id', 'prospects.id')
                    ->limit(1)
            )
            ->limit($limit);
    }

    private function applyWarmScope(): void
    {
        $this->applyWarmScopeOn($this->query);
    }

    private function applyWarmScopeOn(Builder $query): void
    {
        $query
            ->whereHas('report', fn (Builder $q) => $q->whereNotNull('viewed_at'))
            ->whereHas('outreachEmails', fn (Builder $q) => $q->whereNotNull('sent_at'))
            ->whereDoesntHave('outreachEmails', fn (Builder $q) => $q->where('response_received', true));
    }
}
```

Fix warmLeads ordering — use simpler `orderByDesc` on a join or subquery; minimal v1:

```php
public function warmLeads(int $limit = 10): \Illuminate\Support\Collection
{
    return $this->apply(['warm' => true])->query()->limit($limit)->get();
}
```

- [ ] **Step 4: Run tests — expect PASS**

Run: `php artisan test --filter=ProspectListQueryTest`

- [ ] **Step 5: Commit**

```bash
git add app/Queries tests/Feature/ProspectListQueryTest.php
git commit -m "feat: add ProspectListQuery with warm lead scope"
```

---

### Task 4: Saved prospects page

**Files:**
- Create: `app/Http/Controllers/SavedProspectController.php`
- Create: `resources/js/Pages/Saved/Index.jsx`
- Modify: `routes/web.php`

- [ ] **Step 1: Controller**

```php
<?php

namespace App\Http\Controllers;

use App\Queries\ProspectListQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedProspectController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only([
            'from', 'to', 'niche', 'city', 'scan_type', 'min_score', 'dominant_angle', 'warm',
        ]);

        $listQuery = new ProspectListQuery($request->user());
        $prospects = $listQuery->apply($filters)->query()->get();
        $warmLeads = empty($filters['warm'])
            ? (new ProspectListQuery($request->user()))->warmLeads(10)
            : collect();

        return Inertia::render('Saved/Index', [
            'prospects' => $prospects->map(fn ($p) => $this->formatProspect($p)),
            'warmLeads' => $warmLeads->map(fn ($p) => $this->formatProspect($p)),
            'filters'   => $filters,
            'meta'      => ['total' => $prospects->count()],
        ]);
    }

    private function formatProspect($p): array
    {
        $latest = $p->outreachEmails->first();

        return [
            'id'              => $p->id,
            'business_name'   => $p->business_name,
            'niche'           => $p->search->niche,
            'city'            => $p->search->city,
            'combined_score'  => $p->combined_score,
            'gbp_score'       => $p->gbp_score,
            'a11y_score'      => $p->a11y_score,
            'dominant_angle'  => $p->dominant_angle,
            'report_url'      => $p->report ? url('/r/'.$p->report->token) : null,
            'outreach_sent'   => $latest?->sent_at?->toISOString(),
            'response_received' => (bool) ($latest?->response_received),
        ];
    }
}
```

Extract `formatProspect` to a dedicated `ProspectResource` or trait if reused in Task 5.

- [ ] **Step 2: Route**

In `routes/web.php` auth group:

```php
Route::get('/saved', [SavedProspectController::class, 'index'])->name('saved.index');
```

- [ ] **Step 3: Saved/Index.jsx**

- GET filter form (fields from spec)
- Warm panel when `warmLeads.length > 0`
- Table with columns: Business, Scores, Angle, Report (copy), Actions (View, Add to outreach via `router.post('/outreach/selections', { prospect_ids: [id] })`)
- Export: `<form method="post" action="/exports">` with hidden inputs mirroring current filters + `@csrf`

- [ ] **Step 4: Feature test — saved page loads**

```php
public function test_saved_page_requires_auth(): void
{
    $this->get('/saved')->assertRedirect('/login');
}

public function test_saved_page_lists_user_prospects(): void
{
    $user = User::factory()->create();
    $search = Search::factory()->create(['user_id' => $user->id]);
    Prospect::factory()->create(['search_id' => $search->id]);

    $this->actingAs($user)->get('/saved')->assertOk();
}
```

- [ ] **Step 5: Run tests, commit**

```bash
git add app/Http/Controllers/SavedProspectController.php resources/js/Pages/Saved routes/web.php tests/Feature/SavedProspectTest.php
git commit -m "feat: add saved prospects page"
```

---

### Task 5: CSV export

**Files:**
- Create: `app/Http/Controllers/ExportController.php`
- Create: `tests/Feature/ExportProspectsTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Failing export test**

```php
public function test_export_streams_csv_and_creates_export_record(): void
{
    $user = User::factory()->create();
    $search = Search::factory()->create(['user_id' => $user->id]);
    Prospect::factory()->create([
        'search_id' => $search->id,
        'business_name' => 'Acme Dental',
    ]);

    $response = $this->actingAs($user)->post('/exports');

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $this->assertStringContainsString('Acme Dental', $response->streamedContent());
    $this->assertDatabaseHas('exports', ['user_id' => $user->id, 'row_count' => 1]);
}

public function test_export_returns_422_when_no_rows(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)->post('/exports')->assertStatus(422);
}
```

- [ ] **Step 2: ExportController**

```php
public function store(Request $request): StreamedResponse|RedirectResponse
{
    $filters = $request->only([
        'from', 'to', 'niche', 'city', 'scan_type', 'min_score', 'dominant_angle', 'warm',
    ]);

    $prospects = (new ProspectListQuery($request->user()))
        ->apply($filters)
        ->query()
        ->with(['search', 'report', 'outreachEmails' => fn ($q) => $q->latest()->limit(1)])
        ->get();

    if ($prospects->isEmpty()) {
        return back()->withErrors(['export' => 'No prospects match filters.']);
    }

    $filename = 'prospects-'.now()->format('Y-m-d-His').'.csv';

    Export::create([
        'user_id'   => $request->user()->id,
        'search_id' => null,
        'filename'  => $filename,
        'row_count' => $prospects->count(),
    ]);

    return response()->streamDownload(function () use ($prospects) {
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'business_name', 'niche', 'city', 'country', 'phone', 'website_url',
            'combined_score', 'gbp_score', 'a11y_score', 'dominant_angle',
            'gbp_flags', 'a11y_flags', 'report_url',
            'outreach_subject', 'outreach_sent_at', 'response_received',
        ]);
        foreach ($prospects as $p) {
            $email = $p->outreachEmails->first();
            fputcsv($out, [
                $p->business_name,
                $p->search->niche,
                $p->search->city,
                $p->search->country,
                $p->phone,
                $p->website_url,
                $p->combined_score,
                $p->gbp_score,
                $p->a11y_score,
                $p->dominant_angle,
                implode('; ', $p->gbp_flags ?? []),
                implode('; ', $p->a11y_flags ?? []),
                $p->report ? url('/r/'.$p->report->token) : '',
                $email?->subject_line ?? '',
                $email?->sent_at?->toDateTimeString() ?? '',
                $email?->response_received ? '1' : '0',
            ]);
        }
        fclose($out);
    }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
}
```

Route: `Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');`

- [ ] **Step 3: Run tests, commit**

---

### Task 6: Reports dashboard

**Files:**
- Create: `app/Http/Controllers/ReportDashboardController.php`
- Create: `resources/js/Pages/Reports/Index.jsx`
- Modify: `routes/web.php`

- [ ] **Step 1: Controller**

Query `ProspectReport::query()->whereHas('prospect.search', fn ($q) => $q->where('user_id', auth()->id()))`.

Filters:
- `niche` → whereHas search
- `viewed` → `viewed_at` not null / null
- `warm` → `viewed_at >= now()->subDays(7)`

Sort: `orderByRaw('viewed_at DESC NULLS LAST')` (PostgreSQL) or `orderByDesc('viewed_at')` then `orderByDesc('created_at')`.

Map props including `is_engaged_badge` => `$report->viewed_at?->gte(now()->subDays(7))`.

- [ ] **Step 2: Reports/Index.jsx**

Table + filters + copy URL button (`navigator.clipboard.writeText`).

- [ ] **Step 3: Test + commit**

```php
$this->actingAs($user)->get('/reports')->assertOk();
```

---

### Task 7: Outreach selection API

**Files:**
- Create: `app/Http/Controllers/OutreachController.php` (partial — selection methods)
- Create: `app/Policies/OutreachSelectionPolicy.php`
- Create: `tests/Feature/OutreachSelectionTest.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Policy**

```php
public function delete(User $user, OutreachSelection $selection): bool
{
    return $user->id === $selection->user_id;
}
```

- [ ] **Step 2: Failing tests**

```php
public function test_user_can_add_prospect_to_selection(): void
{
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
    ]);

    $this->actingAs($user)
        ->post('/outreach/selections', ['prospect_ids' => [$prospect->id]])
        ->assertRedirect();

    $this->assertDatabaseHas('outreach_selections', [
        'user_id' => $user->id,
        'prospect_id' => $prospect->id,
    ]);
}

public function test_user_cannot_add_another_users_prospect(): void
{
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'search_id' => Search::factory()->create(['user_id' => $owner->id])->id,
    ]);

    $this->actingAs($other)
        ->post('/outreach/selections', ['prospect_ids' => [$prospect->id]])
        ->assertForbidden();
}
```

Controller `storeSelection`:

```php
$request->validate(['prospect_ids' => 'required|array', 'prospect_ids.*' => 'integer']);

foreach ($request->prospect_ids as $id) {
    $prospect = Prospect::findOrFail($id);
    $this->authorize('view', $prospect);
    OutreachSelection::firstOrCreate([
        'user_id' => $request->user()->id,
        'prospect_id' => $prospect->id,
    ]);
}

return back();
```

`destroySelection`: authorize policy, delete.

`clearSelections`: `OutreachSelection::where('user_id', $user->id)->delete();`

- [ ] **Step 3: Routes**

```php
Route::get('/outreach', [OutreachController::class, 'index'])->name('outreach.index');
Route::post('/outreach/selections', [OutreachController::class, 'storeSelection'])->name('outreach.selections.store');
Route::delete('/outreach/selections', [OutreachController::class, 'clearSelections'])->name('outreach.selections.clear');
Route::delete('/outreach/selections/{prospect}', [OutreachController::class, 'destroySelection'])->name('outreach.selections.destroy');
```

- [ ] **Step 4: Run tests, commit**

---

### Task 8: Extend outreach generation (job + service)

**Files:**
- Modify: `app/Jobs/GenerateOutreachEmailJob.php`
- Modify: `app/Services/OutreachEmailGeneratorService.php`
- Create: `tests/Feature/OutreachGenerateTest.php`

- [ ] **Step 1: Add options DTO or array on job**

```php
public function __construct(
    public Prospect $prospect,
    public User $user,
    public array $options = [],
) {}

// options keys: pitch_angle (auto|gbp|accessibility|combined), agency_name, cpc_benchmark
```

Pass to generator: `$generator->generate($prospect, $prospect->report, $this->options);`

- [ ] **Step 2: Update OutreachEmailGeneratorService**

```php
public function generate(Prospect $prospect, ?ProspectReport $report = null, array $options = []): array
{
    $pitchAngle = $options['pitch_angle'] ?? 'auto';
    if ($pitchAngle === 'auto') {
        $pitchAngle = $this->resolvePitchAngle($prospect);
    }
    // inject agency_name and cpc_benchmark into buildUserPrompt when set
}
```

In `buildUserPrompt`:

```php
if (!empty($options['agency_name'])) {
    $lines[] = "Sign the email from: {$options['agency_name']}";
}
if (!empty($options['cpc_benchmark'])) {
    $lines[] = "GBP CPC benchmark for this niche: £{$options['cpc_benchmark']} per click";
}
```

- [ ] **Step 3: Failing batch generate test**

Use `Queue::fake()`:

```php
public function test_generate_dispatches_only_for_prospects_with_reports(): void
{
    Queue::fake();
    $user = User::factory()->create();
    $search = Search::factory()->create(['user_id' => $user->id]);

    $withReport = Prospect::factory()->create(['search_id' => $search->id]);
    ProspectReport::factory()->create(['prospect_id' => $withReport->id]);
    OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $withReport->id]);

    $without = Prospect::factory()->create(['search_id' => $search->id]);
    OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $without->id]);

    $this->actingAs($user)->post('/outreach/generate', [
        'pitch_angle' => 'auto',
    ])->assertRedirect();

    Queue::assertPushed(GenerateOutreachEmailJob::class, 1);
}
```

- [ ] **Step 4: OutreachController@generate**

```php
public function generate(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'agency_name'   => 'nullable|string|max:100',
        'pitch_angle'   => 'required|in:auto,gbp,accessibility,combined',
        'cpc_benchmark' => 'nullable|numeric|min:0',
    ]);

    $selections = $request->user()->outreachSelections()->with('prospect.report')->get();
    $dispatched = 0;
    $skipped = [];

    foreach ($selections as $selection) {
        if (!$selection->prospect->report) {
            $skipped[] = $selection->prospect->business_name;
            continue;
        }
        GenerateOutreachEmailJob::dispatch(
            $selection->prospect,
            $request->user(),
            $validated,
        )->onQueue('auditing');
        $dispatched++;
    }

    return back()->with([
        'success' => "{$dispatched} email(s) queued.",
        'skipped' => $skipped,
    ]);
}
```

Route: `POST /outreach/generate`

Update `ProspectController::generateOutreach` to pass empty options array for backward compatibility.

- [ ] **Step 5: Run tests, commit**

---

### Task 9: Outreach page UI

**Files:**
- Create: `resources/js/Pages/Outreach/Index.jsx`
- Create: `resources/js/Components/OutreachEmailCard.jsx`
- Modify: `resources/js/Pages/Prospect/Show.jsx` (use shared card)
- Modify: `app/Http/Controllers/OutreachController.php` (`index` method)

- [ ] **Step 1: OutreachController@index**

Load selections with prospect.search.report; load recent outreach emails for selected prospect ids:

```php
$emails = OutreachEmail::where('user_id', $user->id)
    ->whereIn('prospect_id', $prospectIds)
    ->latest()
    ->get()
    ->groupBy('prospect_id');
```

Props: `selection`, `emailsByProspect`, `selectionCount`, flash skipped list.

- [ ] **Step 2: Extract OutreachEmailCard**

Move card markup from `Prospect/Show.jsx` — props: `email`, `onMarkSent`, `onMarkResponse`.

- [ ] **Step 3: Outreach/Index.jsx**

Two-column layout; generation form; display skipped names from flash; after generate show "Refresh" or 5s `router.reload({ only: ['emailsByProspect'] })`.

- [ ] **Step 4: Manual smoke**

1. Add prospects to queue from `/saved`
2. Generate with/without reports — confirm flash skipped list
3. Mark sent on card

- [ ] **Step 5: Commit**

---

### Task 10: Search page selection + nav + redirect

**Files:**
- Modify: `app/Http/Controllers/SearchController.php`
- Modify: `resources/js/Pages/Search/Show.jsx`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`
- Modify: `routes/web.php`

- [ ] **Step 1: Pass outreachProspectIds to Search/Show**

```php
'outreachProspectIds' => auth()->user()
    ->outreachSelections()
    ->pluck('prospect_id'),
```

- [ ] **Step 2: Search/Show checkboxes**

- Column with checkbox per row
- Toolbar: "Add selected to outreach" → POST prospect_ids
- Per-row link if not in queue
- Badge "In outreach" when id in `outreachProspectIds`

- [ ] **Step 3: Nav links**

```jsx
<NavLink href="/search" active={route().current('search.*')}>Search</NavLink>
<NavLink href="/outreach" active={route().current('outreach.*')}>
  Outreach {selectionCount > 0 && `(${selectionCount})`}
</NavLink>
<NavLink href="/saved" active={route().current('saved.*')}>Saved</NavLink>
<NavLink href="/reports" active={route().current('reports.*')}>Reports</NavLink>
```

Share `selectionCount` via `HandleInertiaRequests` middleware:

```php
'outreachSelectionCount' => fn () => $request->user()
    ? $request->user()->outreachSelections()->count()
    : 0,
```

- [ ] **Step 4: Root redirect**

```php
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('search.index');
    }
    return Inertia::render('Welcome', [...]);
});
```

- [ ] **Step 5: Run full test suite**

Run: `php artisan test`
Expected: all pass

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: complete operator UI with outreach queue and nav"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|---|---|
| outreach_selections table | Task 1 |
| exports table | Task 1 |
| Warm lead definition B | Task 3 |
| /saved + filters + warm panel | Task 4 |
| CSV export | Task 5 |
| /reports + 7-day badge | Task 6 |
| Selection CRUD | Task 7 |
| /outreach batch generate | Task 8–9 |
| pitch_angle, agency_name, cpc on form | Task 8 |
| Search checkboxes | Task 10 |
| Nav + redirect | Task 10 |
| Skip generate without report | Task 8 |
| Out of scope items | Not in plan |

---

## Verification checklist (manual)

- [ ] Run search, open `/saved`, filter by niche
- [ ] Export CSV with rows matching filter
- [ ] View report in incognito → appears on `/reports` with view count
- [ ] Mark outreach sent → prospect appears in warm panel only after report viewed + sent
- [ ] Queue 3 prospects on `/outreach`, generate — skipped shown for missing reports
- [ ] Horizon processing `auditing` queue for outreach jobs
