# Prospect Detail — Page Speed Score + Breakdown — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show Lighthouse-native page speed on `/prospects/{id}` (score card + Core Web Vitals + top opportunities), capturing metrics/opportunities in `audit.js` and shaping them via `ReportBuilderService::buildOperatorPageSpeed()`.

**Architecture:** Extract pure Lighthouse parsing into `scripts/lighthouse-detail.js` (unit-tested with Node). Extend `runLighthouse()` in `audit.js` to persist category scores, `metrics`, and `opportunities` in `raw_lighthouse_payload`. Add `buildOperatorPageSpeed()` on the PHP side; pass `pageSpeed` Inertia prop from `ProspectController`. New `PageSpeedSection` React component sits between Weakness flags and Site audit; score card row gains a fourth **Page speed** card using existing `ScoreCard` `healthScore` prop.

**Tech Stack:** Laravel 13, Inertia.js, React, PHPUnit, Node.js (audit scripts), Lighthouse CLI, Vite.

**Spec:** `docs/superpowers/specs/2026-05-29-prospect-page-speed-detail-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `scripts/lighthouse-detail.js` | Pure helpers: `ratingFromScore`, `extractMetrics`, `extractOpportunities`, `buildLighthousePayload` |
| `scripts/lighthouse-detail.test.js` | Node unit tests against fixture JSON |
| `scripts/audit.js` | Import helpers; extend `runLighthouse()` return shape |
| `scripts/fixtures/lighthouse-report.json` | Minimal Lighthouse JSON fixture for tests |
| `app/Services/ReportBuilderService.php` | `buildOperatorPageSpeed()`, private shaping helpers |
| `app/Http/Controllers/ProspectController.php` | Pass `pageSpeed` Inertia prop |
| `tests/Unit/ReportBuilderServiceTest.php` | Unit tests for `buildOperatorPageSpeed` |
| `tests/Feature/ProspectShowTest.php` | Feature tests for `pageSpeed` prop |
| `resources/js/Components/audit/PageSpeedSection.jsx` | CWV row + opportunities list |
| `resources/js/Pages/Prospect/Show.jsx` | Page speed score card, legacy hint, render section |
| `resources/js/Components/ui/healthScore.js` | Shared health color helper (optional, used by Search + ScoreCard) |

---

### Task 1: Lighthouse detail extraction module

**Files:**
- Create: `scripts/lighthouse-detail.js`
- Create: `scripts/fixtures/lighthouse-report.json`
- Create: `scripts/lighthouse-detail.test.js`
- Modify: `scripts/package.json`

- [ ] **Step 1: Add test script to `scripts/package.json`**

```json
"scripts": {
    "audit": "node audit.js",
    "test": "node --test lighthouse-detail.test.js"
}
```

- [ ] **Step 2: Create fixture `scripts/fixtures/lighthouse-report.json`**

```json
{
  "categories": {
    "performance": { "score": 0.28 },
    "accessibility": { "score": 0.6 },
    "seo": { "score": 0.7 }
  },
  "audits": {
    "largest-contentful-paint": {
      "score": 0.2,
      "displayValue": "3.2 s",
      "numericValue": 3200
    },
    "total-blocking-time": {
      "score": 0.9,
      "displayValue": "180 ms",
      "numericValue": 180
    },
    "cumulative-layout-shift": {
      "score": 0.75,
      "displayValue": "0.14",
      "numericValue": 0.14
    },
    "first-contentful-paint": {
      "score": 0.6,
      "displayValue": "1.8 s",
      "numericValue": 1800
    },
    "unused-javascript": {
      "id": "unused-javascript",
      "score": 0,
      "title": "Reduce unused JavaScript",
      "description": "Remove unused JavaScript to reduce bytes consumed by network activity.",
      "scoreDisplayMode": "metricSavings",
      "displayValue": "Est. savings 1.2 s",
      "details": {
        "type": "opportunity",
        "overallSavingsMs": 1200
      }
    },
    "render-blocking-resources": {
      "id": "render-blocking-resources",
      "score": 0.5,
      "title": "Eliminate render-blocking resources",
      "description": "Resources are blocking the first paint of your page.",
      "scoreDisplayMode": "metricSavings",
      "displayValue": "Est. savings 450 ms",
      "details": {
        "type": "opportunity",
        "overallSavingsMs": 450
      }
    },
    "uses-optimized-images": {
      "id": "uses-optimized-images",
      "score": 1,
      "title": "Efficiently encode images",
      "description": "Optimized images load faster.",
      "scoreDisplayMode": "metricSavings",
      "details": { "type": "opportunity", "overallSavingsMs": 0 }
    }
  }
}
```

- [ ] **Step 3: Write failing tests in `scripts/lighthouse-detail.test.js`**

```javascript
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    ratingFromScore,
    extractMetrics,
    extractOpportunities,
    buildLighthousePayload,
} from './lighthouse-detail.js';

