# Prospect Detail — Site Audit — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show operator-complete site audit data (axe summary, top violations, full violation table, Lighthouse dials, metadata) on `/prospects/{id}` when `audit_status === 'complete'`, reusing `ReportBuilderService` shaping and shared audit UI components.

**Architecture:** Add `buildOperatorAudit()` and `extractAllViolations()` to `ReportBuilderService` (refactor violation mapping to a single private path). Pass shaped `audit` prop from `ProspectController`. Extract `ViolationCard` / `LighthouseDial` from the public report page; add `SiteAuditSection` + `ViolationsTable` for the prospect detail layout below weakness flags.

**Tech Stack:** Laravel 13, Inertia.js, React, PHPUnit, Vite.

**Spec:** `docs/superpowers/specs/2026-05-27-prospect-site-audit-detail-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Services/ReportBuilderService.php` | `buildOperatorAudit`, `extractAllViolations`, shared violation mapping |
| `app/Http/Controllers/ProspectController.php` | Pass `audit` Inertia prop |
| `tests/Unit/ReportBuilderServiceTest.php` | Unit tests for new methods |
| `tests/Feature/ProspectShowTest.php` | Feature tests for `audit` prop presence/absence |
| `resources/js/Components/audit/ViolationCard.jsx` | Shared violation card |
| `resources/js/Components/audit/LighthouseDial.jsx` | Shared Lighthouse dial |
| `resources/js/Components/audit/ViolationsTable.jsx` | Full violation list table |
| `resources/js/Components/audit/SiteAuditSection.jsx` | Prospect Site audit card |
| `resources/js/Pages/Prospect/Show.jsx` | Render `SiteAuditSection` when `audit` set |
| `resources/js/Pages/Report/Public.jsx` | Import shared components |

---

### Task 1: `extractAllViolations` + refactor mapping

**Files:**
- Modify: `app/Services/ReportBuilderService.php`
- Test: `tests/Unit/ReportBuilderServiceTest.php`

- [ ] **Step 1: Write failing tests for `extractAllViolations`**

Add to `tests/Unit/ReportBuilderServiceTest.php`:

```php
public function test_extract_all_violations_sorted_by_impact(): void
{
    $payload = [
        'violations' => [
            ['id' => 'minor-rule', 'impact' => 'minor', 'description' => 'Minor', 'nodes' => [1]],
            ['id' => 'critical-rule', 'impact' => 'critical', 'description' => 'Critical', 'nodes' => [1, 2]],
            ['id' => 'serious-rule', 'impact' => 'serious', 'description' => 'Serious', 'nodes' => [1]],
        ],
    ];

    $all = $this->service->extractAllViolations($payload);

    $this->assertCount(3, $all);
    $this->assertSame('critical-rule', $all[0]['id']);
    $this->assertSame('serious-rule', $all[1]['id']);
    $this->assertSame('minor-rule', $all[2]['id']);
}

public function test_extract_all_violations_returns_empty_for_no_violations(): void
{
    $this->assertSame([], $this->service->extractAllViolations(['violations' => []]));
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=extract_all_violations
```

Expected: FAIL — method `extractAllViolations` does not exist.

- [ ] **Step 3: Refactor `ReportBuilderService`**

In `app/Services/ReportBuilderService.php`:

1. Add private method `mapViolations(array $payload): array` — move sort + map logic from `extractTopViolations` into it (no limit).
2. Implement `extractAllViolations(array $payload): array` as `return $this->mapViolations($payload);`
3. Change `extractTopViolations` to:

```php
public function extractTopViolations(array $payload, int $limit = 5): array
{
    return array_slice($this->mapViolations($payload), 0, $limit);
}
```

Keep the per-violation return shape identical to today (`id`, `impact`, `description`, `help`, `wcag`, `nodes`, `screenshot_url`, `user_impact`, `fix_hint`).

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Unit/ReportBuilderServiceTest.php
```

Expected: all PASS (including existing `test_extract_top_violations_includes_screenshot_url`).

- [ ] **Step 5: Commit (optional)**

```bash
git add app/Services/ReportBuilderService.php tests/Unit/ReportBuilderServiceTest.php
git commit -m "feat(audit): extract all violations via shared mapper"
```

---

### Task 2: `buildOperatorAudit`

**Files:**
- Modify: `app/Services/ReportBuilderService.php`
- Test: `tests/Unit/ReportBuilderServiceTest.php`

- [ ] **Step 1: Write failing tests**

Add to `tests/Unit/ReportBuilderServiceTest.php`:

```php
public function test_build_operator_audit_returns_null_when_not_complete(): void
{
    $prospect = new Prospect([
        'audit_status' => 'pending',
        'raw_a11y_payload' => ['violations' => [['id' => 'x', 'impact' => 'critical', 'nodes' => [1]]]],
        'website_url' => 'https://example.com',
    ]);

    $this->assertNull($this->service->buildOperatorAudit($prospect));
}

