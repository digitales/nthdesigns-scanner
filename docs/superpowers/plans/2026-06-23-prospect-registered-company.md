# Prospect registered company — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let operators register a legal company name and/or Companies House number on a prospect so CH lookups use the correct entity when the business trades under a different name.

**Architecture:** Seven new columns on `prospects` mirror the `validator_override_*` pattern. `RegisteredCompanyController` handles save/clear. `CompaniesHouseLookupService` resolves registered number → registered name → `business_name`. UI lives inside the existing `CompaniesHouseControl` card.

**Tech Stack:** Laravel 13, Inertia/React, PHPUnit, Companies House REST API.

**Spec:** `docs/superpowers/specs/2026-06-23-prospect-registered-company-design.md`

---

## File map

| File | Action | Responsibility |
|------|--------|----------------|
| `database/migrations/2026_06_23_100000_add_registered_company_columns_to_prospects_table.php` | Create | Schema |
| `app/Models/Prospect.php` | Modify | Fillable, casts, relationships |
| `app/Http/Requests/StoreRegisteredCompanyRequest.php` | Create | Input validation |
| `app/Http/Controllers/RegisteredCompanyController.php` | Create | Save/clear endpoints |
| `routes/web.php` | Modify | Two new routes |
| `app/Services/CompaniesHouseLookupService.php` | Modify | Lookup resolution order |
| `app/Http/Resources/ProspectShowResource.php` | Modify | Expose fields + auditor names |
| `resources/js/Components/CompaniesHouseControl.jsx` | Modify | Registration form UI |
| `tests/Feature/RegisteredCompanyTest.php` | Create | HTTP/feature tests |
| `tests/Unit/CompaniesHouseLookupServiceTest.php` | Modify | Registered lookup tests |

---

### Task 1: Migration

**Files:**
- Create: `database/migrations/2026_06_23_100000_add_registered_company_columns_to_prospects_table.php`

- [ ] **Step 1: Create migration**

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
            $table->string('registered_company_name')->nullable()->after('companies_house_checked_at');
            $table->string('registered_company_number')->nullable()->after('registered_company_name');
            $table->text('registered_company_note')->nullable()->after('registered_company_number');
            $table->foreignId('registered_company_by')->nullable()->after('registered_company_note')->constrained('users')->nullOnDelete();
            $table->timestamp('registered_company_at')->nullable()->after('registered_company_by');
            $table->foreignId('registered_company_cleared_by')->nullable()->after('registered_company_at')->constrained('users')->nullOnDelete();
            $table->timestamp('registered_company_cleared_at')->nullable()->after('registered_company_cleared_by');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_company_by');
            $table->dropConstrainedForeignId('registered_company_cleared_by');
            $table->dropColumn([
                'registered_company_name',
                'registered_company_number',
                'registered_company_note',
                'registered_company_at',
                'registered_company_cleared_at',
            ]);
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: migration runs without error

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_23_100000_add_registered_company_columns_to_prospects_table.php
git commit -m "Add registered company columns to prospects table."
```

---

### Task 2: Prospect model

**Files:**
- Modify: `app/Models/Prospect.php`

- [ ] **Step 1: Add fillable fields**

Add to `$fillable` array after `companies_house_checked_at`:

```php
'registered_company_name', 'registered_company_number', 'registered_company_note',
'registered_company_by', 'registered_company_at',
'registered_company_cleared_by', 'registered_company_cleared_at',
```

- [ ] **Step 2: Add casts**

Add to `casts()`:

```php
'registered_company_at' => 'datetime',
'registered_company_cleared_at' => 'datetime',
```

- [ ] **Step 3: Add relationships**

Add before closing brace of class:

```php
public function registeredCompanyBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'registered_company_by');
}