const fixture = JSON.parse(
    readFileSync(join(dirname(fileURLToPath(import.meta.url)), 'fixtures/lighthouse-report.json'), 'utf8'),
);

test('ratingFromScore maps Lighthouse bands', () => {
    assert.equal(ratingFromScore(0.95), 'good');
    assert.equal(ratingFromScore(0.7), 'needs_improvement');
    assert.equal(ratingFromScore(0.2), 'poor');
    assert.equal(ratingFromScore(null), null);
});

test('extractMetrics returns CWV shape', () => {
    const metrics = extractMetrics(fixture.audits);
    assert.equal(metrics.lcp.display, '3.2 s');
    assert.equal(metrics.lcp.rating, 'poor');
    assert.equal(metrics.inp.display, '180 ms');
    assert.equal(metrics.cls.display, '0.14');
    assert.equal(metrics.fcp.display, '1.8 s');
});

test('extractOpportunities returns failing audits sorted and capped', () => {
    const opps = extractOpportunities(fixture.audits, 8);
    assert.equal(opps.length, 2);
    assert.equal(opps[0].id, 'unused-javascript');
    assert.equal(opps[0].savings_ms, 1200);
    assert.equal(opps[1].id, 'render-blocking-resources');
});

test('buildLighthousePayload merges categories metrics opportunities', () => {
    const payload = buildLighthousePayload(fixture);
    assert.equal(payload.performance, 28);
    assert.equal(payload.accessibility, 60);
    assert.equal(payload.seo, 70);
    assert.ok(payload.metrics.lcp);
    assert.equal(payload.opportunities.length, 2);
});
```

- [ ] **Step 4: Run tests to verify they fail**

```bash
cd scripts && npm test
```

Expected: FAIL — cannot find module `./lighthouse-detail.js`.

- [ ] **Step 5: Implement `scripts/lighthouse-detail.js`**

```javascript
const METRIC_AUDITS = {
    lcp: 'largest-contentful-paint',
    inp: ['interaction-to-next-paint', 'total-blocking-time'],
    cls: 'cumulative-layout-shift',
    fcp: 'first-contentful-paint',
};

export function ratingFromScore(score) {
    if (score == null) return null;
    if (score >= 0.9) return 'good';
    if (score >= 0.5) return 'needs_improvement';
    return 'poor';
}

function firstAudit(audits, ids) {
    const keys = Array.isArray(ids) ? ids : [ids];
    for (const id of keys) {
        if (audits[id]) return audits[id];
    }
    return null;
}

function shapeMetric(audit) {
    if (!audit) return null;
    return {
        value_ms: audit.numericValue ?? null,
        display: audit.displayValue ?? String(audit.numericValue ?? ''),
        rating: ratingFromScore(audit.score),
    };
}

export function extractMetrics(audits = {}) {
    return {
        lcp: shapeMetric(audits[METRIC_AUDITS.lcp]),
        inp: shapeMetric(firstAudit(audits, METRIC_AUDITS.inp)),
        cls: shapeMetric(audits[METRIC_AUDITS.cls]),
        fcp: shapeMetric(audits[METRIC_AUDITS.fcp]),
    };
}

