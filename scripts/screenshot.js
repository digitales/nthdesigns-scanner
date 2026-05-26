#!/usr/bin/env node

import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';

const url = process.argv[2];
const outputDir = process.argv[3];

if (!url || !outputDir) {
    console.error(JSON.stringify({ error: 'URL and output directory required' }));
    process.exit(1);
}

mkdirSync(outputDir, { recursive: true });

const desktopPath = join(outputDir, 'desktop.png');

async function main() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });

    try {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
        await page.screenshot({ path: desktopPath, fullPage: false });

        writeFileSync(
            join(outputDir, 'meta.json'),
            JSON.stringify({ url, captured_at: new Date().toISOString() }),
        );

        process.stdout.write(JSON.stringify({
            desktop: 'desktop.png',
        }));
    } catch (error) {
        process.stdout.write(JSON.stringify({ error: error.message }));
        process.exit(1);
    } finally {
        await browser.close();
    }
}

main();
