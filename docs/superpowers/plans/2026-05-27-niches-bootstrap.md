# `niches:bootstrap` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a one-time `niches:bootstrap` Artisan command that fetches UK cities and filtered Places taxonomy niches, optionally validates them via Places search in Birmingham, and writes nested `config/niches.php` — with `niches:scan` updated to consume the new shape in the same PR.

**Architecture:** Single `NichesBootstrapCommand` class with private step methods, class constants for blocklists/fallbacks, `Http::timeout(15)` for ONS + taxonomy, constructor-injected `GooglePlacesService` for validation only. Config migration ships first so consumers never break.

**Tech Stack:** Laravel 13, `Illuminate\Support\Facades\Http`, `GooglePlacesService`, PHPUnit, `artisan` command testing.

**Spec:** `docs/superpowers/specs/2026-05-27-niches-bootstrap-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Console/Commands/NichesBootstrapCommand.php` | `niches:bootstrap` — fetch, filter, validate, write config |
| `app/Console/Commands/ScanNichesCommand.php` | Read `config('niches.niches')` / `config('niches.cities')` |
| `config/niches.php` | Nested `niches` + `cities` (migrate existing 20 entries + `primary_type`) |
| `tests/Feature/NichesBootstrapCommandTest.php` | Bootstrap command behaviour |
| `tests/Feature/ScanNichesCommandTest.php` | Nested config + default cities |

**Not modified:** migrations, jobs, UI, scheduler, `GooglePlacesService` internals.

---

### Task 1: Migrate config shape and update `niches:scan`

**Files:**
- Modify: `config/niches.php`
- Modify: `app/Console/Commands/ScanNichesCommand.php`
- Modify: `tests/Feature/ScanNichesCommandTest.php`

- [ ] **Step 1: Restructure `config/niches.php`**

Replace flat list with nested shape. Add `primary_type` per entry (reasonable Places type slug; match bootstrap fallbacks where obvious):

```php
<?php

return [
    'niches' => [
        ['label' => 'Dental Practice', 'query' => 'dental practice', 'primary_type' => 'dentist'],
        ['label' => 'Physiotherapist', 'query' => 'physiotherapist', 'primary_type' => 'physiotherapist'],
        ['label' => 'Solicitor', 'query' => 'solicitor', 'primary_type' => 'lawyer'],
        ['label' => 'Accountant', 'query' => 'accountant', 'primary_type' => 'accounting'],
        ['label' => 'Estate Agent', 'query' => 'estate agent', 'primary_type' => 'real_estate_agency'],
        ['label' => 'Independent Hotel', 'query' => 'independent hotel', 'primary_type' => 'lodging'],
        ['label' => 'Restaurant', 'query' => 'restaurant', 'primary_type' => 'restaurant'],
        ['label' => 'Optician', 'query' => 'optician', 'primary_type' => 'optician'],
        ['label' => 'Veterinary Practice', 'query' => 'veterinary practice', 'primary_type' => 'veterinary_care'],
        ['label' => 'Private GP', 'query' => 'private gp', 'primary_type' => 'doctor'],
        ['label' => 'Osteopath', 'query' => 'osteopath', 'primary_type' => 'physiotherapist'],
        ['label' => 'Chiropractor', 'query' => 'chiropractor', 'primary_type' => 'physiotherapist'],
        ['label' => 'Beauty Salon', 'query' => 'beauty salon', 'primary_type' => 'beauty_salon'],
        ['label' => 'Barbershop', 'query' => 'barbershop', 'primary_type' => 'hair_care'],
        ['label' => 'Plumber', 'query' => 'plumber', 'primary_type' => 'plumber'],
        ['label' => 'Electrician', 'query' => 'electrician', 'primary_type' => 'electrician'],
        ['label' => 'Architect', 'query' => 'architect', 'primary_type' => 'architect'],
        ['label' => 'Financial Adviser', 'query' => 'financial adviser', 'primary_type' => 'finance'],
        ['label' => 'Mortgage Broker', 'query' => 'mortgage broker', 'primary_type' => 'finance'],
        ['label' => 'Private Tutor', 'query' => 'private tutor', 'primary_type' => 'tutoring_center'],
    ],
    'cities' => [
        'Birmingham',
        'Manchester',
        'Leeds',
        'Bristol',
        'Edinburgh',
    ],
];
```

- [ ] **Step 2: Write failing test for nested config dispatch**

Add to `tests/Feature/ScanNichesCommandTest.php`:

