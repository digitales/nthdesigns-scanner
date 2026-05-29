import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    buildPageSpeedUrl,
    parsePageSpeedResponse,
    fetchPageSpeedLighthouse,
} from './pagespeed-fetch.js';

const lighthouseFixture = JSON.parse(
    readFileSync(join(dirname(fileURLToPath(import.meta.url)), 'fixtures/lighthouse-report.json'), 'utf8'),
);

test('buildPageSpeedUrl includes mobile strategy and categories', () => {
    const url = buildPageSpeedUrl('https://example.com', 'test-key');

    assert.equal(url.searchParams.get('url'), 'https://example.com');
    assert.equal(url.searchParams.get('strategy'), 'mobile');
    assert.equal(url.searchParams.get('key'), 'test-key');
    assert.deepEqual(url.searchParams.getAll('category'), ['PERFORMANCE', 'ACCESSIBILITY', 'SEO']);
});

test('parsePageSpeedResponse returns null without lighthouseResult', () => {
    assert.equal(parsePageSpeedResponse({}), null);
    assert.equal(parsePageSpeedResponse({ lighthouseResult: { audits: {} } }), null);
});

test('parsePageSpeedResponse builds payload from lighthouseResult', () => {
    const payload = parsePageSpeedResponse({ lighthouseResult: lighthouseFixture });

    assert.equal(payload.performance, 28);
    assert.equal(payload.accessibility, 60);
    assert.equal(payload.seo, 70);
    assert.ok(payload.metrics.lcp);
    assert.equal(payload.opportunities.length, 2);
});

test('fetchPageSpeedLighthouse returns null without api key', async () => {
    assert.equal(await fetchPageSpeedLighthouse('https://example.com', {}), null);
});

test('fetchPageSpeedLighthouse parses successful response', async () => {
    const originalFetch = globalThis.fetch;

    globalThis.fetch = async () => ({
        ok: true,
        json: async () => ({
            captchaResult: 'CAPTCHA_NOT_NEEDED',
            lighthouseResult: lighthouseFixture,
        }),
    });

    try {
        const result = await fetchPageSpeedLighthouse('https://example.com', {
            PAGESPEED_API_KEY: 'test-key',
        });

        assert.equal(result?.performance, 28);
        assert.equal(result?.seo, 70);
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('fetchPageSpeedLighthouse returns null on HTTP error', async () => {
    const originalFetch = globalThis.fetch;

    globalThis.fetch = async () => ({
        ok: false,
        status: 429,
    });

    try {
        assert.equal(
            await fetchPageSpeedLighthouse('https://example.com', { PAGESPEED_API_KEY: 'test-key' }),
            null,
        );
    } finally {
        globalThis.fetch = originalFetch;
    }
});