public function test_build_operator_audit_returns_null_when_payload_missing(): void
{
    $prospect = new Prospect([
        'audit_status' => 'complete',
        'raw_a11y_payload' => null,
        'raw_lighthouse_payload' => null,
        'website_url' => 'https://example.com',
    ]);

    $this->assertNull($this->service->buildOperatorAudit($prospect));
}

public function test_build_operator_audit_returns_full_shape_when_complete(): void
{
    $completedAt = now()->subHour();
    $prospect = Prospect::factory()->make([
        'audit_status' => 'complete',
        'website_url' => 'https://example.com',
        'performance_score' => 42,
        'raw_a11y_payload' => [
            'url' => 'https://example.com',
            'violations' => [
                ['id' => 'color-contrast', 'impact' => 'critical', 'description' => 'Contrast', 'tags' => ['wcag2aa'], 'nodes' => [1]],
            ],
            'pass_count' => 40,
            'incomplete_count' => 2,
        ],
        'raw_lighthouse_payload' => [
            'performance' => 42,
            'accessibility' => 55,
            'seo' => 70,
        ],
    ]);
    $prospect->setRelation('auditJobs', collect([
        new \App\Models\AuditJob([
            'job_type' => 'accessibility',
            'status' => 'complete',
            'completed_at' => $completedAt,
        ]),
    ]));

    $audit = $this->service->buildOperatorAudit($prospect);

    $this->assertNotNull($audit);
    $this->assertSame('https://example.com', $audit['url']);
    $this->assertSame(1, $audit['summary']['critical']);
    $this->assertSame(40, $audit['pass_count']);
    $this->assertSame(2, $audit['incomplete_count']);
    $this->assertCount(1, $audit['top_violations']);
    $this->assertCount(1, $audit['all_violations']);
    $this->assertSame(42, $audit['lighthouse']['performance']);
    $this->assertSame(42, $audit['performance_score']);
    $this->assertSame($completedAt->toIso8601String(), $audit['audited_at']);
}
```

Note: if `Prospect::factory()->make()` is awkward in unit test, use `new Prospect([...])` and `setRelation` only — drop factory if it requires DB.

- [ ] **Step 2: Run tests to verify failure**

```bash
php artisan test --filter=build_operator_audit
```

Expected: FAIL — method not defined.

- [ ] **Step 3: Implement `buildOperatorAudit`**

Add to `ReportBuilderService.php`:

```php
/**
 * Shaped site audit for operator prospect detail. Null when section should be hidden.
 *
 * @return array<string, mixed>|null
 */
