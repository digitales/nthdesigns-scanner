#!/usr/bin/env node

import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';

const url = process.argv[2];
const lighthouseBinary = process.env.LIGHTHOUSE_BINARY || 'lighthouse';

if (!url) {
    console.error(JSON.stringify({ error: 'URL argument required' }));
    process.exit(1);
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

function runLighthouse(targetUrl) {
    if (!existsSync(lighthouseBinary) && lighthouseBinary === 'lighthouse') {
        try {
            execFileSync('which', ['lighthouse'], { stdio: 'pipe' });
        } catch {
            return null;
        }
    }

    try {
        const output = execFileSync(
            lighthouseBinary,
            [
                targetUrl,
                '--quiet',
                '--chrome-flags=--headless',
                '--only-categories=performance,accessibility',
                '--output=json',
            ],
            { encoding: 'utf8', timeout: 90000, maxBuffer: 10 * 1024 * 1024 },
        );

        const report = JSON.parse(output);
        const categories = report.categories ?? {};

        return {
            performance: Math.round((categories.performance?.score ?? 0) * 100),
            accessibility: Math.round((categories.accessibility?.score ?? 0) * 100),
        };
    } catch {
        return null;
    }
}

async function main() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
        const axe = await runAxe(page);
        const lighthouse = runLighthouse(url);

        const payload = {
            url,
            violations: axe.violations,
            pass_count: axe.passes,
            incomplete_count: axe.incomplete,
            lighthouse,
        };

        process.stdout.write(JSON.stringify(payload));
    } catch (error) {
        process.stdout.write(JSON.stringify({
            url,
            error: error.message,
            violations: [],
            pass_count: 0,
            incomplete_count: 0,
            lighthouse: null,
        }));
        process.exit(1);
    } finally {
        await browser.close();
    }
}

main();
