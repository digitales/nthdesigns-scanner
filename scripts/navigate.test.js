import assert from 'node:assert/strict';
import test from 'node:test';
import { buildNavigationAttempts, stripTrackingQuery } from './navigate.js';

test('stripTrackingQuery removes utm and click ids', () => {
    const url = 'https://www.example.co.uk/?utm_source=google&utm_medium=organic&utm_campaign=gbp&gclid=abc';
    const stripped = stripTrackingQuery(url);

    assert.equal(stripped, 'https://www.example.co.uk/');
});

test('stripTrackingQuery leaves clean urls unchanged', () => {
    const url = 'https://example.com/about?ref=partner';

    assert.equal(stripTrackingQuery(url), url);
});

test('buildNavigationAttempts adds stripped and commit fallbacks', () => {
    const url = 'https://example.com/?utm_source=google';
    const attempts = buildNavigationAttempts(url);

    assert.equal(attempts.length, 3);
    assert.equal(attempts[0].url, url);
    assert.equal(attempts[0].waitUntil, 'domcontentloaded');
    assert.equal(attempts[1].url, 'https://example.com/');
    assert.equal(attempts[2].waitUntil, 'commit');
});