export function extractOpportunities(audits = {}, limit = 8) {
    const candidates = Object.values(audits)
        .filter((audit) => {
            if (audit.score == null || audit.score >= 0.9) return false;
            const type = audit.details?.type;
            const mode = audit.scoreDisplayMode;
            return type === 'opportunity' || mode === 'metricSavings';
        })
        .map((audit) => ({
            id: audit.id ?? audit.title,
            title: audit.title ?? audit.id,
            description: audit.description ?? '',
            savings_ms: audit.details?.overallSavingsMs ?? 0,
            savings_display: audit.displayValue ?? '',
        }))
        .sort((a, b) => {
            if (b.savings_ms !== a.savings_ms) return b.savings_ms - a.savings_ms;
            return a.title.localeCompare(b.title);
        });

    return candidates.slice(0, limit);
}

export function buildLighthousePayload(report) {
    const categories = report.categories ?? {};
    const audits = report.audits ?? {};

    return {
        performance: Math.round((categories.performance?.score ?? 0) * 100),
        accessibility: Math.round((categories.accessibility?.score ?? 0) * 100),
        seo: Math.round((categories.seo?.score ?? 0) * 100),
        metrics: extractMetrics(audits),
        opportunities: extractOpportunities(audits, 8),
    };
}
```

- [ ] **Step 6: Run tests**

```bash
cd scripts && npm test
```

Expected: all PASS.

- [ ] **Step 7: Commit (optional)**

```bash
git add scripts/lighthouse-detail.js scripts/lighthouse-detail.test.js scripts/fixtures/lighthouse-report.json scripts/package.json
git commit -m "feat(audit): extract Lighthouse metrics and opportunities"
```

---

### Task 2: Wire `audit.js` to persist extended payload

**Files:**
- Modify: `scripts/audit.js`

- [ ] **Step 1: Import and use `buildLighthousePayload`**

At top of `scripts/audit.js`:

```javascript
import { buildLighthousePayload } from './lighthouse-detail.js';
```

Replace the return block inside `runLighthouse()` (after `JSON.parse(output)`) with:

```javascript
const report = JSON.parse(output);
return buildLighthousePayload(report);
```

Remove the old inline `{ performance, accessibility, seo }` object.

- [ ] **Step 2: Smoke-check locally (optional)**

If Lighthouse is installed locally:

```bash
cd scripts && node audit.js https://example.com /tmp/audit-test
```

Expected: stdout JSON includes `metrics` and `opportunities` keys (opportunities may be empty for example.com).

- [ ] **Step 3: Commit (optional)**

```bash
git add scripts/audit.js
git commit -m "feat(audit): persist Lighthouse metrics and opportunities in payload"
```

---

### Task 3: `buildOperatorPageSpeed` in ReportBuilderService

**Files:**
- Modify: `app/Services/ReportBuilderService.php`
- Test: `tests/Unit/ReportBuilderServiceTest.php`

- [ ] **Step 1: Write failing tests**

Add to `tests/Unit/ReportBuilderServiceTest.php`:

```php
public function test_build_operator_page_speed_returns_null_when_not_complete(): void
{
    $prospect = new Prospect([
        'audit_status' => 'pending',
        'performance_score' => 50,
        'raw_lighthouse_payload' => ['performance' => 50, 'metrics' => []],
    ]);
    $prospect->setRelation('search', new Search(['scan_type' => 'combined']));

    $this->assertNull($this->service->buildOperatorPageSpeed($prospect));
}

public function test_build_operator_page_speed_returns_null_for_gbp_only(): void
{
    $prospect = new Prospect([
        'audit_status' => 'complete',
        'performance_score' => 50,
        'raw_lighthouse_payload' => [
            'performance' => 50,
            'metrics' => ['lcp' => ['display' => '3.2 s', 'rating' => 'poor']],
            'opportunities' => [],
        ],
    ]);
    $prospect->setRelation('search', new Search(['scan_type' => 'gbp_only']));

    $this->assertNull($this->service->buildOperatorPageSpeed($prospect));
}