public function registeredCompanyClearedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'registered_company_cleared_by');
}
```

Add `use App\Models\User;` if not already imported via same namespace (User is in App\Models — use fully qualified or import).

- [ ] **Step 4: Commit**

```bash
git add app/Models/Prospect.php
git commit -m "Add registered company fields and relationships to Prospect model."
```

---

### Task 3: Form request validation

**Files:**
- Create: `app/Http/Requests/StoreRegisteredCompanyRequest.php`
- Create: `tests/Feature/RegisteredCompanyTest.php`

- [ ] **Step 1: Write failing validation tests**

Create `tests/Feature/RegisteredCompanyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\CheckCompaniesHouseJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RegisteredCompanyTest extends TestCase
{
    use RefreshDatabase;

    private function prospectFor(User $user): Prospect
    {
        return Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);
    }

    public function test_save_requires_at_least_one_of_name_or_number(): void
    {
        $user = User::factory()->create();
        $prospect = $this->prospectFor($user);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/registered-company", [
                'name' => '',
                'number' => '',
            ])
            ->assertSessionHasErrors(['name', 'number']);
    }

    public function test_save_rejects_invalid_company_number(): void
    {
        $user = User::factory()->create();
        $prospect = $this->prospectFor($user);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/registered-company", [
                'number' => 'ABC',
            ])
            ->assertSessionHasErrors(['number']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=RegisteredCompanyTest`
Expected: FAIL (route/class not found)

- [ ] **Step 3: Create form request**

Create `app/Http/Requests/StoreRegisteredCompanyRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRegisteredCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{8}$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $name = trim((string) $this->input('name'));
            $number = trim((string) $this->input('number'));

            if ($name === '' && $number === '') {
                $validator->errors()->add('name', 'Enter a registered company name or number.');
                $validator->errors()->add('number', 'Enter a registered company name or number.');
            }
        });
    }

    /**
     * @return array{name: ?string, number: ?string, note: ?string}
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        $name = trim((string) ($validated['name'] ?? ''));
        $number = strtoupper(trim((string) ($validated['number'] ?? '')));
        $note = trim((string) ($validated['note'] ?? ''));

        return [
            'name' => $name !== '' ? $name : null,
            'number' => $number !== '' ? $number : null,
            'note' => $note !== '' ? $note : null,
        ];
    }
}
```

- [ ] **Step 4: Add routes and stub controller so validation tests can run**

Add to `routes/web.php` after companies-house routes:

```php
Route::post('/prospects/{prospect}/registered-company', [RegisteredCompanyController::class, 'store'])->name('prospects.registered-company.store');
Route::delete('/prospects/{prospect}/registered-company', [RegisteredCompanyController::class, 'destroy'])->name('prospects.registered-company.destroy');
```

Add import: `use App\Http\Controllers\RegisteredCompanyController;`

Create minimal `app/Http/Controllers/RegisteredCompanyController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegisteredCompanyRequest;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;

class RegisteredCompanyController extends Controller
{
    public function store(StoreRegisteredCompanyRequest $request, Prospect $prospect): RedirectResponse
    {
        abort(501);
    }

    public function destroy(Prospect $prospect): RedirectResponse
    {
        abort(501);
    }
}
```

- [ ] **Step 5: Run validation tests**

Run: `php artisan test --filter='test_save_requires_at_least_one|test_save_rejects_invalid'`
Expected: PASS (422 session errors, not 501 — if 501, validation runs before controller body; session errors should still work)

Note: Laravel runs FormRequest validation before controller — tests should pass with session errors.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/StoreRegisteredCompanyRequest.php app/Http/Controllers/RegisteredCompanyController.php routes/web.php tests/Feature/RegisteredCompanyTest.php
git commit -m "Add registered company form request and validation tests."
```

---

### Task 4: Controller save and clear

**Files:**
- Modify: `app/Http/Controllers/RegisteredCompanyController.php`
- Modify: `tests/Feature/RegisteredCompanyTest.php`

- [ ] **Step 1: Write failing feature tests**

Append to `tests/Feature/RegisteredCompanyTest.php`:

```php
public function test_operator_can_save_registration_with_number_only(): void
{
    Bus::fake();

    $user = User::factory()->create();
    $prospect = $this->prospectFor($user);

    $this->actingAs($user)
        ->post("/prospects/{$prospect->id}/registered-company", [
            'number' => '12345678',
            'note' => 'From website footer',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $prospect->refresh();

    $this->assertNull($prospect->registered_company_name);
    $this->assertSame('12345678', $prospect->registered_company_number);
    $this->assertSame('From website footer', $prospect->registered_company_note);
    $this->assertSame($user->id, $prospect->registered_company_by);
    $this->assertNotNull($prospect->registered_company_at);
    $this->assertNull($prospect->registered_company_cleared_by);
    $this->assertNull($prospect->registered_company_cleared_at);

    Bus::assertDispatched(CheckCompaniesHouseJob::class);
}

public function test_first_save_dispatches_check_only_when_never_checked_before(): void
{
    Bus::fake();

    $user = User::factory()->create();
    $prospect = $this->prospectFor($user);
    $prospect->update(['companies_house_checked_at' => now()]);

    $this->actingAs($user)
        ->post("/prospects/{$prospect->id}/registered-company", [
            'name' => 'North West Dental Holdings Ltd',
        ])
        ->assertRedirect();

    Bus::assertNotDispatched(CheckCompaniesHouseJob::class);
}

public function test_operator_can_clear_registration(): void
{
    Bus::fake();

    $user = User::factory()->create();
    $prospect = $this->prospectFor($user);
    $prospect->update([
        'registered_company_name' => 'North West Dental Holdings Ltd',
        'registered_company_number' => '12345678',
        'registered_company_note' => 'Manual',
        'registered_company_by' => $user->id,
        'registered_company_at' => now(),
        'companies_house_number' => '12345678',
        'companies_house_status' => 'matched',
        'companies_house_checked_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete("/prospects/{$prospect->id}/registered-company")
        ->assertRedirect()
        ->assertSessionHas('success');

    $prospect->refresh();

    $this->assertNull($prospect->registered_company_name);
    $this->assertNull($prospect->registered_company_number);
    $this->assertNull($prospect->registered_company_note);
    $this->assertNull($prospect->registered_company_by);
    $this->assertNull($prospect->registered_company_at);
    $this->assertSame($user->id, $prospect->registered_company_cleared_by);
    $this->assertNotNull($prospect->registered_company_cleared_at);
    $this->assertSame('12345678', $prospect->companies_house_number);

    Bus::assertNotDispatched(CheckCompaniesHouseJob::class);
}

public function test_clear_is_idempotent_when_no_active_registration(): void
{
    $user = User::factory()->create();
    $prospect = $this->prospectFor($user);

    $this->actingAs($user)
        ->delete("/prospects/{$prospect->id}/registered-company")
        ->assertRedirect()
        ->assertSessionHas('success');
}

public function test_re_register_after_clear_resets_cleared_audit_fields(): void
{
    Bus::fake();

    $user = User::factory()->create();
    $prospect = $this->prospectFor($user);
    $prospect->update([
        'registered_company_cleared_by' => $user->id,
        'registered_company_cleared_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->post("/prospects/{$prospect->id}/registered-company", [
            'name' => 'North West Dental Holdings Ltd',
        ])
        ->assertRedirect();

    $prospect->refresh();

    $this->assertSame('North West Dental Holdings Ltd', $prospect->registered_company_name);
    $this->assertNull($prospect->registered_company_cleared_by);
    $this->assertNull($prospect->registered_company_cleared_at);

    Bus::assertDispatched(CheckCompaniesHouseJob::class);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=RegisteredCompanyTest`
Expected: FAIL on save/clear tests (501 or missing assertions)

- [ ] **Step 3: Implement controller**

Replace `RegisteredCompanyController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegisteredCompanyRequest;
use App\Jobs\CheckCompaniesHouseJob;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;

class RegisteredCompanyController extends Controller
{
    public function store(StoreRegisteredCompanyRequest $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('view', $prospect);

        $validated = $request->validated();
        $shouldCheck = $prospect->companies_house_checked_at === null;

        $prospect->update([
            'registered_company_name' => $validated['name'],
            'registered_company_number' => $validated['number'],
            'registered_company_note' => $validated['note'],
            'registered_company_by' => $request->user()->id,
            'registered_company_at' => now(),
            'registered_company_cleared_by' => null,
            'registered_company_cleared_at' => null,
        ]);

        if ($shouldCheck) {
            CheckCompaniesHouseJob::dispatch($prospect->fresh());
        }

        return back()->with('success', $shouldCheck
            ? 'Registered company saved — Companies House check queued.'
            : 'Registered company saved.');
    }

    public function destroy(Prospect $prospect): RedirectResponse
    {
        $this->authorize('view', $prospect);

        $prospect->update([
            'registered_company_name' => null,
            'registered_company_number' => null,
            'registered_company_note' => null,
            'registered_company_by' => null,
            'registered_company_at' => null,
            'registered_company_cleared_by' => auth()->id(),
            'registered_company_cleared_at' => now(),
        ]);

        return back()->with('success', 'Registered company cleared.');
    }
}
```

- [ ] **Step 4: Run feature tests**

Run: `php artisan test --filter=RegisteredCompanyTest`
Expected: all PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/RegisteredCompanyController.php tests/Feature/RegisteredCompanyTest.php
git commit -m "Add registered company save and clear controller."
```

---

### Task 5: Companies House lookup service

**Files:**
- Modify: `app/Services/CompaniesHouseLookupService.php`
- Modify: `tests/Unit/CompaniesHouseLookupServiceTest.php`

- [ ] **Step 1: Write failing unit tests**

Append to `tests/Unit/CompaniesHouseLookupServiceTest.php`:

```php
public function test_check_uses_registered_company_number_directly(): void
{
    config(['services.companies_house.key' => 'test-key']);

    $prospect = $this->makeProspect([
        'business_name' => 'Smile Dental Manchester',
        'registered_company_number' => '87654321',
    ]);

    Http::fake([
        '*/search/companies*' => function () {
            throw new \RuntimeException('Search should not be called when number is registered');
        },
        '*/company/87654321/charges*' => Http::response(['total_count' => 0]),
        '*/company/87654321' => Http::response([
            'company_status' => 'active',
            'date_of_creation' => now()->subYears(3)->toDateString(),
            'accounts' => ['overdue' => false],
        ]),
    ]);

    app(CompaniesHouseLookupService::class)->check($prospect);

    $prospect->refresh();

    $this->assertSame('87654321', $prospect->companies_house_number);
    $this->assertSame(ProspectFinancialStatus::Matched, $prospect->companies_house_status);
}

