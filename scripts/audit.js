#!/usr/bin/env node

import { chromium } from 'playwright';
import { chromiumLaunchOptions } from './browser.js';
import AxeBuilder from '@axe-core/playwright';
import { execFile, execFileSync } from 'node:child_process';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
import { existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { buildLighthousePayload } from './lighthouse-detail.js';
import { fetchPageSpeedLighthouse } from './pagespeed-fetch.js';
import { detectCms } from './cms-detect.js';
import { formatAuditError } from './audit-error-format.js';
import { navigateForCapture } from './navigate.js';

const url = process.argv[2];
const outputDir = process.argv[3] || null;
const lighthouseBinary = process.env.LIGHTHOUSE_BINARY || 'lighthouse';

if (!url) {
    console.error(JSON.stringify({ error: 'URL argument required' }));
    process.exit(1);
}

if (outputDir) {
    mkdirSync(outputDir, { recursive: true });
}

async function runAxe(page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    return {
        violations: results.violations,
        passes: results.passes.length,
        incomplete: results.incomplete.length,
    };
}

function lighthouseAvailable() {
    if (existsSync(lighthouseBinary) || lighthouseBinary !== 'lighthouse') {
        return true;
    }

    try {
        execFileSync('which', ['lighthouse'], { stdio: 'pipe' });

        return true;
    } catch {
        return false;
    }
}

async function runLighthouse(targetUrl) {
    if (!lighthouseAvailable()) {
        return null;
    }

    const chromeFlags = [
        '--headless',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
    ].join(' ');

    try {
        const { stdout: output } = await execFileAsync(
            lighthouseBinary,
            [
                targetUrl,
                '--quiet',
                `--chrome-flags=${chromeFlags}`,
                '--only-categories=performance,accessibility,seo',
                '--output=json',
            ],
            {
                encoding: 'utf8',
                timeout: 90000,
                maxBuffer: 10 * 1024 * 1024,
                env: process.env,
            },
        );

        const report = JSON.parse(output);

        return buildLighthousePayload(report);
    } catch (error) {
        console.error('[audit.js] lighthouse failed:', error?.message ?? error);

        return null;
    }
}

async function resolveLighthouse(targetUrl) {
    const local = await runLighthouse(targetUrl);

    if (local !== null) {
        return local;
    }

    return fetchPageSpeedLighthouse(targetUrl);
}

async function captureViolationScreenshots(page, violations) {
    if (!outputDir) {
        return [];
    }

    const impactOrder = { critical: 0, serious: 1, moderate: 2, minor: 3 };
    const sorted = [...violations].sort(
        (a, b) => (impactOrder[a.impact] ?? 4) - (impactOrder[b.impact] ?? 4),
    );

    const top = sorted
        .filter(v => ['critical', 'serious', 'moderate'].includes(v.impact))
        .slice(0, 5);

    const screenshots = [];

    for (let i = 0; i < top.length; i++) {
        const violation = top[i];
        const selector = violation.nodes?.[0]?.target?.[0];

        if (!selector) {
            continue;
        }

        const filename = `violation-${i}.png`;
        const filepath = join(outputDir, filename);

        try {
            const locator = page.locator(selector).first();
            await locator.scrollIntoViewIfNeeded({ timeout: 5000 });
            await locator.screenshot({ path: filepath, timeout: 8000, animations: 'disabled' });

            screenshots.push({
                violation_id: violation.id,
                index: i,
                file: filename,
            });
        } catch {
            // Element may not be visible or selector invalid — skip this violation.
        }
    }

    return screenshots;
}

async function main() {
    const browser = await chromium.launch(chromiumLaunchOptions);
    // axe-core's finishRun() opens a second page on page.context(); that fails on
    // Playwright's default context from browser.newPage() — use newContext() instead.
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await context.newPage();

    try {
        const lighthousePromise = resolveLighthouse(url);

        const response = await navigateForCapture(page, url);
        const cms = await detectCms(page, response);
        const axe = await runAxe(page);
        const violationScreenshots = await captureViolationScreenshots(page, axe.violations);
        const lighthouse = await lighthousePromise;

        const payload = {
            url,
            violations: axe.violations,
            pass_count: axe.passes,
            incomplete_count: axe.incomplete,
            violation_screenshots: violationScreenshots,
            lighthouse,
            cms,
        };

        process.stdout.write(JSON.stringify(payload));
    } catch (error) {
        process.stdout.write(JSON.stringify({
            url,
            error: formatAuditError(error, { stage: 'audit' }),
            violations: [],
            pass_count: 0,
            incomplete_count: 0,
            violation_screenshots: [],
            lighthouse: null,
        }));
        process.exit(1);
    } finally {
        await context.close();
        await browser.close();
    }
}

main();