```php
public function test_uses_nested_config_niches_and_default_cities(): void
{
    Queue::fake();

    config([
        'niches' => [
            'niches' => [
                ['label' => 'Plumber', 'query' => 'plumber', 'primary_type' => 'plumber'],
            ],
            'cities' => ['Leeds', 'Bristol'],
        ],
    ]);

    $this->artisan('niches:scan', ['--sample' => 1])
        ->expectsOutputToContain('Dispatched 2')
        ->assertExitCode(0);

    Queue::assertPushed(ScanNicheJob::class, 2);
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
php artisan test --filter=test_uses_nested_config_niches_and_default_cities
```

Expected: FAIL — `config('niches')` is not iterable as niche rows / wrong dispatch count.

- [ ] **Step 4: Update `ScanNichesCommand`**

```php
protected $signature = 'niches:scan
    {--cities=}
    {--niches=}
    {--sample=5}';

// In handle():
$defaultCities = collect(config('niches.cities', []))->filter()->implode(',');
if ($defaultCities === '') {
    $defaultCities = 'Birmingham,Manchester,Leeds,Bristol,Edinburgh';
}

$citiesOption = (string) $this->option('cities');
$cities = collect(explode(',', $citiesOption !== '' ? $citiesOption : $defaultCities))
    ->map(fn (string $c) => trim($c))
    ->filter()
    ->values();

$niches = collect(config('niches.niches', []))
    ->when($nicheFilter->isNotEmpty(), fn ($c) => $c->filter(
        fn (array $n) => $nicheFilter->contains(Str::lower($n['label']))
    ));
```

- [ ] **Step 5: Run ScanNichesCommand tests**

```bash
php artisan test tests/Feature/ScanNichesCommandTest.php
```

Expected: PASS (both tests).

- [ ] **Step 6: Commit**

```bash
git add config/niches.php app/Console/Commands/ScanNichesCommand.php tests/Feature/ScanNichesCommandTest.php
git commit -m "refactor: nest niches config for bootstrap command"
```

---

### Task 2: Command skeleton and Step 1 (UK cities)

**Files:**
- Create: `app/Console/Commands/NichesBootstrapCommand.php`
- Create: `tests/Feature/NichesBootstrapCommandTest.php`

