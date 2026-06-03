const DEFAULT_GOTO_TIMEOUT = Number(process.env.BROWSER_GOTO_TIMEOUT_MS || 45000);

/**
 * Strip GBP / ads tracking params that can slow or break headless navigation.
 */
export function stripTrackingQuery(url) {
    try {
        const parsed = new URL(url);
        let changed = false;

        for (const key of [...parsed.searchParams.keys()]) {
            if (key.startsWith('utm_') || key === 'gclid' || key === 'fbclid') {
                parsed.searchParams.delete(key);
                changed = true;
            }
        }

        if (!changed) {
            return url;
        }

        const next = parsed.toString();

        return next.endsWith('?') ? next.slice(0, -1) : next;
    } catch {
        return url;
    }
}

/**
 * @returns {Array<{ url: string, waitUntil: string, timeout?: number }>}
 */
export function buildNavigationAttempts(url) {
    const timeout = DEFAULT_GOTO_TIMEOUT;
    const stripped = stripTrackingQuery(url);
    const attempts = [{ url, waitUntil: 'domcontentloaded', timeout }];

    if (stripped !== url) {
        attempts.push({ url: stripped, waitUntil: 'domcontentloaded', timeout });
    }

    // Under load, domcontentloaded may never fire; commit + body is enough for a viewport PNG.
    attempts.push({
        url: stripped,
        waitUntil: 'commit',
        timeout: Math.min(timeout, 30000),
    });

    return attempts;
}

/**
 * Navigate for audit/screenshot capture with fallbacks for slow or tracked URLs.
 */
export async function navigateForCapture(page, url, options = {}) {
    const attempts = buildNavigationAttempts(url);
    let lastError;

    for (let index = 0; index < attempts.length; index++) {
        const attempt = attempts[index];

        try {
            const response = await page.goto(attempt.url, {
                waitUntil: attempt.waitUntil,
                timeout: attempt.timeout ?? DEFAULT_GOTO_TIMEOUT,
            });

            if (attempt.waitUntil === 'commit') {
                await page.waitForSelector('body', { timeout: 10000, state: 'attached' });
            }

            return response;
        } catch (error) {
            lastError = error;

            if (index < attempts.length - 1) {
                continue;
            }
        }
    }

    throw lastError;
}
