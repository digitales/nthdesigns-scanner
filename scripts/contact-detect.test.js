import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveContactFromInputs } from './contact-detect.js';

test('detects contact form 7 and contact page link', () => {
    const html = `
        <html><body>
            <nav><a href="/contact-us">Contact</a></nav>
            <form class="wpcf7-form">
                <input type="email" name="your-email" />
                <textarea name="your-message"></textarea>
            </form>
        </body></html>
    `;

    const result = resolveContactFromInputs({
        html,
        finalUrl: 'https://example.com/',
    });

    assert.equal(result.status, 'detected');
    assert.equal(result.has_contact_form, true);
    assert.equal(result.contact_page_url, 'https://example.com/contact-us');
    assert.ok(result.signals.some((signal) => signal.startsWith('form:')));
});

test('extracts linkedin company url', () => {
    const html = `
        <footer>
            <a href="https://www.linkedin.com/company/acme-dental/">LinkedIn</a>
        </footer>
    `;

    const result = resolveContactFromInputs({
        html,
        finalUrl: 'https://example.com/',
    });

    assert.equal(result.linkedin_url, 'https://www.linkedin.com/company/acme-dental');
});

test('collects mailto and visible emails as suggestions', () => {
    const html = `
        <a href="mailto:info@acme.co.uk">Email us</a>
        <p>Reach us at hello@acme.co.uk</p>
    `;

    const result = resolveContactFromInputs({
        html,
        finalUrl: 'https://example.com/',
    });

    assert.deepEqual(result.suggested_emails.sort(), ['hello@acme.co.uk', 'info@acme.co.uk']);
});

test('returns failed status when error provided', () => {
    const result = resolveContactFromInputs({
        html: '',
        finalUrl: 'https://example.com/',
        error: 'timeout',
    });

    assert.equal(result.status, 'failed');
    assert.equal(result.has_contact_form, false);
});