- [ ] **Step 1: Write failing test — ONS cities merged with supplement**

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NichesBootstrapCommandTest extends TestCase
{
    private const ONS_URL = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/TCITY_Dec_2015_EN_BFC/FeatureServer/0/query*';

    public function test_fetches_ons_cities_and_merges_supplementary_settlements(): void
    {
        Http::fake([
            self::ONS_URL => Http::response([
                'features' => [
                    ['attributes' => ['TCITY15NM' => 'Bristol']],
                    ['attributes' => ['TCITY15NM' => 'Bristol']],
                ],
            ]),
            '*' => Http::response('<html>dentist physiotherapist atm</html>', 200),
        ]);

        config(['services.google_places.key' => null]);

        $this->artisan('niches:bootstrap', ['--dry-run' => true])
            ->expectsOutputToContain('Found')
            ->expectsOutputToContain('Edinburgh')
            ->assertExitCode(0);
    }
}
```

- [ ] **Step 2: Run test — expect FAIL (command missing)**

```bash
php artisan test --filter=test_fetches_ons_cities_and_merges_supplementary_settlements
```

- [ ] **Step 3: Create command skeleton + Step 1**

`app/Console/Commands/NichesBootstrapCommand.php` — include:

- `protected $signature = 'niches:bootstrap {--min-results=5} {--dry-run}';`
- Constants: `ONS_URL`, `TAXONOMY_URL`, `SUPPLEMENTARY_CITIES`, `FALLBACK_CITIES`, `FALLBACK_NICHES`, `TYPE_BLOCKLIST`, `TYPE_ALLOWLIST`
- Constructor: `public function __construct(private GooglePlacesService $places) { parent::__construct(); }`
- `handle()` try/catch → `$this->error()`, return `FAILURE`
- `fetchCities(): array` — HTTP get ONS, parse names, merge supplement, unique, sort; on failure warn + `FALLBACK_CITIES`
- Stub `fetchNicheCandidates(): array` returning `FALLBACK_NICHES` until Task 3
- Stub `validateNiches(array $niches): array` returning input until Task 4
- Stub `writeConfig(array $niches, array $cities): string` returning `'dry-run'` until Task 5
- Call steps in order; log `Found {n} UK settlements`

- [ ] **Step 4: Run test**

```bash
php artisan test --filter=test_fetches_ons_cities_and_merges_supplementary_settlements
```

Expected: PASS (dry-run output includes Edinburgh from supplement).

- [ ] **Step 5: Write failing test — ONS failure uses fallback**

```php
public function test_ons_failure_uses_fallback_cities(): void
{
    Http::fake([
        self::ONS_URL => Http::response('', 500),
        '*' => Http::response('<html>dentist</html>', 200),
    ]);

    config(['services.google_places.key' => null]);

    $this->artisan('niches:bootstrap', ['--dry-run' => true])
        ->expectsOutputToContain('Found 30 UK settlements')
        ->assertExitCode(0);
}
```

Adjust expected count to `count(FALLBACK_CITIES)` in assertion message.

- [ ] **Step 6: Run test, fix if needed, commit**

```bash
php artisan test tests/Feature/NichesBootstrapCommandTest.php
git add app/Console/Commands/NichesBootstrapCommand.php tests/Feature/NichesBootstrapCommandTest.php
git commit -m "feat: add niches:bootstrap command with ONS city fetch"
```

---

### Task 3: Step 2 — taxonomy extract and filter

**Files:**
- Modify: `app/Console/Commands/NichesBootstrapCommand.php`
- Modify: `tests/Feature/NichesBootstrapCommandTest.php`

- [ ] **Step 1: Write failing test — blocklist vs allowlist**

```php
public function test_extracts_and_filters_place_types_from_taxonomy_html(): void
{
    Http::fake([
        self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
        'https://developers.google.com/maps/documentation/places/web-service/place-types' => Http::response(
            '<code>dentist</code> <code>atm</code> <code>physiotherapist</code> <code>shopping_mall</code>',
            200,
        ),
    ]);

    config(['services.google_places.key' => null]);

    $this->mock(\App\Services\GooglePlacesService::class);

    $this->artisan('niches:bootstrap', ['--dry-run' => true])
        ->expectsOutputToContain('Extracted')
        ->expectsOutputToContain('primary_type')
        ->doesntExpectOutputToContain('primary_type\' => \'atm\'')
        ->assertExitCode(0);
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --filter=test_extracts_and_filters_place_types_from_taxonomy_html
```

- [ ] **Step 3: Implement `fetchNicheCandidates()`**

```php
private function fetchNicheCandidates(): array
{
    $response = Http::timeout(15)->get(self::TAXONOMY_URL);

    if ($response->failed()) {
        $this->warn('Places taxonomy fetch failed; using hardcoded niche list.');

        return self::FALLBACK_NICHES;
    }

    preg_match_all('/\b[a-z][a-z_]{3,40}\b/', $response->body(), $matches);
    $types = collect($matches[0] ?? [])->unique()->values();

    $filtered = $types
        ->filter(fn (string $type) => $this->typePassesFilter($type))
        ->map(fn (string $type) => [
            'primary_type' => $type,
            'label'        => \Illuminate\Support\Str::title(str_replace('_', ' ', $type)),
            'query'        => str_replace('_', ' ', $type),
        ])
        ->values()
        ->all();

    if ($filtered === []) {
        $this->warn('No types extracted from taxonomy; using hardcoded niche list.');

        return self::FALLBACK_NICHES;
    }

    return $filtered;
}

private function typePassesFilter(string $type): bool
{
    foreach (self::TYPE_ALLOWLIST as $signal) {
        if (str_contains($type, $signal)) {
            return true;
        }
    }

    foreach (self::TYPE_BLOCKLIST as $signal) {
        if (str_contains($type, $signal)) {
            return false;
        }
    }

    return false;
}
```

Copy full `TYPE_BLOCKLIST`, `TYPE_ALLOWLIST`, `FALLBACK_NICHES` arrays from spec verbatim.

Log: `$this->info('Extracted '.count($candidates).' candidate niches from Places taxonomy');`

- [ ] **Step 4: Write failing test — taxonomy HTTP failure**

```php
public function test_taxonomy_failure_uses_fallback_niches(): void
{
    Http::fake([
        self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
        'https://developers.google.com/maps/documentation/places/web-service/place-types' => Http::response('', 500),
    ]);

    config(['services.google_places.key' => null]);

    $this->mock(\App\Services\GooglePlacesService::class);

    $this->artisan('niches:bootstrap', ['--dry-run' => true])
        ->expectsOutputToContain('Dental Practice')
        ->assertExitCode(0);
}
```

- [ ] **Step 5: Run tests, commit**

```bash
php artisan test tests/Feature/NichesBootstrapCommandTest.php
git add app/Console/Commands/NichesBootstrapCommand.php tests/Feature/NichesBootstrapCommandTest.php
git commit -m "feat: bootstrap Places taxonomy extraction and filtering"
```

---

### Task 4: Step 3 — Places validation pass

**Files:**
- Modify: `app/Console/Commands/NichesBootstrapCommand.php`
- Modify: `tests/Feature/NichesBootstrapCommandTest.php`

- [ ] **Step 1: Write failing test — drops niches below min-results**

```php
public function test_validation_drops_niches_below_min_results(): void
{
    Http::fake([
        self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
        '*' => Http::response('<html>dentist sparse_niche_type</html>', 200),
    ]);

    config(['services.google_places.key' => 'test-key']);

    $this->mock(\App\Services\GooglePlacesService::class, function ($mock) {
        $mock->shouldReceive('searchByNicheAndCity')
            ->andReturnUsing(function (string $query) {
                return $query === 'dentist' ? array_fill(0, 10, 'places/1') : [];
            });
    });

    $path = config_path('niches.php');
    $backup = file_exists($path) ? file_get_contents($path) : null;

    try {
        $this->artisan('niches:bootstrap', ['--min-results' => 5, '--no-interaction' => true])
            ->expectsOutputToContain('Kept')
            ->assertExitCode(0);

        $written = file_get_contents($path);
        $this->assertStringContainsString("'query' => 'dentist'", $written);
        $this->assertStringNotContainsString('sparse_niche_type', $written);
    } finally {
        if ($backup !== null) {
            file_put_contents($path, $backup);
        }
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --filter=test_validation_drops_niches_below_min_results
```

- [ ] **Step 3: Implement `validateNiches(array $niches): array`**

```php
private function validateNiches(array $niches): array
{
    $key = config('services.google_places.key');

    if ($key === null || $key === '') {
        $this->warn('GOOGLE_PLACES_API_KEY is not set; skipping validation pass.');

        return ['niches' => $niches, 'apiCalls' => 0, 'dropped' => 0];
    }

    $minResults = max(1, (int) $this->option('min-results'));
    $kept = [];
    $dropped = 0;
    $apiCalls = 0;
    $zeroResultCount = 0;

    $bar = $this->output->createProgressBar(count($niches));
    $bar->start();

    foreach ($niches as $niche) {
        $apiCalls++;
        $placeIds = $this->places->searchByNicheAndCity($niche['query'], 'Birmingham', 'GB');
        $count = count($placeIds);

        if ($count === 0) {
            $zeroResultCount++;
        }

        if ($count < $minResults) {
            $dropped++;
            $this->warn("Dropped {$niche['label']}: {$count} results in Birmingham");
        } else {
            $kept[] = $niche;
        }

        $bar->advance();
    }

    $bar->finish();
    $this->newLine();

    if ($zeroResultCount === count($niches) && count($niches) > 0) {
        $this->warn('Places API may have failed — keeping unvalidated niche list.');

        return ['niches' => $niches, 'apiCalls' => $apiCalls, 'dropped' => 0];
    }

    $this->info('Kept '.count($kept)." niches after validation pass ({$dropped} dropped)");

    return ['niches' => $kept, 'apiCalls' => $apiCalls, 'dropped' => $dropped];
}
```

- [ ] **Step 4: Write test — missing API key skips validation**

```php
public function test_skips_validation_when_api_key_missing(): void
{
    Http::fake([
        self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
        '*' => Http::response('<html>dentist</html>', 200),
    ]);

    config(['services.google_places.key' => '']);

    $this->mock(\App\Services\GooglePlacesService::class, function ($mock) {
        $mock->shouldNotReceive('searchByNicheAndCity');
    });

    $this->artisan('niches:bootstrap', ['--dry-run' => true])
        ->expectsOutputToContain('skipping validation')
        ->assertExitCode(0);
}
```

- [ ] **Step 5: Run tests, commit**

```bash
php artisan test tests/Feature/NichesBootstrapCommandTest.php
git add app/Console/Commands/NichesBootstrapCommand.php tests/Feature/NichesBootstrapCommandTest.php
git commit -m "feat: bootstrap niche validation via Places API"
```

---

### Task 5: Step 4 — write config, dry-run, summary table

**Files:**
- Modify: `app/Console/Commands/NichesBootstrapCommand.php`
- Modify: `tests/Feature/NichesBootstrapCommandTest.php`

- [ ] **Step 1: Write failing test — dry-run does not write file**

```php
public function test_dry_run_does_not_write_config_file(): void
{
    Http::fake([
        self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
        '*' => Http::response('<html>dentist</html>', 200),
    ]);

    config(['services.google_places.key' => null]);

    $path = config_path('niches.php');
    $before = filemtime($path);

    $this->artisan('niches:bootstrap', ['--dry-run' => true])
        ->expectsOutputToContain("'niches'")
        ->expectsOutputToContain("'cities'")
        ->assertExitCode(0);

    $this->assertSame($before, filemtime($path));
}
```

- [ ] **Step 2: Write failing test — overwrite declined**

```php
public function test_declines_overwrite_when_operator_says_no(): void
{
    Http::fake([
        self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
        '*' => Http::response('<html>dentist</html>', 200),
    ]);

    config(['services.google_places.key' => null]);
    $this->mock(\App\Services\GooglePlacesService::class);

    $path = config_path('niches.php');
    $backup = file_get_contents($path);
    file_put_contents($path, "<?php\nreturn ['marker' => true];\n");

    try {
        $this->artisan('niches:bootstrap')
            ->expectsConfirmation('config/niches.php already exists. Overwrite?', 'no')
            ->expectsOutputToContain('skipped')
            ->assertExitCode(0);

        $this->assertStringContainsString('marker', file_get_contents($path));
    } finally {
        file_put_contents($path, $backup);
    }
}
```

- [ ] **Step 3: Implement `writeConfig()` and `renderConfigPhp()`**

```php
private function writeConfig(array $niches, array $cities): string
{
    $this->info('Niches: '.count($niches).', Cities: '.count($cities));

    $contents = $this->renderConfigPhp($niches, $cities);
    $path = config_path('niches.php');

    if ($this->option('dry-run')) {
        $this->line($contents);

        return 'dry-run';
    }

    if (file_exists($path) && ! $this->option('no-interaction')) {
        if (! $this->confirm('config/niches.php already exists. Overwrite?', false)) {
            $this->warn('Config write skipped (declined overwrite).');

            return 'skipped (declined overwrite)';
        }
    }

    file_put_contents($path, $contents, LOCK_EX);

    return $path;
}

private function renderConfigPhp(array $niches, array $cities): string
{
    $date = now()->toDateString();
    $export = var_export(['niches' => $niches, 'cities' => $cities], true);

    return <<<PHP
<?php

// Generated by niches:bootstrap on {$date}
// Edit this file manually to add, remove, or rename niches.
// Re-run niches:bootstrap only if you need to expand from scratch.

return {$export};

PHP;
}
```

Wire summary table in `handle()`:

```php
$this->table(
    ['Metric', 'Value'],
    [
        ['Niches', (string) count($niches)],
        ['Cities', (string) count($cities)],
        ['API calls used', (string) $apiCalls.' (validation)'],
        ['Config written', $writtenPath],
    ],
);
```

- [ ] **Step 4: Run all bootstrap + scan tests**

```bash
php artisan test tests/Feature/NichesBootstrapCommandTest.php tests/Feature/ScanNichesCommandTest.php
```

Expected: all PASS.

- [ ] **Step 5: Manual smoke (optional, requires API key)**

```bash
php artisan niches:bootstrap --dry-run
```

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/NichesBootstrapCommand.php tests/Feature/NichesBootstrapCommandTest.php
git commit -m "feat: write config from niches:bootstrap with dry-run and confirm"
```

---

### Task 6: Final verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: PASS (fix any tests still using flat `config('niches')` — grep `config('niches')` and update to `niches.niches`).

- [ ] **Step 2: Confirm command registered**

```bash
php artisan list | grep niches:bootstrap
```

Expected: `niches:bootstrap` listed.

- [ ] **Step 3: Commit any grep fixes**

```bash
git add -A
git commit -m "test: align niche config references with nested shape"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Signature `{--min-results=5} {--dry-run}` | Task 2–5 |
| No `--population` | N/A |
| ONS + supplement + fallback cities | Task 2 |
| Taxonomy regex + block/allow lists + fallback | Task 3 |
| Validation Birmingham + min-results + key skip + all-zero heuristic | Task 4 |
| `primary_type` in config, `query` for search | Task 1, 4 |
| Nested `config/niches.php` | Task 1, 5 |
| Overwrite confirm / `--no-interaction` / dry-run | Task 5 |
| Summary `$this->table()` | Task 5 |
| `ScanNichesCommand` consumer update | Task 1 |
| Top-level try/catch exit 1 | Task 2 |
| No DB/UI/scheduler | — |

---

## Execution handoff

Plan saved. Choose how to implement:

1. **Subagent-driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline execution** — run tasks in this session with executing-plans checkpoints

Which approach do you want?
