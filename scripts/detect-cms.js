#!/usr/bin/env node

import { chromium } from 'playwright';
import { chromiumLaunchOptions } from './browser.js';
import { detectCms, resolveCmsFromInputs } from './cms-detect.js';
import { detectContactSignals } from './contact-detect.js';
import { navigateForCapture } from './navigate.js';

const url = process.argv[2];

if (!url) {
    console.error(JSON.stringify({ error: 'URL argument required' }));
    process.exit(1);
}

async function main() {
    const browser = await chromium.launch(chromiumLaunchOptions);
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        const response = await navigateForCapture(page, url);
        const cms = await detectCms(page, response);
        const contact = await detectContactSignals(page);
        process.stdout.write(JSON.stringify({ cms, contact }));
    } catch (error) {
        const cms = resolveCmsFromInputs({
            html: '',
            bodyClass: '',
            headers: {},
            finalUrl: url,
            error: error.message,
        });
        process.stdout.write(JSON.stringify({ cms, contact: null }));
        process.exit(1);
    } finally {
        await context.close();
        await browser.close();
    }
}

main();