public function buildOperatorAudit(Prospect $prospect): ?array
{
    if ($prospect->audit_status !== 'complete') {
        return null;
    }

    $a11yPayload = $prospect->raw_a11y_payload;
    $lighthousePayload = $prospect->raw_lighthouse_payload ?? [];

    if ($a11yPayload === null || $a11yPayload === []) {
        return null;
    }

    $lighthouse = $this->extractLighthouse($lighthousePayload, $a11yPayload);
    $hasLighthouse = $lighthouse['performance'] !== null
        || $lighthouse['accessibility'] !== null
        || $lighthouse['seo'] !== null
        || $lighthouse['best_practices'] !== null;

    if (($a11yPayload['violations'] ?? []) === [] && ! $hasLighthouse && ! isset($a11yPayload['pass_count'])) {
        return null;
    }

    $completedJob = $prospect->relationLoaded('auditJobs')
        ? $prospect->auditJobs
            ->where('job_type', 'accessibility')
            ->where('status', 'complete')
            ->sortByDesc('completed_at')
            ->first()
        : null;

    $auditedAt = $completedJob?->completed_at ?? $prospect->updated_at;

    return [
        'audited_at'        => $auditedAt?->toIso8601String() ?? now()->toIso8601String(),
        'url'               => $a11yPayload['url'] ?? $prospect->website_url ?? '',
        'summary'           => $this->summarizeViolations($a11yPayload),
        'pass_count'        => (int) ($a11yPayload['pass_count'] ?? 0),
        'incomplete_count'  => (int) ($a11yPayload['incomplete_count'] ?? 0),
        'top_violations'    => $this->extractTopViolations($a11yPayload, 5),
        'all_violations'    => $this->extractAllViolations($a11yPayload),
        'lighthouse'        => $lighthouse,
        'performance_score' => (int) $prospect->performance_score,
    ];
}
```

- [ ] **Step 4: Run unit tests**

```bash
php artisan test tests/Unit/ReportBuilderServiceTest.php
```

Expected: all PASS.

- [ ] **Step 5: Commit (optional)**

```bash
git add app/Services/ReportBuilderService.php tests/Unit/ReportBuilderServiceTest.php
git commit -m "feat(audit): build operator audit payload for prospect detail"
```

---

### Task 3: Wire `ProspectController` + feature test

**Files:**
- Modify: `app/Http/Controllers/ProspectController.php`
- Create: `tests/Feature/ProspectShowTest.php`

- [ ] **Step 1: Write failing feature test**

Create `tests/Feature/ProspectShowTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_includes_audit_when_complete_with_payload(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'complete',
            'website_url' => 'https://example.com',
            'performance_score' => 50,
            'raw_a11y_payload' => [
                'url' => 'https://example.com',
                'violations' => [
                    ['id' => 'color-contrast', 'impact' => 'critical', 'description' => 'Contrast', 'nodes' => [1]],
                ],
                'pass_count' => 10,
                'incomplete_count' => 1,
            ],
            'raw_lighthouse_payload' => ['performance' => 50, 'accessibility' => 60, 'seo' => 70],
        ]);
        AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'complete',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->has('audit')
                ->where('audit.summary.critical', 1)
                ->where('audit.pass_count', 10));
    }

    public function test_show_omits_audit_when_pending(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'pending',
            'raw_a11y_payload' => null,
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('audit', null));
    }
}
```

- [ ] **Step 2: Run feature test — expect fail**

```bash
php artisan test tests/Feature/ProspectShowTest.php
```

Expected: FAIL — `audit` missing or wrong.

- [ ] **Step 3: Update `ProspectController::show`**

```php
use App\Services\ReportBuilderService;

public function show(Prospect $prospect, ReportBuilderService $reportBuilder): Response
{
    $this->authorize('view', $prospect);

    $prospect->load([
        'search',
        'report',
        'outreachEmails' => fn ($q) => $q->latest(),
        'auditJobs',
    ]);

    return Inertia::render('Prospect/Show', [
        // ... existing prospect/search/report/outreachEmails keys unchanged ...
        'audit' => $reportBuilder->buildOperatorAudit($prospect),
    ]);
}
```

- [ ] **Step 4: Run feature test**

```bash
php artisan test tests/Feature/ProspectShowTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit (optional)**

```bash
git add app/Http/Controllers/ProspectController.php tests/Feature/ProspectShowTest.php
git commit -m "feat(prospect): pass shaped audit prop on detail page"
```

---

### Task 4: Extract shared audit components

**Files:**
- Create: `resources/js/Components/audit/ViolationCard.jsx`
- Create: `resources/js/Components/audit/LighthouseDial.jsx`
- Modify: `resources/js/Pages/Report/Public.jsx`

- [ ] **Step 1: Create `ViolationCard.jsx`**

Copy the `ViolationCard` function from `resources/js/Pages/Report/Public.jsx` (lines ~231–296) into `resources/js/Components/audit/ViolationCard.jsx`. Add imports:

```jsx
import { SevChip } from '@/Components/ui';

export default function ViolationCard({ violation: v, screenshotUrl }) {
    // ... same body; remove unused `index` param if not used
}
```

Export as default.

- [ ] **Step 2: Create `LighthouseDial.jsx`**

Copy `LighthouseDial` from `Public.jsx` into `resources/js/Components/audit/LighthouseDial.jsx` as default export.

- [ ] **Step 3: Update `Public.jsx`**

