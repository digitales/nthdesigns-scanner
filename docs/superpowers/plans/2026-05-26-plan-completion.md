# Plan Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close Phases 6–7 from the prospect scanner plan: plan-faithful three-signal scoring, performance outreach/search UI, public report polish, and Horizon/env hardening.

**Architecture:** Adjust scoring in `CombineScoresService` and `A11yScoringService` first (no double-counting); extend outreach prompts and React tables; enrich `ReportBuilderService` with violation copy map and Lighthouse/GBP fields; gate Horizon via config allowlist.

**Tech Stack:** Laravel 13, PHPUnit, Inertia + React, Horizon, existing audit pipeline (no new jobs).

**Spec:** `docs/superpowers/specs/2026-05-26-plan-completion-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Services/A11yScoringService.php` | Axe-only weakness scoring (no Lighthouse perf bumps) |
| `app/Services/CombineScoresService.php` | 0.35/0.50/0.15 combine + `>70` dominant angles |
| `app/Services/OutreachEmailGeneratorService.php` | Performance secondary-line prompt instruction |
| `app/Support/AxeViolationCopy.php` | Static `user_impact` / `fix_hint` by axe rule id |
| `app/Services/ReportBuilderService.php` | Violation copy, benchmark GBP fields, `best_practices` |
| `app/Providers/HorizonServiceProvider.php` | Email allowlist gate |
| `config/scanner.php` | `horizon_allowed_emails` |
| `.env.example` | `HORIZON_ALLOWED_EMAILS` |
| `resources/js/Pages/Search/Show.jsx` | Perf column |
| `resources/js/Pages/Report/Public.jsx` | Impact/fix copy, GBP rows, 4th dial, perf footnote |
| `tests/Unit/A11yScoringServiceTest.php` | No perf-only bump |
| `tests/Unit/CombineScoresServiceTest.php` | New weights + dominant thresholds |
| `tests/Unit/OutreachEmailGeneratorServiceTest.php` | Performance prompt instruction |
| `tests/Unit/ReportBuilderServiceTest.php` | Copy fields, best_practices, benchmark GBP |

---

### Task 1: Remove Lighthouse performance from a11y scoring

**Files:**
- Modify: `app/Services/A11yScoringService.php`
- Modify: `tests/Unit/A11yScoringServiceTest.php`

- [ ] **Step 1: Replace failing test**

In `tests/Unit/A11yScoringServiceTest.php`, replace `test_low_performance_adds_points` with:

```php
public function test_low_lighthouse_performance_alone_does_not_add_score(): void
{
    $payload = [
        'violations' => [],
        'lighthouse' => ['performance' => 40],
    ];

    $result = $this->service->score($payload);

    $this->assertSame(0, $result['score']);
    $this->assertNotContains('Performance score below 50', $result['flags']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_low_lighthouse_performance_alone_does_not_add_score`

Expected: FAIL (old code still adds flags)

- [ ] **Step 3: Remove performance block from scorer**

In `app/Services/A11yScoringService.php`, delete lines that check `$performance < 50` / `< 70` and add score/flags. **Keep** the `$lhA11y < 70` block unchanged.

- [ ] **Step 4: Run a11y tests**

Run: `php artisan test tests/Unit/A11yScoringServiceTest.php`