public function test_check_uses_registered_company_name_instead_of_business_name(): void
{
    config(['services.companies_house.key' => 'test-key']);

    $prospect = $this->makeProspect([
        'business_name' => 'Smile Dental Manchester',
        'registered_company_name' => 'North West Dental Holdings Ltd',
        'address' => '1 High Street, Bristol, BS1 4ST',
    ]);

    Http::fake([
        '*/search/companies*' => function ($request) {
            $query = $request->data()['q'] ?? '';
            if ($query !== 'North West Dental Holdings Ltd') {
                return Http::response(['items' => []]);
            }

            return Http::response(['items' => [
                [
                    'company_number' => '11223344',
                    'title' => 'NORTH WEST DENTAL HOLDINGS LTD',
                    'address_snippet' => '1 High Street, Bristol, BS1 4ST',
                ],
            ]]);
        },
        '*/company/11223344/charges*' => Http::response(['total_count' => 0]),
        '*/company/11223344' => Http::response([
            'company_status' => 'active',
            'date_of_creation' => now()->subYears(4)->toDateString(),
            'accounts' => ['overdue' => false],
        ]),
    ]);

    app(CompaniesHouseLookupService::class)->check($prospect);

    $prospect->refresh();

    $this->assertSame('11223344', $prospect->companies_house_number);
    $this->assertStringContainsString('Matched via registered company name', $prospect->companies_house_summary);
}

