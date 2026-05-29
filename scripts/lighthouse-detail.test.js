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