Expected: PASS (all 4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/A11yScoringService.php tests/Unit/A11yScoringServiceTest.php
git commit -m "fix: stop double-counting Lighthouse performance in a11y score"
```

---

### Task 2: Plan-faithful combined scoring

**Files:**
- Modify: `app/Services/CombineScoresService.php`
- Modify: `tests/Unit/CombineScoresServiceTest.php`

- [ ] **Step 1: Replace combined tests**

Replace entire `tests/Unit/CombineScoresServiceTest.php` with:

```php
<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Services\CombineScoresService;
use PHPUnit\Framework\TestCase;

class CombineScoresServiceTest extends TestCase
{
    private CombineScoresService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CombineScoresService();
    }

    public function test_gbp_only_uses_gbp_score(): void
    {
        $prospect = new Prospect(['gbp_score' => 80, 'a11y_score' => 20, 'performance_score' => 25]);

        $result = $this->service->combine($prospect, 'gbp_only');

        $this->assertSame(80, $result['combined_score']);
        $this->assertSame('gbp', $result['dominant_angle']);
    }

    public function test_accessibility_only_uses_a11y_score(): void
    {
        $prospect = new Prospect(['gbp_score' => 80, 'a11y_score' => 45]);

        $result = $this->service->combine($prospect, 'accessibility_only');

        $this->assertSame(45, $result['combined_score']);
        $this->assertSame('accessibility', $result['dominant_angle']);
    }

    public function test_combined_uses_weighted_formula_with_performance_weakness(): void
    {
        // gbp=80, a11y=40, perf=25 -> weakness=75
        // round(28 + 20 + 11.25) = 59
        $prospect = new Prospect([
            'gbp_score' => 80,
            'a11y_score' => 40,
            'performance_score' => 25,
        ]);

        $result = $this->service->combine($prospect, 'combined');

        $this->assertSame(59, $result['combined_score']);
        $this->assertSame('gbp', $result['dominant_angle']);
    }

    public function test_combined_dominant_accessibility_when_a11y_above_70(): void
    {
        $prospect = new Prospect([
            'gbp_score' => 50,
            'a11y_score' => 75,
            'performance_score' => 80,
        ]);

        $result = $this->service->combine($prospect, 'combined');

        $this->assertSame('accessibility', $result['dominant_angle']);
    }

    public function test_combined_dominant_both_when_neither_above_70(): void
    {
        $prospect = new Prospect([
            'gbp_score' => 50,
            'a11y_score' => 55,
            'performance_score' => 0,
        ]);

        $result = $this->service->combine($prospect, 'combined');

        $this->assertSame('both', $result['dominant_angle']);
    }

    public function test_performance_weakness_is_zero_when_no_lighthouse_score(): void
    {
        $prospect = new Prospect([
            'gbp_score' => 40,
            'a11y_score' => 40,
            'performance_score' => 0,
        ]);

        $result = $this->service->combine($prospect, 'combined');

        // round(14 + 20 + 0) = 34
        $this->assertSame(34, $result['combined_score']);
    }
}
```

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test tests/Unit/CombineScoresServiceTest.php`

Expected: FAIL on weighted / dominant assertions

- [ ] **Step 3: Implement CombineScoresService**

Replace `combineBoth` in `app/Services/CombineScoresService.php`:

```php
private function combineBoth(int $gbp, int $a11y, int $performanceScore): array
{
    $perfWeakness = $this->performanceWeakness($performanceScore);

    $combined = (int) round(
        ($gbp * 0.35) + ($a11y * 0.50) + ($perfWeakness * 0.15)
    );

    $dominant = 'both';
    if ($a11y > 70) {
        $dominant = 'accessibility';
    } elseif ($gbp > 70) {
        $dominant = 'gbp';
    }

    return [
        'combined_score' => $combined,
        'dominant_angle' => $dominant,
    ];
}

public function performanceWeakness(int $performanceScore): int
{
    return $performanceScore > 0 ? 100 - $performanceScore : 0;
}
```

Update `combine()` `combined` branch to call:

```php
'combined' => $this->combineBoth($gbp, $a11y, (int) $prospect->performance_score),
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Unit/CombineScoresServiceTest.php`

Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/CombineScoresService.php tests/Unit/CombineScoresServiceTest.php
git commit -m "feat: three-signal combined scoring with plan weights"
```

---

### Task 3: Outreach performance prompt instruction

**Files:**
- Modify: `app/Services/OutreachEmailGeneratorService.php`
- Create: `tests/Unit/OutreachEmailGeneratorServiceTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/OutreachEmailGeneratorServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\Search;
use App\Services\OutreachEmailGeneratorService;
use PHPUnit\Framework\TestCase;