public function test_check_sets_caution_when_registered_number_not_found(): void
{
    config(['services.companies_house.key' => 'test-key']);

    $prospect = $this->makeProspect([
        'business_name' => 'Smile Dental Manchester',
        'registered_company_number' => '99999999',
    ]);

    Http::fake([
        '*/company/99999999' => Http::response([], 404),
    ]);

    app(CompaniesHouseLookupService::class)->check($prospect);

    $prospect->refresh();

    $this->assertSame(ProspectFinancialStatus::Caution, $prospect->companies_house_status);
    $this->assertContains('Registered company number not found on Companies House', $prospect->companies_house_flags);
}

public function test_check_falls_back_to_business_name_when_no_registration(): void
{
    config(['services.companies_house.key' => 'test-key']);

    $prospect = $this->makeProspect([
        'business_name' => 'Acorn Dental Practice',
        'address' => '1 High Street, Bristol, BS1 4ST',
    ]);

    Http::fake([
        '*/search/companies*' => Http::response(['items' => [
            [
                'company_number' => '12345678',
                'title' => 'ACORN DENTAL PRACTICE LTD',
                'address_snippet' => '1 High Street, Bristol, BS1 4ST',
            ],
        ]]),
        '*/company/12345678/charges*' => Http::response(['total_count' => 0]),
        '*/company/12345678' => Http::response([
            'company_status' => 'active',
            'date_of_creation' => now()->subYears(5)->toDateString(),
            'accounts' => ['overdue' => false],
        ]),
    ]);

    app(CompaniesHouseLookupService::class)->check($prospect);

    $prospect->refresh();

    $this->assertSame('12345678', $prospect->companies_house_number);
    $this->assertStringNotContainsString('Matched via registered company name', (string) $prospect->companies_house_summary);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter='test_check_uses_registered|test_check_sets_caution_when_registered|test_check_falls_back_to_business_name_when_no_registration'`
Expected: FAIL

- [ ] **Step 3: Refactor `CompaniesHouseLookupService::check()`**

Replace the `check()` method and add private helpers:

```php
public function check(Prospect $prospect): void
{
    if (blank($this->apiKey())) {
        $prospect->update([
            'companies_house_status' => ProspectFinancialStatus::Caution->value,
            'companies_house_summary' => 'Companies House API key not configured — check skipped.',
            'companies_house_flags' => ['Companies House API key not configured'],
            'companies_house_checked_at' => now(),
        ]);

        return;
    }

    if (filled($prospect->registered_company_number)) {
        $this->checkByRegisteredNumber($prospect);

        return;
    }

    $searchName = filled($prospect->registered_company_name)
        ? (string) $prospect->registered_company_name
        : (string) $prospect->business_name;

    if (blank($searchName)) {
        return;
    }

    $viaRegisteredName = filled($prospect->registered_company_name);
    $candidates = $this->search($searchName);
    $match = $this->bestMatch($candidates, $prospect);

    if ($match === null) {
        $prospect->update([
            'companies_house_number' => null,
            'companies_house_status' => ProspectFinancialStatus::NoMatch->value,
            'companies_house_summary' => 'No confident Companies House match — likely a sole trader or partnership.',
            'companies_house_flags' => ['No Companies House match — likely sole trader or partnership'],
            'raw_companies_house_payload' => null,
            'companies_house_checked_at' => now(),
        ]);

        return;
    }

    $this->applyMatch($prospect, $match['company_number'], $viaRegisteredName);
}

private function checkByRegisteredNumber(Prospect $prospect): void
{
    $companyNumber = (string) $prospect->registered_company_number;
    $profile = $this->profile($companyNumber);

    if ($profile === null) {
        $prospect->update([
            'companies_house_number' => $companyNumber,
            'companies_house_status' => ProspectFinancialStatus::Caution->value,
            'companies_house_summary' => 'Registered company number not found on Companies House — verify manually.',
            'companies_house_flags' => ['Registered company number not found on Companies House'],
            'raw_companies_house_payload' => null,
            'companies_house_checked_at' => now(),
        ]);

        return;
    }

    $this->applyMatch($prospect, $companyNumber, false);
}

private function applyMatch(Prospect $prospect, string $companyNumber, bool $viaRegisteredName): void
{
    $profile = $this->profile($companyNumber);

    if ($profile === null) {
        $prospect->update([
            'companies_house_number' => $companyNumber,
            'companies_house_status' => ProspectFinancialStatus::Caution->value,
            'companies_house_summary' => 'Matched a company number but could not fetch its profile — verify manually.',
            'companies_house_flags' => ['Could not fetch Companies House profile'],
            'companies_house_checked_at' => now(),
        ]);

        return;
    }

    $chargeCount = $this->chargeCount($companyNumber);
    [$status, $flags, $summary] = $this->assess($profile, $chargeCount);

    if ($viaRegisteredName) {
        $summary = "Matched via registered company name — {$summary}";
    }

    $prospect->update([
        'companies_house_number' => $companyNumber,
        'companies_house_status' => $status->value,
        'companies_house_summary' => $summary,
        'companies_house_flags' => $flags,
        'raw_companies_house_payload' => array_merge($profile, ['charge_count' => $chargeCount]),
        'companies_house_checked_at' => now(),
    ]);
}
```

Note: `checkByRegisteredNumber` calls `profile()` once; `applyMatch` calls it again. Acceptable for clarity; optional optimisation to pass profile through.

- [ ] **Step 4: Run all Companies House unit tests**

Run: `php artisan test tests/Unit/CompaniesHouseLookupServiceTest.php`
Expected: all PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/CompaniesHouseLookupService.php tests/Unit/CompaniesHouseLookupServiceTest.php
git commit -m "Use registered company details in Companies House lookup."
```

---

### Task 6: Prospect show resource

**Files:**
- Modify: `app/Http/Resources/ProspectShowResource.php`

- [ ] **Step 1: Eager-load auditor relationships**

In `ProspectShowController` or wherever prospect is loaded for show — find the query and add:

```php
$prospect->loadMissing(['registeredCompanyBy', 'registeredCompanyClearedBy']);
```

Search for where `ProspectShowResource::format` is called (likely `ProspectController@show`) and add the `loadMissing` call on the prospect before formatting.

- [ ] **Step 2: Expose fields in `prospect()` method**

Add after `companies_house_checked_at`:

```php
'registered_company_name' => $prospect->registered_company_name,
'registered_company_number' => $prospect->registered_company_number,
'registered_company_note' => $prospect->registered_company_note,
'registered_company_at' => $prospect->registered_company_at?->toISOString(),
'registered_company_by_name' => $prospect->registeredCompanyBy?->name,
'registered_company_cleared_at' => $prospect->registered_company_cleared_at?->toISOString(),
'registered_company_cleared_by_name' => $prospect->registeredCompanyClearedBy?->name,
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Resources/ProspectShowResource.php app/Http/Controllers/ProspectController.php
git commit -m "Expose registered company fields on prospect show page."
```

---

### Task 7: Companies House control UI

**Files:**
- Modify: `resources/js/Components/CompaniesHouseControl.jsx`

- [ ] **Step 1: Add registration state and helpers**

At top of component, add state:

```javascript
const hasRegistration = Boolean(
    prospect.registered_company_name || prospect.registered_company_number,
);
const wasCleared = Boolean(
    !hasRegistration && prospect.registered_company_cleared_at,
);
const [showForm, setShowForm] = useState(!hasRegistration && !wasCleared);
const [confirmClear, setConfirmClear] = useState(false);
const [form, setForm] = useState({
    name: prospect.registered_company_name ?? '',
    number: prospect.registered_company_number ?? '',
    note: prospect.registered_company_note ?? '',
});
```

Add handlers:

```javascript
const saveRegistration = (e) => {
    e.preventDefault();
    router.post(`/prospects/${prospect.id}/registered-company`, {
        name: form.name.trim() || null,
        number: form.number.trim() || null,
        note: form.note.trim() || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            setShowForm(false);
            setConfirmClear(false);
        },
    });
};

const clearRegistration = () => {
    router.delete(`/prospects/${prospect.id}/registered-company`, {
        preserveScroll: true,
        onSuccess: () => {
            setConfirmClear(false);
            setForm({ name: '', number: '', note: '' });
            setShowForm(true);
        },
    });
};
```

- [ ] **Step 2: Add registration section above check status**

Insert before the check status header (`companies-house-control-header`):

```jsx
<Stack gap={10} className="registered-company-section">
    <p className="micro text-stone">Registered company</p>

    {wasCleared && (
        <p className="micro text-stone">
            Registration cleared
            {prospect.registered_company_cleared_by_name
                ? ` by ${prospect.registered_company_cleared_by_name}`
                : ''}
            {prospect.registered_company_cleared_at
                ? ` on ${new Date(prospect.registered_company_cleared_at).toLocaleString()}`
                : ''}
            .
        </p>
    )}

    {hasRegistration && !showForm && (
        <Stack gap={6}>
            {prospect.registered_company_name && (
                <p className="micro">Name: {prospect.registered_company_name}</p>
            )}
            {prospect.registered_company_number && (
                <p className="micro">Number: {prospect.registered_company_number}</p>
            )}
            {prospect.registered_company_note && (
                <p className="micro text-stone">Note: {prospect.registered_company_note}</p>
            )}
            {prospect.registered_company_at && (
                <p className="micro text-stone">
                    Registered
                    {prospect.registered_company_by_name
                        ? ` by ${prospect.registered_company_by_name}`
                        : ''}
                    {' '}on {new Date(prospect.registered_company_at).toLocaleString()}
                </p>
            )}
            <Stack direction="row" gap={8}>
                <Button kind="ghost" size="sm" onClick={() => setShowForm(true)}>Edit</Button>
                {!confirmClear ? (
                    <Button kind="ghost" size="sm" onClick={() => setConfirmClear(true)}>Clear</Button>
                ) : (
                    <>
                        <Button kind="secondary" size="sm" onClick={clearRegistration}>Confirm clear</Button>
                        <Button kind="ghost" size="sm" onClick={() => setConfirmClear(false)}>Cancel</Button>
                    </>
                )}
            </Stack>
        </Stack>
    )}

    {showForm && (
        <Stack as="form" gap={10} onSubmit={saveRegistration}>
            <label className="micro">Registered company name</label>
            <input
                className="input"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                placeholder="Legal entity name"
            />
            <label className="micro">Companies House number</label>
            <input
                className="input"
                value={form.number}
                onChange={(e) => setForm((f) => ({ ...f, number: e.target.value }))}
                placeholder="8-character number"
                maxLength={8}
            />
            <label className="micro">Note (optional)</label>
            <textarea
                className="textarea w-full"
                rows={2}
                value={form.note}
                onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))}
                placeholder="e.g. Found on website footer"
            />
            <Stack direction="row" gap={8}>
                <Button kind="primary" size="sm" type="submit">Save registration</Button>
                {hasRegistration && (
                    <Button kind="ghost" size="sm" type="button" onClick={() => setShowForm(false)}>
                        Cancel
                    </Button>
                )}
            </Stack>
        </Stack>
    )}
</Stack>
```

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: build succeeds

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/CompaniesHouseControl.jsx
git commit -m "Add registered company form to Companies House card."
```

---

### Task 8: Final verification

- [ ] **Step 1: Run full test suite for touched areas**

Run:
```bash
php artisan test --filter='RegisteredCompanyTest|CompaniesHouseLookupServiceTest|CompaniesHouseCheckTest'
npm run build
```
Expected: all tests PASS, build succeeds

- [ ] **Step 2: Manual smoke test**

1. Open a prospect show page
2. Register a company name in the Companies House card → confirm job queues on first save
3. Click Recheck → confirm summary uses registered name
4. Register a company number → confirm direct match
5. Clear registration → confirm cleared notice appears and CH results remain

- [ ] **Step 3: Final commit if any fixups needed**

```bash
git add -A
git commit -m "Fix registered company implementation follow-ups."
```

Only run this step if fixups were required.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Registered name + number columns | Task 1 |
| Audit fields (by/at, cleared by/at) | Task 1, 4 |
| Number priority lookup | Task 5 |
| Registered name lookup | Task 5 |
| business_name fallback | Task 5 |
| Auto-recheck on first save only | Task 4 |
| Clear keeps CH results | Task 4 |
| Re-register resets cleared fields | Task 4 |
| Validation (name or number, 8-char number) | Task 3 |
| UI in Companies House card | Task 7 |
| Three UI states (none/active/cleared) | Task 7 |
| Prospect show resource exposure | Task 6 |
