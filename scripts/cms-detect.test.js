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

test('detects Wix from wix-first-paint bootstrap markup', () => {
    const html = load('wix-first-paint.html');
    const result = resolveCmsFromInputs({
        html,
        bodyClass: '',
        headers: {},
        finalUrl: 'https://www.example-hotel.co.uk/',
    });

    assert.equal(result.platform, 'wix');
    assert.equal(result.confidence, 'high');
    assert.ok(result.signals.some((s) => s.id === 'html_wix' && s.matched));
    assert.ok(result.signals.some((s) => s.id === 'html_wix_dom_ids' && s.matched));
});

test('detects Wix from parastorage CDN scripts', () => {
    const html = load('wix-parastorage.html');
    const result = resolveCmsFromInputs({
        html,
        bodyClass: '',
        headers: {},
        finalUrl: 'https://example.com/',
    });

    assert.equal(result.platform, 'wix');
    assert.ok(result.signals.some((s) => s.id === 'html_wix' && s.matched));
});

test('detects Wix from response headers when HTML is empty', () => {
    const result = resolveCmsFromInputs({
        html: '<html><body></body></html>',
        bodyClass: '',
        headers: {
            'x-wix-request-id': '1780410627.620235568732923178',
            link: '<https://static.parastorage.com/>; rel=preconnect',
            server: 'Pepyaka',
        },
        finalUrl: 'https://www.example-hotel.co.uk/',
    });

    assert.equal(result.platform, 'wix');
    assert.equal(result.confidence, 'medium');
    assert.ok(result.signals.some((s) => s.id === 'header_wix' && s.matched));
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

test('detects Craft CMS from CraftSessionId Set-Cookie header', () => {
    const result = resolveCmsFromInputs({
        html: '<html><body></body></html>',
        bodyClass: '',
        headers: {
            'set-cookie': 'CraftSessionId=abc123; path=/; secure; HttpOnly',
        },
        finalUrl: 'https://www.example-dental.co.uk/',
    });

    assert.equal(result.platform, 'craft');
    assert.equal(result.confidence, 'high');
    assert.ok(result.signals.some((s) => s.id === 'cookie_craft_session' && s.matched));
});

test('detects Craft CMS from browser cookie names', () => {
    const result = resolveCmsFromInputs({
        html: '<html><body></body></html>',
        bodyClass: '',
        headers: {},
        cookieNames: ['OptanonConsent', 'CraftSessionId'],
        finalUrl: 'https://www.example-dental.co.uk/',
    });

    assert.equal(result.platform, 'craft');
    assert.equal(result.confidence, 'high');
});

test('detects Craft CMS from HTML markers with version', () => {
    const html = load('craft-cpresources.html');
    const result = resolveCmsFromInputs({
        html,
        bodyClass: 'home',
        headers: {},
        finalUrl: 'https://example.com/',
    });

    assert.equal(result.platform, 'craft');
    assert.equal(result.version, '4.14.0');
    assert.equal(result.confidence, 'high');
    assert.ok(result.signals.some((s) => s.id === 'meta_generator_craft' && s.matched));
    assert.ok(result.signals.some((s) => s.id === 'html_cpresources' && s.matched));
});