Replace local functions with:

```jsx
import ViolationCard from '@/Components/audit/ViolationCard';
import LighthouseDial from '@/Components/audit/LighthouseDial';
```

Update usage:

```jsx
<ViolationCard key={i} violation={v} screenshotUrl={v.screenshot_url} />
```

Remove the local `ViolationCard` and `LighthouseDial` function definitions.

- [ ] **Step 4: Build frontend**

```bash
npm run build
```

Expected: build succeeds with no import errors.

- [ ] **Step 5: Commit (optional)**

```bash
git add resources/js/Components/audit/ resources/js/Pages/Report/Public.jsx
git commit -m "refactor(ui): extract shared audit components"
```

---

### Task 5: `ViolationsTable` component

**Files:**
- Create: `resources/js/Components/audit/ViolationsTable.jsx`

- [ ] **Step 1: Create the component**

```jsx
import { useMemo, useState } from 'react';
import { SevChip } from '@/Components/ui';

const IMPACT_ORDER = { critical: 0, serious: 1, moderate: 2, minor: 3 };

export default function ViolationsTable({ violations = [] }) {
    const [hideModerateMinor, setHideModerateMinor] = useState(false);
    const showFilter = violations.length > 15;

    const rows = useMemo(() => {
        const sorted = [...violations].sort(
            (a, b) => (IMPACT_ORDER[a.impact] ?? 4) - (IMPACT_ORDER[b.impact] ?? 4),
        );
        if (!hideModerateMinor) {
            return sorted;
        }
        return sorted.filter((v) => v.impact === 'critical' || v.impact === 'serious');
    }, [violations, hideModerateMinor]);

    if (violations.length === 0) {
        return <p className="micro">No violations recorded.</p>;
    }

    return (
        <div>
            {showFilter && (
                <label className="micro" style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
                    <input
                        type="checkbox"
                        checked={hideModerateMinor}
                        onChange={(e) => setHideModerateMinor(e.target.checked)}
                    />
                    Hide moderate &amp; minor
                </label>
            )}
            <table className="data-table" style={{ width: '100%', fontSize: 13 }}>
                <thead>
                    <tr>
                        <th style={{ width: 100 }}>Impact</th>
                        <th style={{ width: 140 }}>Rule</th>
                        <th>Description</th>
                        <th style={{ width: 80 }}>WCAG</th>
                        <th style={{ width: 56, textAlign: 'right' }}>Nodes</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((v) => (
                        <tr key={v.id}>
                            <td><SevChip level={v.impact === 'minor' ? 'moderate' : v.impact} /></td>
                            <td className="mono" style={{ fontSize: 11 }}>{v.id}</td>
                            <td>{v.description}</td>
                            <td className="micro">{v.wcag ?? '—'}</td>
                            <td style={{ textAlign: 'right' }} className="num">{v.nodes}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
```

Adjust table class if project uses `DataTable` wrapper instead — match `resources/js/Components/ui` patterns.

- [ ] **Step 2: Build**

```bash
npm run build
```

Expected: PASS.

---

### Task 6: `SiteAuditSection` + prospect page

**Files:**
- Create: `resources/js/Components/audit/SiteAuditSection.jsx`
- Modify: `resources/js/Pages/Prospect/Show.jsx`

- [ ] **Step 1: Create `SiteAuditSection.jsx`**

