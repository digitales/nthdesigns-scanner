import { buildLighthousePayload } from './lighthouse-detail.js';

const PAGESPEED_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

const DEFAULT_CATEGORIES = ['PERFORMANCE', 'ACCESSIBILITY', 'SEO'];

/**
 * @param {string} targetUrl
 * @param {string} apiKey
 * @param {{ strategy?: string, categories?: string[] }} [options]
 */
export function buildPageSpeedUrl(targetUrl, apiKey, options = {}) {
    const url = new URL(PAGESPEED_ENDPOINT);
    url.searchParams.set('url', targetUrl);
    url.searchParams.set('strategy', options.strategy ?? 'mobile');
    url.searchParams.set('key', apiKey);

    for (const category of options.categories ?? DEFAULT_CATEGORIES) {
        url.searchParams.append('category', category);
    }

    return url;
}

/**
 * @param {unknown} data
 * @returns {ReturnType<typeof buildLighthousePayload> | null}
 */
export function parsePageSpeedResponse(data) {
    const lighthouseResult = data?.lighthouseResult;

    if (!lighthouseResult?.categories || !lighthouseResult?.audits) {
        return null;
    }

    return buildLighthousePayload(lighthouseResult);
}

/**
 * Fetch Lighthouse-shaped payload from PageSpeed Insights when local CLI fails.
 *
 * @param {string} targetUrl
 * @param {NodeJS.ProcessEnv} [env]
 * @returns {Promise<ReturnType<typeof buildLighthousePayload> | null>}
 */
export async function fetchPageSpeedLighthouse(targetUrl, env = process.env) {
    const apiKey = env.PAGESPEED_API_KEY?.trim();

    if (!apiKey) {
        return null;
    }

    const requestUrl = buildPageSpeedUrl(targetUrl, apiKey);

    try {
        const response = await fetch(requestUrl, {
            signal: AbortSignal.timeout(90_000),
        });

        if (!response.ok) {
            return null;
        }

        const data = await response.json();

        if (data.captchaResult && data.captchaResult !== 'CAPTCHA_NOT_NEEDED') {
            return null;
        }

        return parsePageSpeedResponse(data);
    } catch {
        return null;
    }
}