public function test_build_operator_page_speed_returns_null_for_legacy_score_only_payload(): void
{
    $prospect = new Prospect([
        'audit_status' => 'complete',
        'performance_score' => 28,
        'website_url' => 'https://example.com',
        'raw_lighthouse_payload' => ['performance' => 28, 'accessibility' => 60, 'seo' => 70],
        'raw_a11y_payload' => ['url' => 'https://example.com'],
    ]);
    $prospect->setRelation('search', new Search(['scan_type' => 'combined']));

    $this->assertNull($this->service->buildOperatorPageSpeed($prospect));
}

public function test_build_operator_page_speed_returns_full_shape_with_highlights(): void
{
    $completedAt = now()->subHour();
    $prospect = new Prospect([
        'audit_status' => 'complete',
        'website_url' => 'https://example.com',
        'performance_score' => 28,
        'raw_a11y_payload' => ['url' => 'https://example.com'],
        'raw_lighthouse_payload' => [
            'performance' => 28,
            'metrics' => [
                'lcp' => ['display' => '3.2 s', 'rating' => 'poor'],
                'inp' => ['display' => '180 ms', 'rating' => 'good'],
                'cls' => ['display' => '0.14', 'rating' => 'needs_improvement'],
                'fcp' => ['display' => '1.8 s', 'rating' => 'needs_improvement'],
            ],
            'opportunities' => [
                [
                    'id' => 'unused-javascript',
                    'title' => 'Reduce unused JavaScript',
                    'description' => 'Remove unused JavaScript.',
                    'savings_ms' => 1200,
                    'savings_display' => 'Est. savings 1.2 s',
                ],
                [
                    'id' => 'render-blocking-resources',
                    'title' => 'Eliminate render-blocking resources',
                    'description' => 'Resources are blocking the first paint.',
                    'savings_ms' => 450,
                    'savings_display' => 'Est. savings 450 ms',
                ],
            ],
        ],
    ]);
    $prospect->setRelation('search', new Search(['scan_type' => 'combined']));
    $prospect->setRelation('auditJobs', collect([
        new AuditJob([
            'job_type' => 'accessibility',
            'status' => 'complete',
            'completed_at' => $completedAt,
        ]),
    ]));

    $pageSpeed = $this->service->buildOperatorPageSpeed($prospect);

    $this->assertNotNull($pageSpeed);
    $this->assertTrue($pageSpeed['has_detail']);
    $this->assertSame('3.2 s', $pageSpeed['metrics']['lcp']['display']);
    $this->assertCount(2, $pageSpeed['opportunities']);
    $this->assertTrue($pageSpeed['opportunities'][0]['highlight']);
    $this->assertFalse($pageSpeed['opportunities'][1]['highlight']);
    $this->assertSame($completedAt->toIso8601String(), $pageSpeed['audited_at']);
}
```

- [ ] **Step 2: Run tests to verify failure**

```bash
php artisan test --filter=build_operator_page_speed
```

Expected: FAIL — method not defined.

- [ ] **Step 3: Implement `buildOperatorPageSpeed`**

Add to `app/Services/ReportBuilderService.php`:

```php
/**
 * Shaped page speed breakdown for operator prospect detail. Null when section hidden.
 *
 * @return array<string, mixed>|null
 */
