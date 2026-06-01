import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveCmsFromInputs } from './cms-detect.js';

const fixturesDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'tests', 'fixtures', 'cms');

function load(name) {
    return readFileSync(join(fixturesDir, name), 'utf8');
}

test('detects WordPress with version and high confidence', () => {
    const html = load('wordpress-generator.html');
    const result = resolveCmsFromInputs({
        html,
        bodyClass: 'wp-singular page-id-12',
        headers: {},
        finalUrl: 'https://example.com/',
    });

    assert.equal(result.platform, 'wordpress');
    assert.equal(result.version, '6.4.2');
    assert.equal(result.confidence, 'high');
    assert.ok(result.signals.some((s) => s.id === 'meta_generator' && s.matched));
});

test('detects Shopify from HTML markers', () => {
    const html = load('shopify-cdn.html');
    const result = resolveCmsFromInputs({
        html,
        bodyClass: '',
        headers: {},
        finalUrl: 'https://shop.example.com/',
    });

    assert.equal(result.platform, 'shopify');
});

test('returns unknown when no signals match', () => {
    const html = load('unknown-static.html');
    const result = resolveCmsFromInputs({
        html,
        bodyClass: 'layout',
        headers: {},
        finalUrl: 'https://example.com/',
    });

    assert.equal(result.platform, 'unknown');
    assert.equal(result.confidence, 'low');
});

test('returns fetch_failed signal on error', () => {
    const result = resolveCmsFromInputs({
        html: '',
        bodyClass: '',
        headers: {},
        finalUrl: 'https://example.com/',
        error: 'timeout',
    });

    assert.equal(result.platform, 'unknown');
    assert.ok(result.signals.some((s) => s.id === 'fetch_failed' && s.matched));
});