```jsx
import { Card, SevChip } from '@/Components/ui';
import ViolationCard from '@/Components/audit/ViolationCard';
import LighthouseDial from '@/Components/audit/LighthouseDial';
import ViolationsTable from '@/Components/audit/ViolationsTable';

export default function SiteAuditSection({ audit }) {
    if (!audit) {
        return null;
    }

    const lh = audit.lighthouse ?? {};
    const hasLighthouse = lh.performance != null || lh.accessibility != null || lh.seo != null || lh.best_practices != null;
    const summary = audit.summary ?? {};
    const auditedLabel = audit.audited_at
        ? new Date(audit.audited_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })
        : null;
    const showScannerScore = audit.performance_score != null
        && (lh.performance == null || lh.performance !== audit.performance_score);

    return (
        <Card title="Site audit" style={{ marginBottom: 24 }}>
            <div style={{ marginBottom: 20 }}>
                {auditedLabel && <div className="micro" style={{ marginBottom: 6 }}>Audited {auditedLabel}</div>}
                {audit.url && (
                    <a href={audit.url} target="_blank" rel="noopener noreferrer" className="micro">
                        {audit.url.replace(/^https?:\/\//, '')}
                    </a>
                )}
            </div>

            <div style={{ marginBottom: 24 }}>
                <div className="eyebrow" style={{ marginBottom: 10 }}>Summary</div>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 8 }}>
                    {summary.critical > 0 && <SevChip level="critical" count={summary.critical} />}
                    {summary.serious > 0 && <SevChip level="serious" count={summary.serious} />}
                    {summary.moderate > 0 && <SevChip level="moderate" count={summary.moderate} />}
                    {summary.minor > 0 && <SevChip level="minor" count={summary.minor} />}
                    {summary.total === 0 && <span className="micro">No issues detected</span>}
                </div>
                <p className="micro">
                    {audit.pass_count} passes · {audit.incomplete_count} incomplete checks
                </p>
            </div>

            {hasLighthouse && (
                <div style={{ marginBottom: 28 }}>
                    <div className="eyebrow" style={{ marginBottom: 16 }}>Lighthouse</div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(100px, 1fr))', gap: 16 }}>
                        {lh.performance != null && <LighthouseDial label="Performance" score={lh.performance} />}
                        {lh.accessibility != null && <LighthouseDial label="Accessibility" score={lh.accessibility} />}
                        {lh.seo != null && <LighthouseDial label="SEO" score={lh.seo} />}
                        {lh.best_practices != null && <LighthouseDial label="Best practices" score={lh.best_practices} />}
                    </div>
                    {showScannerScore && (
                        <p className="micro" style={{ marginTop: 12 }}>
                            Scanner score: <span className="num">{audit.performance_score}</span>
                        </p>
                    )}
                </div>
            )}

            {(audit.top_violations ?? []).length > 0 && (
                <div style={{ marginBottom: 28 }}>
                    <div className="eyebrow" style={{ marginBottom: 16 }}>Priority issues</div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                        {audit.top_violations.map((v) => (
                            <ViolationCard key={v.id} violation={v} screenshotUrl={v.screenshot_url} />
                        ))}
                    </div>
                </div>
            )}

            <div>
                <div className="eyebrow" style={{ marginBottom: 12 }}>All violations</div>
                <ViolationsTable violations={audit.all_violations ?? []} />
            </div>
        </Card>
    );
}
```

- [ ] **Step 2: Wire into `Prospect/Show.jsx`**

Add import and render after the Weakness flags `</Card>`:

```jsx
import SiteAuditSection from '@/Components/audit/SiteAuditSection';

// In component signature:
export default function ProspectShow({ prospect, search, report, outreachEmails, audit }) {

// After weakness flags Card:
<SiteAuditSection audit={audit} />
```

- [ ] **Step 3: Build and run tests**

```bash
npm run build
php artisan test tests/Unit/ReportBuilderServiceTest.php tests/Feature/ProspectShowTest.php
```

Expected: all PASS.

- [ ] **Step 4: Manual smoke test**

1. Open `/prospects/{id}` for a prospect with `audit_status = complete` and `raw_a11y_payload` populated.
2. Confirm **Site audit** appears below weakness flags with summary, Lighthouse, priority cards, and table.
3. Open `/prospects/{id}` for a pending prospect — section absent.
4. Open `/r/{token}` — public report still renders violations and Lighthouse.

- [ ] **Step 5: Commit (optional)**

```bash
git add resources/js/Components/audit/ resources/js/Pages/Prospect/Show.jsx
git commit -m "feat(prospect): show full site audit on detail page"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Operator-complete audit depth | Task 2, 6 |
| Keep weakness flags + section below | Task 6 |
| Hide when not complete / no payload | Task 2, 3 |
| `buildOperatorAudit` + no raw JSON | Task 2, 3 |
| Shared ViolationCard / LighthouseDial | Task 4 |
| Full violations table + filter >15 | Task 5 |
| Scanner score when LH differs | Task 6 |
| Unit + feature tests | Tasks 1–3 |
| Public report unchanged behaviour | Task 4 |

---

## Verification commands

```bash
php artisan test tests/Unit/ReportBuilderServiceTest.php tests/Feature/ProspectShowTest.php
npm run build
```