class OutreachEmailGeneratorServiceTest extends TestCase
{
    public function test_performance_instruction_when_score_below_30(): void
    {
        $search = new Search(['niche' => 'dental', 'city' => 'Leeds', 'country' => 'GB']);
        $prospect = new Prospect([
            'business_name' => 'Acme',
            'performance_score' => 25,
            'combined_score' => 60,
            'gbp_flags' => [],
            'a11y_flags' => [],
        ]);
        $prospect->setRelation('search', $search);

        $service = new OutreachEmailGeneratorService(
            new \App\Services\AnthropicService()
        );

        $line = $service->performancePromptInstruction($prospect);

        $this->assertNotNull($line);
        $this->assertStringContainsString('25/100', $line);
        $this->assertStringContainsString('secondary sentence', $line);
    }

    public function test_no_performance_instruction_when_score_high(): void
    {
        $search = new Search(['niche' => 'dental', 'city' => 'Leeds', 'country' => 'GB']);
        $prospect = new Prospect([
            'business_name' => 'Acme',
            'performance_score' => 80,
            'combined_score' => 60,
            'gbp_flags' => [],
            'a11y_flags' => [],
        ]);
        $prospect->setRelation('search', $search);

        $service = new OutreachEmailGeneratorService(
            new \App\Services\AnthropicService()
        );

        $this->assertNull($service->performancePromptInstruction($prospect));
    }
}
```

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test tests/Unit/OutreachEmailGeneratorServiceTest.php`

Expected: FAIL — method `performancePromptInstruction` not found

- [ ] **Step 3: Add public method and wire into prompt**

In `app/Services/OutreachEmailGeneratorService.php`:

```php
public function performancePromptInstruction(Prospect $prospect): ?string
{
    $score = (int) $prospect->performance_score;

    if ($score <= 0 || $score >= 30) {
        return null;
    }

    return "Add exactly one secondary sentence (not the opening) noting their site scored {$score}/100 on Google's performance benchmark and that slow load times affect rankings and bounce rate.";
}
```

At end of `buildUserPrompt()`, before `return implode`:

```php
if ($instruction = $this->performancePromptInstruction($prospect)) {
    $lines[] = $instruction;
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Unit/OutreachEmailGeneratorServiceTest.php`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/OutreachEmailGeneratorService.php tests/Unit/OutreachEmailGeneratorServiceTest.php
git commit -m "feat: outreach prompt includes performance line when Lighthouse below 30"
```

---

### Task 4: Search results Perf column

**Files:**
- Modify: `resources/js/Pages/Search/Show.jsx`

- [ ] **Step 1: Add Perf column header**

In `ProspectTable`, after the A11y `<th>`, add:

```jsx
<th className="text-center px-4 py-3 font-medium text-gray-600">Perf</th>
```

(inside the `{showA11y && ( <> ...` block, after A11y column)

- [ ] **Step 2: Add Perf cell and helper**

After a11y score `<td>`, add:

```jsx
<td className="px-4 py-3 text-center">
    <PerfScore value={p.performance_score} auditStatus={p.audit_status} />
</td>
```

Add below `ScoreBadge`:

```jsx
function PerfScore({ value, auditStatus }) {
    if (!value || value === 0) {
        return <span className="text-xs text-gray-300">—</span>;
    }
    const colour =
        value < 50 ? 'text-red-700 font-medium' :
        value < 70 ? 'text-amber-700 font-medium' :
                     'text-green-700 font-medium';
    return <span className={`text-sm tabular-nums ${colour}`}>{value}</span>;
}
```

- [ ] **Step 3: Manual check**

Run: `npm run build` (or `npm run dev`) and open a completed combined search.

Expected: Perf column visible with colour-coded Lighthouse scores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Search/Show.jsx
git commit -m "feat: show Lighthouse performance column on search results"
```

---

### Task 5: Violation copy map and ReportBuilder enrichment

**Files:**
- Create: `app/Support/AxeViolationCopy.php`
- Modify: `app/Services/ReportBuilderService.php`
- Modify: `tests/Unit/ReportBuilderServiceTest.php`

- [ ] **Step 1: Create AxeViolationCopy**

Create `app/Support/AxeViolationCopy.php`:

```php
<?php

namespace App\Support;

class AxeViolationCopy
{
    /** @var array<string, array{user_impact: string, fix_hint: string}> */
    private const MAP = [
        'color-contrast' => [
            'user_impact' => 'Text and buttons may be hard to read for people with low vision or in bright sunlight.',
            'fix_hint' => 'Increase contrast between text and background to meet WCAG AA (4.5:1 for normal text).',
        ],
        'image-alt' => [
            'user_impact' => 'Screen reader users miss information carried by images.',
            'fix_hint' => 'Add descriptive alt text to every meaningful image.',
        ],
        'label' => [
            'user_impact' => 'Form fields are unclear for screen reader and voice-control users.',
            'fix_hint' => 'Associate a visible <label> with each input using for/id.',
        ],
        'link-name' => [
            'user_impact' => 'Links announced as “click here” give no context out of context.',
            'fix_hint' => 'Use link text that describes the destination or action.',
        ],
        'button-name' => [
            'user_impact' => 'Icon-only buttons are unusable for many assistive technology users.',
            'fix_hint' => 'Provide visible text or an accessible name (aria-label) for each button.',
        ],
        'html-has-lang' => [
            'user_impact' => 'Screen readers may use the wrong language and pronunciation.',
            'fix_hint' => 'Set lang on the <html> element (e.g. lang="en-GB").',
        ],
        'document-title' => [
            'user_impact' => 'Users cannot identify the page when switching tabs or using assistive tech.',
            'fix_hint' => 'Add a unique, descriptive <title> on every page.',
        ],
        'heading-order' => [
            'user_impact' => 'Skipping heading levels confuses document structure for screen reader users.',
            'fix_hint' => 'Use headings in order (h1 → h2 → h3) without skipping levels.',
        ],
        'bypass' => [
            'user_impact' => 'Keyboard users must tab through repetitive navigation on every page.',
            'fix_hint' => 'Add a “skip to main content” link at the top of the page.',
        ],
        'meta-viewport' => [
            'user_impact' => 'Users who zoom on mobile may be unable to read content.',
            'fix_hint' => 'Allow zoom in the viewport meta tag (avoid user-scalable=no).',
        ],
    ];

  private const FALLBACK = [
        'user_impact' => 'This issue creates barriers for people using assistive technology or keyboard-only navigation.',
        'fix_hint' => 'Remediate according to the referenced WCAG criterion or ask a specialist for a targeted fix.',
    ];

    /**
     * @return array{user_impact: string, fix_hint: string}
     */
    public static function forRule(string $ruleId): array
    {
        return self::MAP[$ruleId] ?? self::FALLBACK;
    }
}
```

- [ ] **Step 2: Add failing report tests**

Add to `tests/Unit/ReportBuilderServiceTest.php`:

```php
public function test_top_violations_include_user_impact_and_fix_hint(): void
{
    $search = new Search(['niche' => 'test', 'city' => 'Leeds', 'country' => 'GB', 'scan_type' => 'combined']);
    $prospect = new Prospect([
        'business_name' => 'Acme',
        'combined_score' => 80,
        'raw_a11y_payload' => [
            'violations' => [
                [
                    'id' => 'color-contrast',
                    'impact' => 'critical',
                    'description' => 'Contrast fail',
                    'nodes' => [1],
                ],
            ],
        ],
    ]);
    $prospect->setRelation('search', $search);

    $report = $this->service->build($prospect, null);

    $this->assertArrayHasKey('user_impact', $report['top_violations'][0]);
    $this->assertArrayHasKey('fix_hint', $report['top_violations'][0]);
    $this->assertStringContainsString('contrast', strtolower($report['top_violations'][0]['fix_hint']));
}

public function test_lighthouse_includes_best_practices(): void
{
    $search = new Search(['niche' => 'test', 'city' => 'Leeds', 'country' => 'GB', 'scan_type' => 'combined']);
    $prospect = new Prospect([
        'business_name' => 'Acme',
        'combined_score' => 50,
        'raw_lighthouse_payload' => [
            'performance' => 50,
            'best_practices' => 88,
        ],
    ]);
    $prospect->setRelation('search', $search);

    $report = $this->service->build($prospect, null);

    $this->assertSame(88, $report['lighthouse']['best_practices']);
}

public function test_benchmark_includes_description_and_hours(): void
{
    $search = new Search(['niche' => 'dental', 'city' => 'Birmingham', 'country' => 'GB', 'scan_type' => 'combined']);
    $prospect = new Prospect([
        'business_name' => 'Test Dental',
        'has_description' => false,
        'hours_complete' => true,
        'review_count' => 5,
        'photo_count' => 1,
        'combined_score' => 70,
    ]);
    $prospect->setRelation('search', $search);

    $benchmark = [
        'id' => 'places/1',
        'displayName' => ['text' => 'Top Dental'],
        'rating' => 4.9,
        'userRatingCount' => 100,
        'photos' => array_fill(0, 10, []),
        'editorialSummary' => ['text' => 'A great practice'],
        'regularOpeningHours' => ['periods' => [['open' => '0900']]],
    ];

    $report = $this->service->build($prospect, $benchmark);

    $this->assertFalse($report['prospect']['has_description']);
    $this->assertTrue($report['prospect']['hours_complete']);
    $this->assertTrue($report['benchmark']['has_description']);
    $this->assertTrue($report['benchmark']['hours_complete']);
}
```

- [ ] **Step 3: Run tests to verify failure**

Run: `php artisan test --filter=test_top_violations_include_user_impact`

Expected: FAIL

- [ ] **Step 4: Update ReportBuilderService**

In `extractTopViolations` map callback, after `$id = ...`:

```php
$copy = \App\Support\AxeViolationCopy::forRule($id);
```

Add to return array:

```php
'user_impact' => $copy['user_impact'],
'fix_hint'    => $copy['fix_hint'],
```

In `build()`, extend `$benchmark` array:

```php
'has_description' => !empty($benchmarkPlace['editorialSummary']['text'] ?? null),
'hours_complete'  => !empty($benchmarkPlace['regularOpeningHours']['periods'] ?? null),
```

In `extractLighthouse()`, add to return:

```php
'best_practices' => isset($lh['best_practices']) ? (int) $lh['best_practices'] : null,
```

Update PHPDoc return type to include `best_practices`.

- [ ] **Step 5: Run report tests**

Run: `php artisan test tests/Unit/ReportBuilderServiceTest.php`

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Support/AxeViolationCopy.php app/Services/ReportBuilderService.php tests/Unit/ReportBuilderServiceTest.php
git commit -m "feat: enrich public report data with violation copy and GBP metrics"
```

---

### Task 6: Public report UI polish

**Files:**
- Modify: `resources/js/Pages/Report/Public.jsx`

- [ ] **Step 1: Violation impact and fix lines**

Inside each violation `<li>`, after `help` paragraph:

```jsx
{v.user_impact && (
    <p className="text-sm text-gray-600 mt-2">{v.user_impact}</p>
)}
{v.fix_hint && (
    <p className="text-sm text-indigo-700 mt-1"><span className="font-medium">Fix:</span> {v.fix_hint}</p>
)}
```

- [ ] **Step 2: GBP Description and Hours rows**

In both prospect and benchmark `<dl>` blocks, after Rating row:

```jsx
<MetricRow label="Description" value={p.has_description ? 'Yes' : 'No'} />
<MetricRow label="Hours" value={p.hours_complete ? 'Complete' : 'Incomplete'} />
```

Use `benchmark.has_description` / `benchmark.hours_complete` in competitor column (with `?? false` fallback).

- [ ] **Step 3: Best practices dial and performance footnote**

In Lighthouse grid, add after SEO dial:

```jsx
{lighthouse.best_practices != null && (
    <LighthouseDial label="Best practices" score={lighthouse.best_practices} />
)}
```

Change grid to `grid-cols-2 sm:grid-cols-4` when four metrics may show.

After `</div>` closing the dials grid, before section end:

```jsx
{lighthouse.performance != null && lighthouse.performance < 30 && (
    <p className="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mt-4">
        Slow load times affect search rankings and increase bounce rate — visitors often leave before the page finishes loading.
    </p>
)}
```

- [ ] **Step 4: Build frontend**

Run: `npm run build`

Expected: SUCCESS

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Report/Public.jsx
git commit -m "feat: public report shows violation fixes, GBP details, and perf footnote"
```

---

### Task 7: Horizon allowlist and env docs

**Files:**
- Modify: `config/scanner.php`
- Modify: `app/Providers/HorizonServiceProvider.php`
- Modify: `.env.example`

- [ ] **Step 1: Add config key**

In `config/scanner.php`, before closing `];`:

```php
'horizon_allowed_emails' => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('HORIZON_ALLOWED_EMAILS', ''))
))),
```

- [ ] **Step 2: Fix HorizonServiceProvider**

Remove the `Horizon::auth(function ($request) { return auth()->check(); });` block from `boot()`.

Replace `gate()` body with:

```php
Gate::define('viewHorizon', function ($user = null) {
    if (!$user) {
        return false;
    }

    if (app()->environment('local')) {
        return true;
    }

    $allowed = config('scanner.horizon_allowed_emails', []);

    return !empty($allowed) && in_array($user->email, $allowed, true);
});
```

- [ ] **Step 3: Update .env.example**

After `HORIZON_PREFIX`, add:

```env
# Comma-separated emails allowed to view /horizon in non-local environments
HORIZON_ALLOWED_EMAILS=
```

- [ ] **Step 4: Commit**

```bash
git add config/scanner.php app/Providers/HorizonServiceProvider.php .env.example
git commit -m "feat: restrict Horizon to allowlisted emails in production"
```

---

### Task 8: Phase 6 verification and full test run

**Files:**
- Verify: `app/Console/Commands/PurgeExpiredProspectData.php`
- Verify: `routes/console.php`
- Verify: uncommitted Phase 6 files from working tree

- [ ] **Step 1: Confirm purge scheduled**

`routes/console.php` must contain:

```php
Schedule::command('scanner:purge-expired')->daily();
```

- [ ] **Step 2: Run full test suite**

Run: `php artisan test`

Expected: All tests PASS

- [ ] **Step 3: Stage any remaining Phase 6 files**

If `git status` shows uncommitted Phase 6 work (Settings, Purge command, rate limit tests, etc.), commit in logical groups:

```bash
git add app/Console/Commands/PurgeExpiredProspectData.php routes/console.php tests/Feature/PurgeExpiredProspectDataTest.php
git commit -m "feat: scheduled purge of expired prospect payloads and reports"
```

(Repeat for Settings, Search rate limit, ScreenshotStorage, etc. — only files not already committed.)

- [ ] **Step 4: Update spec status**

In `docs/superpowers/specs/2026-05-26-plan-completion-design.md`, change status line to:

`**Status:** Implemented`

- [ ] **Step 5: Final commit if spec updated**

```bash
git add docs/superpowers/specs/2026-05-26-plan-completion-design.md
git commit -m "docs: mark plan completion spec as implemented"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Remove a11y perf double-count | Task 1 |
| 0.35/0.50/0.15 combine | Task 2 |
| `>70` dominant_angle | Task 2 |
| Outreach perf line `<30` | Task 3 |
| Search Perf column | Task 4 |
| Violation user_impact / fix_hint | Task 5–6 |
| Benchmark description/hours | Task 5–6 |
| best_practices dial | Task 5–6 |
| Perf footnote on public report | Task 6 |
| Horizon allowlist | Task 7 |
| Purge schedule + full tests | Task 8 |

---

## Success verification

```bash
php artisan test
npm run build
```

Manual smoke (optional):

1. Run a combined search → confirm Perf column and new combined scores after audit completes.
2. Add prospect with `performance_score` 25 to outreach → generate → inspect stored prompt via logs or temporary `dd()` — instruction present.
3. Open `/r/{token}` → violation Fix line, GBP Description/Hours, four dials when data exists.
4. In `staging`/`production` env with `HORIZON_ALLOWED_EMAILS` set — only listed email can open `/horizon`.
