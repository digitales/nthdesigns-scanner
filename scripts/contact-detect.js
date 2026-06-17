const EMAIL_PATTERN = /[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/gi;

const CONTACT_PATH_PATTERN = /\/(contact|contact-us|get-in-touch|enquiries|reach-us|about\/contact)(?:\/|$|\?|#)/i;

const FORM_PLUGIN_MARKERS = [
    { id: 'form:contact-form-7', pattern: /wpcf7|contact-form-7/i },
    { id: 'form:gravity-forms', pattern: /gform_wrapper|gravity/i },
    { id: 'form:wpforms', pattern: /wpforms-container|wpforms-form/i },
    { id: 'form:generic-contact', pattern: /action=["'][^"']*contact/i },
];

const NOISE_EMAIL_DOMAINS = ['example.com', 'sentry.io', 'wixpress.com', 'domain.com'];

/**
 * @param {string} html
 * @returns {string[]}
 */
function extractSuggestedEmails(html) {
    /** @type {Set<string>} */
    const emails = new Set();

    const mailtoRegex = /href=["']mailto:([^"'?]+)/gi;
    let match;

    while ((match = mailtoRegex.exec(html)) !== null) {
        const normalized = match[1].trim().toLowerCase();

        if (isValidSuggestedEmail(normalized)) {
            emails.add(normalized);
        }
    }

    const textMatches = html.match(EMAIL_PATTERN) ?? [];

    for (const raw of textMatches) {
        const normalized = raw.toLowerCase();

        if (isValidSuggestedEmail(normalized)) {
            emails.add(normalized);
        }
    }

    return [...emails];
}

/**
 * @param {string} email
 */
function isValidSuggestedEmail(email) {
    if (!email.includes('@')) {
        return false;
    }

    const domain = email.split('@')[1] ?? '';

    if (NOISE_EMAIL_DOMAINS.some((noise) => domain.endsWith(noise))) {
        return false;
    }

    if (/\.(png|jpg|jpeg|gif|svg|webp)$/i.test(email)) {
        return false;
    }

    return true;
}

/**
 * @param {string} html
 * @returns {string|null}
 */
function extractLinkedInUrl(html) {
    const linkedinRegex = /href=["'](https?:\/\/(?:[a-z]+\.)?linkedin\.com\/(?:company|in)\/[^"'?#]+)/gi;
    let match;
    let first = null;

    while ((match = linkedinRegex.exec(html)) !== null) {
        const url = normalizeLinkedInUrl(match[1]);

        if (url) {
            first = first ?? url;
        }
    }

    return first;
}

/**
 * @param {string} url
 */
function normalizeLinkedInUrl(url) {
    try {
        const parsed = new URL(url);

        if (!parsed.hostname.includes('linkedin.com')) {
            return null;
        }

        parsed.search = '';
        parsed.hash = '';

        return parsed.toString().replace(/\/$/, '');
    } catch {
        return null;
    }
}

/**
 * @param {string} html
 * @param {string} finalUrl
 * @returns {string|null}
 */
function extractContactPageUrl(html, finalUrl) {
    const anchorRegex = /href=["']([^"']+)["']/gi;
    let match;

    while ((match = anchorRegex.exec(html)) !== null) {
        const href = match[1];

        if (!CONTACT_PATH_PATTERN.test(href)) {
            continue;
        }

        try {
            const resolved = new URL(href, finalUrl).toString().replace(/\/$/, '');

            return resolved;
        } catch {
            continue;
        }
    }

    if (CONTACT_PATH_PATTERN.test(finalUrl)) {
        return finalUrl.replace(/\/$/, '');
    }

    return null;
}

/**
 * @param {string} html
 */
function detectContactFormSignals(html) {
    /** @type {string[]} */
    const signals = [];

    for (const marker of FORM_PLUGIN_MARKERS) {
        if (marker.pattern.test(html)) {
            signals.push(marker.id);
        }
    }

    const hasFormTag = /<form[\s>]/i.test(html);
    const hasEmailField = /type=["']email["']|name=["'][^"']*email/i.test(html);
    const hasMessageField = /<textarea|name=["'][^"']*(message|enquiry|comment|body)/i.test(html);

    if (hasFormTag && hasEmailField && hasMessageField) {
        signals.push('form:email-and-message-fields');
    }

    const hasContactForm = signals.length > 0;

    return { hasContactForm, signals };
}

/**
 * @param {{ html: string, finalUrl: string, error?: string|null }} inputs
 */
export function resolveContactFromInputs({ html, finalUrl, error = null }) {
    const detectedAt = new Date().toISOString();

    if (error) {
        return {
            status: 'failed',
            error,
            url: finalUrl,
            has_contact_form: false,
            contact_page_url: null,
            suggested_emails: [],
            linkedin_url: null,
            confidence: 'low',
            signals: [],
            detected_at: detectedAt,
        };
    }

    const { hasContactForm, signals } = detectContactFormSignals(html);
    const suggestedEmails = extractSuggestedEmails(html);
    const linkedinUrl = extractLinkedInUrl(html);
    const contactPageUrl = extractContactPageUrl(html, finalUrl);

    let confidence = 'low';

    if (hasContactForm && contactPageUrl) {
        confidence = 'high';
    } else if (hasContactForm || contactPageUrl || linkedinUrl) {
        confidence = 'medium';
    }

    return {
        status: 'detected',
        url: finalUrl,
        has_contact_form: hasContactForm,
        contact_page_url: contactPageUrl,
        suggested_emails: suggestedEmails,
        linkedin_url: linkedinUrl,
        confidence,
        signals,
        detected_at: detectedAt,
    };
}

/**
 * @param {import('playwright').Page} page
 */
export async function detectContactSignals(page) {
    const html = await page.content();

    return resolveContactFromInputs({
        html,
        finalUrl: page.url(),
    });
}
