const PLATFORMS = [
    'wordpress',
    'shopify',
    'wix',
    'squarespace',
    'webflow',
    'joomla',
    'drupal',
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
            || htmlLower.includes('wix-warmup-data'),
        detail: () => 'Wix assets or markers',
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
];

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

    if (strong.length >= 2) {
        return 'high';
    }

    if (strong.length >= 1 || medium.length >= 2) {
        return 'medium';
    }

    return 'low';
}

/**
 * @param {{ html: string, bodyClass: string, headers: Record<string, string>, finalUrl: string, error?: string|null }} inputs
 */
export function resolveCmsFromInputs({ html, bodyClass, headers, finalUrl, error = null }) {
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
    const headers = response
        ? Object.fromEntries(
            Object.entries(response.headers()).map(([key, value]) => [key.toLowerCase(), value]),
        )
        : {};

    return resolveCmsFromInputs({
        html,
        bodyClass,
        headers,
        finalUrl: page.url(),
    });
}
