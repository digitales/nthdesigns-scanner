import https from 'node:https';
import http from 'node:http';

const PLATFORMS = [
    'wordpress',
    'shopify',
    'wix',
    'squarespace',
    'webflow',
    'joomla',
    'drupal',
    'craft',
];

const RULES = [
    {
        id: 'meta_generator',
        platform: 'wordpress',
        weight: 10,
        test: ({ generator }) => /wordpress/i.test(generator ?? ''),
        detail: ({ generator }) => generator,
        version: ({ generator }) => {
            const match = (generator ?? '').match(/WordPress\s+([\d.]+)/i);

            return match?.[1] ?? null;
        },
    },
    {
        id: 'body_class_wp',
        platform: 'wordpress',
        weight: 8,
        test: ({ bodyClass }) => /\bwp-[\w-]+/.test(bodyClass) || /\bpage-id-\d+\b/.test(bodyClass),
        detail: ({ bodyClass }) => bodyClass,
    },
    {
        id: 'html_wp_content',
        platform: 'wordpress',
        weight: 5,
        test: ({ htmlLower }) => htmlLower.includes('/wp-content/')
            || htmlLower.includes('/wp-includes/')
            || htmlLower.includes('wp-json'),
        detail: () => 'WordPress paths in HTML',
    },
    {
        id: 'header_x_powered_by',
        platform: 'wordpress',
        weight: 2,
        test: ({ headers }) => /php|wordpress/i.test(headers['x-powered-by'] ?? ''),
        detail: ({ headers }) => headers['x-powered-by'] ?? '',
    },
    {
        id: 'html_shopify_cdn',
        platform: 'shopify',
        weight: 10,
        test: ({ htmlLower }) => htmlLower.includes('cdn.shopify.com') || htmlLower.includes('myshopify.com'),
        detail: () => 'Shopify CDN or myshopify.com',
    },
    {
        id: 'html_shopify_global',
        platform: 'shopify',
        weight: 8,
        test: ({ html }) => /Shopify\.(shop|theme|routes)/.test(html),
        detail: () => 'Shopify global in page',
    },
    {
        id: 'html_wix',
        platform: 'wix',
        weight: 10,
        test: ({ htmlLower }) => htmlLower.includes('wix.com')
            || htmlLower.includes('wixstatic.com')
            || htmlLower.includes('wix-warmup-data')
            || htmlLower.includes('parastorage.com')
            || htmlLower.includes('wix-thunderbolt')
            || htmlLower.includes('wix-first-paint'),
        detail: () => 'Wix assets or markers',
    },
    {
        id: 'html_wix_dom_ids',
        platform: 'wix',
        weight: 8,
        test: ({ html }) => /\bid=["']wix-/i.test(html),
        detail: () => 'Wix element or script ids',
    },
    {
        id: 'header_wix',
        platform: 'wix',
        weight: 8,
        test: ({ headers }) => (headers['x-wix-request-id'] ?? '') !== ''
            || /wix|parastorage/i.test(headers.link ?? '')
            || /pepyaka/i.test(headers.server ?? ''),
        detail: ({ headers }) => headers['x-wix-request-id']
            ?? headers.link
            ?? headers.server
            ?? '',
    },
    {
        id: 'meta_generator_wix',
        platform: 'wix',
        weight: 10,
        test: ({ generator }) => /wix/i.test(generator ?? ''),
        detail: ({ generator }) => generator,
    },
    {
        id: 'html_squarespace',
        platform: 'squarespace',
        weight: 10,
        test: ({ htmlLower }) => htmlLower.includes('squarespace.com')
            || htmlLower.includes('static.squarespace.com'),
        detail: () => 'Squarespace assets',
    },
    {
        id: 'html_webflow',
        platform: 'webflow',
        weight: 10,
        test: ({ htmlLower }) => htmlLower.includes('webflow.com')
            || htmlLower.includes('wfdesign')
            || htmlLower.includes('data-wf-page'),
        detail: () => 'Webflow markers',
    },
    {
        id: 'meta_generator_joomla',
        platform: 'joomla',
        weight: 10,
        test: ({ generator }) => /joomla!/i.test(generator ?? ''),
        detail: ({ generator }) => generator,
        version: ({ generator }) => {
            const match = (generator ?? '').match(/Joomla!\s*([\d.]+)/i);

            return match?.[1] ?? null;
        },
    },
    {
        id: 'html_joomla_com',
        platform: 'joomla',
        weight: 6,
        test: ({ htmlLower }) => htmlLower.includes('/components/com_'),
        detail: () => 'Joomla component path',
    },
    {
        id: 'meta_generator_drupal',
        platform: 'drupal',
        weight: 10,
        test: ({ generator }) => /drupal/i.test(generator ?? ''),
        detail: ({ generator }) => generator,
        version: ({ generator }) => {
            const match = (generator ?? '').match(/Drupal\s*([\d.]+)/i);

            return match?.[1] ?? null;
        },
    },
    {
        id: 'html_drupal',
        platform: 'drupal',
        weight: 6,
        test: ({ htmlLower }) => htmlLower.includes('drupal.js')
            || htmlLower.includes('/sites/default/'),
        detail: () => 'Drupal assets or paths',
    },
    {
        id: 'cookie_craft_session',
        platform: 'craft',
        weight: 10,
        test: ({ headers, cookieNames }) => craftCookiePattern.test(craftCookieSource(headers, cookieNames)),
        detail: ({ headers, cookieNames }) => {
            const match = craftCookieSource(headers, cookieNames).match(/(?:CraftSessionId|CRAFT_CSRF_TOKEN)[^;\s]*/i);

            return match?.[0] ?? 'Craft session cookie';
        },
    },
    {
        id: 'meta_generator_craft',
        platform: 'craft',
        weight: 10,
        test: ({ generator }) => /craft\s+cms/i.test(generator ?? ''),
        detail: ({ generator }) => generator,
        version: ({ generator }) => {
            const match = (generator ?? '').match(/Craft CMS\s*([\d.]+)/i);

            return match?.[1] ?? null;
        },
    },
    {
        id: 'html_cpresources',
        platform: 'craft',
        weight: 8,
        test: ({ htmlLower }) => htmlLower.includes('/cpresources/'),
        detail: () => 'Craft cpresources path in HTML',
    },
    {
        id: 'html_craft_actions',
        platform: 'craft',
        weight: 6,
        test: ({ htmlLower }) => htmlLower.includes('/actions/'),
        detail: () => 'Craft actions path in HTML',
    },
];

const craftCookiePattern = /CraftSessionId|CRAFT_CSRF_TOKEN/i;

function craftCookieSource(headers, cookieNames = []) {
    return [
        headers?.['set-cookie'] ?? '',
        cookieNames.join('; '),
    ].join('; ');
}

function parseGenerator(html) {
    const match = html.match(/<meta[^>]+name=["']generator["'][^>]*>/i);

    if (!match) {
        return '';
    }

    const content = match[0].match(/content=["']([^"']+)["']/i);

    return content?.[1] ?? '';
}

function normalizeHeaders(headers) {
    if (!headers || typeof headers !== 'object') {
        return {};
    }

    return Object.fromEntries(
        Object.entries(headers).map(([key, value]) => [key.toLowerCase(), String(value)]),
    );
}

function scorePlatform(platform, matchedRules) {
    return matchedRules
        .filter((rule) => rule.platform === platform)
        .reduce((sum, rule) => sum + rule.weight, 0);
}

function resolveConfidence(platform, matchedRules, totalScore) {
    if (platform === 'unknown' || totalScore === 0) {
        return 'low';
    }

    const strong = matchedRules.filter((rule) => rule.platform === platform && rule.weight >= 8);
    const medium = matchedRules.filter((rule) => rule.platform === platform && rule.weight >= 5);

    if (platform === 'wordpress') {
        const hasGenerator = strong.some((rule) => rule.id === 'meta_generator');
        const hasBody = strong.some((rule) => rule.id === 'body_class_wp');
        const hasHtml = medium.some((rule) => rule.id === 'html_wp_content');

        if (hasGenerator || (hasBody && hasHtml)) {
            return 'high';
        }

        if (strong.length >= 1 || medium.length >= 2) {
            return 'medium';
        }

        return 'low';
    }

    if (platform === 'craft') {
        const hasSessionCookie = strong.some((rule) => rule.id === 'cookie_craft_session');
        const hasGenerator = strong.some((rule) => rule.id === 'meta_generator_craft');

        if (hasSessionCookie || hasGenerator) {
            return 'high';
        }

        if (strong.length >= 1 || medium.length >= 2) {
            return 'medium';
        }

        return 'low';
    }

    if (strong.length >= 2) {
        return 'high';
    }

    if (strong.length >= 1 || medium.length >= 2) {
        return 'medium';
    }

    return 'low';
}

/**
 * Lightweight HEAD probe for Set-Cookie headers Playwright may omit on cached document responses.
 *
 * @param {string} url
 * @param {number} timeoutMs
 * @returns {Promise<Record<string, string>>}
 */
export function fetchDocumentHeaders(url, timeoutMs = 10000) {
    return new Promise((resolve) => {
        let parsed;

        try {
            parsed = new URL(url);
        } catch {
            resolve({});

            return;
        }

        const client = parsed.protocol === 'https:' ? https : http;

        const req = client.request(url, {
            method: 'HEAD',
            headers: { 'User-Agent': 'nthdesigns-scanner-cms-detect/1.0' },
            timeout: timeoutMs,
        }, (res) => {
            const headers = {};

            for (const [key, value] of Object.entries(res.headers)) {
                headers[key.toLowerCase()] = Array.isArray(value) ? value.join('\n') : String(value);
            }

            res.resume();
            resolve(headers);
        });

        req.on('error', () => resolve({}));
        req.on('timeout', () => {
            req.destroy();
            resolve({});
        });
        req.end();
    });
}

/**
 * @param {{ html: string, bodyClass: string, headers: Record<string, string>, cookieNames?: string[], finalUrl: string, error?: string|null }} inputs
 */
export function resolveCmsFromInputs({ html, bodyClass, headers, cookieNames = [], finalUrl, error = null }) {
    const detectedAt = new Date().toISOString();

    if (error) {
        return {
            platform: 'unknown',
            version: null,
            confidence: 'low',
            signals: [{ id: 'fetch_failed', matched: true, detail: error }],
            detected_at: detectedAt,
            url: finalUrl,
        };
    }

    const htmlLower = (html ?? '').toLowerCase();
    const generator = parseGenerator(html ?? '');
    const ctx = {
        html: html ?? '',
        htmlLower,
        bodyClass: bodyClass ?? '',
        headers: normalizeHeaders(headers),
        cookieNames,
        generator,
    };

    const signals = RULES.map((rule) => {
        const matched = rule.test(ctx);

        return {
            id: rule.id,
            matched,
            detail: matched ? (rule.detail?.(ctx) ?? '') : '',
        };
    });

    const matchedRules = RULES.filter((rule, index) => signals[index].matched);

    let platform = 'unknown';
    let bestScore = 0;

    for (const candidate of PLATFORMS) {
        const score = scorePlatform(candidate, matchedRules);

        if (score > bestScore) {
            bestScore = score;
            platform = candidate;
        }
    }

    if (bestScore === 0) {
        platform = 'unknown';
    }

    let version = null;

    if (platform !== 'unknown') {
        for (const rule of matchedRules) {
            if (rule.platform === platform && rule.version) {
                version = rule.version(ctx);

                if (version) {
                    break;
                }
            }
        }
    }

    const confidence = resolveConfidence(platform, matchedRules, bestScore);

    return {
        platform,
        version,
        confidence,
        signals,
        detected_at: detectedAt,
        url: finalUrl,
    };
}

/**
 * @param {import('playwright').Page} page
 * @param {import('playwright').Response|null} response
 */
export async function detectCms(page, response = null) {
    const html = await page.content();
    const bodyClass = (await page.locator('body').getAttribute('class')) ?? '';
    let headers = {};

    if (response) {
        try {
            headers = await response.allHeaders();
        } catch {
            headers = response.headers();
        }

        headers = normalizeHeaders(headers);
    }

    const cookieNames = (await page.context().cookies()).map((cookie) => cookie.name);

    if (!craftCookiePattern.test(craftCookieSource(headers, cookieNames))) {
        const probed = normalizeHeaders(await fetchDocumentHeaders(page.url()));

        if (probed['set-cookie']) {
            headers['set-cookie'] = headers['set-cookie']
                ? `${headers['set-cookie']}\n${probed['set-cookie']}`
                : probed['set-cookie'];
        }
    }

    return resolveCmsFromInputs({
        html,
        bodyClass,
        headers,
        cookieNames,
        finalUrl: page.url(),
    });
}