public function buildOperatorPageSpeed(Prospect $prospect): ?array
{
    $prospect->loadMissing('search');

    if ($prospect->audit_status !== 'complete') {
        return null;
    }

    if ($prospect->search?->scan_type === 'gbp_only') {
        return null;
    }

    $payload = $prospect->raw_lighthouse_payload ?? [];
    $metrics = $payload['metrics'] ?? null;
    $opportunities = $payload['opportunities'] ?? null;

    if (! is_array($metrics) && ! is_array($opportunities)) {
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
    $a11yPayload = is_array($prospect->raw_a11y_payload) ? $prospect->raw_a11y_payload : [];

    return [
        'audited_at'    => $auditedAt?->toIso8601String() ?? now()->toIso8601String(),
        'url'           => $a11yPayload['url'] ?? $prospect->website_url ?? '',
        'metrics'       => $this->shapePageSpeedMetrics(is_array($metrics) ? $metrics : []),
        'opportunities' => $this->shapePageSpeedOpportunities(is_array($opportunities) ? $opportunities : []),
        'has_detail'    => true,
    ];
}

/**
 * @param  array<string, mixed>  $metrics
 * @return array{lcp: ?array{display: string, rating: string}, inp: ?array{display: string, rating: string}, cls: ?array{display: string, rating: string}, fcp: ?array{display: string, rating: string}}
 */
private function shapePageSpeedMetrics(array $metrics): array
{
    $shape = fn (?array $row) => $row && isset($row['display'], $row['rating'])
        ? ['display' => (string) $row['display'], 'rating' => (string) $row['rating']]
        : null;

    return [
        'lcp' => $shape($metrics['lcp'] ?? null),
        'inp' => $shape($metrics['inp'] ?? null),
        'cls' => $shape($metrics['cls'] ?? null),
        'fcp' => $shape($metrics['fcp'] ?? null),
    ];
}

/**
 * @param  list<array<string, mixed>>  $opportunities
 * @return list<array{id: string, title: string, description: string, savings_display: string, savings_ms: int, highlight: bool}>
 */
private function shapePageSpeedOpportunities(array $opportunities): array
{
    return array_values(array_map(function (array $row) {
        $savingsMs = (int) ($row['savings_ms'] ?? 0);
        $description = (string) ($row['description'] ?? '');

        return [
            'id'              => (string) ($row['id'] ?? ''),
            'title'           => (string) ($row['title'] ?? ''),
            'description'     => mb_strlen($description) > 120 ? mb_substr($description, 0, 117).'...' : $description,
            'savings_display' => (string) ($row['savings_display'] ?? ''),
            'savings_ms'      => $savingsMs,
            'highlight'       => $savingsMs >= 500,
        ];
    }, $opportunities));
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Unit/ReportBuilderServiceTest.php
```

Expected: all PASS.

- [ ] **Step 5: Commit (optional)**

```bash
git add app/Services/ReportBuilderService.php tests/Unit/ReportBuilderServiceTest.php
git commit -m "feat(audit): shape operator page speed breakdown"
```

---

### Task 4: Pass `pageSpeed` from ProspectController

**Files:**
- Modify: `app/Http/Controllers/ProspectController.php`
- Test: `tests/Feature/ProspectShowTest.php`

- [ ] **Step 1: Write failing feature test**

Add to `tests/Feature/ProspectShowTest.php`:

```php
public function test_show_includes_page_speed_when_lighthouse_detail_present(): void
{
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'search_id' => Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined'])->id,
        'audit_status' => 'complete',
        'website_url' => 'https://example.com',
        'performance_score' => 28,
        'raw_a11y_payload' => ['url' => 'https://example.com', 'violations' => []],
        'raw_lighthouse_payload' => [
            'performance' => 28,
            'metrics' => [
                'lcp' => ['display' => '3.2 s', 'rating' => 'poor'],
            ],
            'opportunities' => [
                [
                    'id' => 'unused-javascript',
                    'title' => 'Reduce unused JavaScript',
                    'description' => 'Remove unused JavaScript.',
                    'savings_ms' => 1200,
                    'savings_display' => 'Est. savings 1.2 s',
                ],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get("/prospects/{$prospect->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Prospect/Show')
            ->has('pageSpeed')
            ->where('pageSpeed.metrics.lcp.display', '3.2 s')
            ->where('pageSpeed.opportunities.0.highlight', true));
}

public function test_show_omits_page_speed_for_legacy_lighthouse_payload(): void
{
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'search_id' => Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined'])->id,
        'audit_status' => 'complete',
        'performance_score' => 28,
        'raw_lighthouse_payload' => ['performance' => 28, 'accessibility' => 60],
    ]);

    $this->actingAs($user)
        ->get("/prospects/{$prospect->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Prospect/Show')
            ->where('pageSpeed', null));
}
```

- [ ] **Step 2: Run test to verify failure**

```bash
php artisan test --filter=page_speed
```

Expected: FAIL — `pageSpeed` prop missing.

- [ ] **Step 3: Add prop in `ProspectController::show`**

After the `'audit'` line:

```php
'pageSpeed' => $reportBuilder->buildOperatorPageSpeed($prospect),
```

Ensure `auditJobs` is already eager-loaded (it is in existing controller).

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Feature/ProspectShowTest.php
```

Expected: all PASS.

- [ ] **Step 5: Commit (optional)**

```bash
git add app/Http/Controllers/ProspectController.php tests/Feature/ProspectShowTest.php
git commit -m "feat(prospect): pass pageSpeed prop to detail page"
```

---

### Task 5: `PageSpeedSection` component

**Files:**
- Create: `resources/js/Components/audit/PageSpeedSection.jsx`

- [ ] **Step 1: Create component**

```jsx
import { Card } from '@/Components/ui';

const RATING_COLOR = {
    good: 'var(--color-positive)',
    needs_improvement: 'var(--color-sev-serious)',
    poor: 'var(--color-sev-critical)',
};

const METRIC_LABELS = {
    lcp: 'LCP',
    inp: 'INP',
    cls: 'CLS',
    fcp: 'FCP',
};

function MetricCell({ label, metric }) {
    if (!metric) return null;

    return (
        <div style={{ padding: '12px 16px', background: 'var(--color-paper-2)', borderRadius: 6 }}>
            <div className="eyebrow" style={{ marginBottom: 6 }}>{label}</div>
            <div className="tabular" style={{ fontSize: 20, fontWeight: 500, color: RATING_COLOR[metric.rating] ?? 'inherit' }}>
                {metric.display}
            </div>
        </div>
    );
}

export default function PageSpeedSection({ pageSpeed }) {
    if (!pageSpeed?.has_detail) {
        return null;
    }

    const metrics = pageSpeed.metrics ?? {};
    const opportunities = pageSpeed.opportunities ?? [];
    const auditedLabel = pageSpeed.audited_at
        ? new Date(pageSpeed.audited_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })
        : null;

    return (
        <Card title="Page speed" style={{ marginBottom: 24 }}>
            {auditedLabel && <div className="micro" style={{ marginBottom: 6 }}>Audited {auditedLabel}</div>}
            {pageSpeed.url && (
                <a href={pageSpeed.url} target="_blank" rel="noopener noreferrer" className="micro" style={{ display: 'block', marginBottom: 20 }}>
                    {pageSpeed.url.replace(/^https?:\/\//, '')}
                </a>
            )}

            <div className="eyebrow" style={{ marginBottom: 10 }}>Core Web Vitals</div>
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))',
                gap: 12,
                marginBottom: 12,
            }}>
                {Object.entries(METRIC_LABELS).map(([key, label]) => (
                    <MetricCell key={key} label={label} metric={metrics[key]} />
                ))}
            </div>
            <p className="micro" style={{ marginBottom: 24 }}>Measured via Google Lighthouse · mobile</p>

            <div className="eyebrow" style={{ marginBottom: 12 }}>Opportunities</div>
            {opportunities.length === 0 ? (
                <p className="micro">No significant opportunities detected</p>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    {opportunities.map((opp) => (
                        <div
                            key={opp.id}
                            style={{
                                padding: '12px 16px',
                                borderRadius: 6,
                                borderLeft: opp.highlight ? '3px solid var(--color-sev-critical)' : '3px solid transparent',
                                background: opp.highlight ? 'var(--color-sev-critical-soft)' : 'var(--color-paper-2)',
                            }}
                        >
                            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, marginBottom: 4 }}>
                                <span style={{ fontSize: 13, fontWeight: 500 }}>{opp.title}</span>
                                {opp.savings_display && (
                                    <span className="micro tabular" style={{ whiteSpace: 'nowrap' }}>{opp.savings_display}</span>
                                )}
                            </div>
                            {opp.description && <p className="micro" style={{ margin: 0 }}>{opp.description}</p>}
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}
```

- [ ] **Step 2: Build frontend**

```bash
npm run build
```

Expected: build succeeds with no errors.

- [ ] **Step 3: Commit (optional)**

```bash
git add resources/js/Components/audit/PageSpeedSection.jsx
git commit -m "feat(ui): add PageSpeedSection for prospect detail"
```

---

### Task 6: Update `Prospect/Show.jsx`

**Files:**
- Modify: `resources/js/Pages/Prospect/Show.jsx`

- [ ] **Step 1: Import `PageSpeedSection`**

```javascript
import PageSpeedSection from '@/Components/audit/PageSpeedSection';
```

- [ ] **Step 2: Add `pageSpeed` to component props**

```javascript
export default function ProspectShow({ prospect, search, navigation, report, outreachEmails, audit, lighthouse, pageSpeed, notes = [] }) {
```

- [ ] **Step 3: Remove Performance from `LIGHTHOUSE_METRICS`**

```javascript
const LIGHTHOUSE_METRICS = [
    { label: 'Lighthouse a11y', key: 'accessibility' },
    { label: 'SEO', key: 'seo' },
    { label: 'Best practices', key: 'best_practices' },
];
```

- [ ] **Step 4: Add page speed score card and legacy hint**

After the Accessibility `ScoreCard`, inside the score grid:

```jsx
{search.scan_type !== 'gbp_only' && (
    <ScoreCard
        label="Page speed"
        value={prospect.performance_score > 0 ? prospect.performance_score : null}
        healthScore
        delta="/100"
    />
)}
```

After the score grid closing `</div>`, before Weakness flags:

```jsx
{search.scan_type !== 'gbp_only'
    && prospect.audit_status === 'complete'
    && prospect.performance_score > 0
    && !pageSpeed && (
    <p className="micro" style={{ marginTop: -16, marginBottom: 28, color: 'var(--color-stone-500)' }}>
        Re-run site audit for Core Web Vitals breakdown
    </p>
)}
```

- [ ] **Step 5: Render `PageSpeedSection` between Weakness flags and Site audit**

After the Weakness flags `</Card>`:

```jsx
<PageSpeedSection pageSpeed={pageSpeed} />
```

- [ ] **Step 6: Build and run full test suite**

```bash
npm run build
php artisan test
```

Expected: all PASS.

- [ ] **Step 7: Commit (optional)**

```bash
git add resources/js/Pages/Prospect/Show.jsx
git commit -m "feat(prospect): show page speed score and breakdown on detail page"
```

---

### Task 7: Manual verification

- [ ] **Step 1: Open a prospect with a complete audit** (or create via factory/tinker with extended `raw_lighthouse_payload`).

Confirm:
- Score row shows Combined · GBP · Accessibility · Page speed (health colors).
- Page speed card shows CWV metrics + opportunities with highlighted rows for savings ≥ 500 ms.
- Site audit section still renders below unchanged.

- [ ] **Step 2: Open a legacy prospect** (score-only lighthouse payload).

Confirm:
- Page speed score card shows value.
- Micro hint: "Re-run site audit for Core Web Vitals breakdown".
- No Page speed detail card.

- [ ] **Step 3: After Fly deploy, backfill production prospects**

```bash
php artisan scanner:backfill-audits --execute
```

Expected: incomplete prospects re-audited; new payloads include `metrics` and `opportunities`.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Lighthouse-native score card | Task 6 |
| Remove duplicate Performance from LIGHTHOUSE_METRICS | Task 6 |
| Core Web Vitals row | Tasks 1, 5 |
| Top 8 opportunities with highlight | Tasks 1, 3, 5 |
| Extend audit.js payload | Tasks 1–2 |
| buildOperatorPageSpeed | Task 3 |
| pageSpeed Inertia prop | Task 4 |
| Legacy re-run hint | Task 6 |
| gbp_only hidden | Tasks 3, 6 |
| No migration | Tasks 1–2 (JSON only) |
| Backfill via existing command | Task 7 |

---

## Post-deploy

1. Deploy browser service (`scripts/`) to Fly with updated `audit.js`.
2. Deploy Laravel app.
3. Run `php artisan scanner:backfill-audits --execute` on production to populate metrics for existing prospects.
