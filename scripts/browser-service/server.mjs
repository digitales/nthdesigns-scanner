#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { formatAuditError } from '../audit-error-format.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SCRIPTS_ROOT = path.resolve(__dirname, '..');
const APP_ROOT = path.resolve(SCRIPTS_ROOT, '..');
const PORT = Number(process.env.PORT || 8080);
const TOKEN = process.env.BROWSER_SERVICE_TOKEN || process.env.AUDIT_SERVICE_TOKEN || '';
const MAX_CONCURRENT = Math.max(1, Number(process.env.BROWSER_SERVICE_MAX_CONCURRENT || 2));

let activeJobs = 0;
/** @type {Array<() => void>} */
const jobWaiters = [];

function acquireJobSlot() {
    if (activeJobs < MAX_CONCURRENT) {
        activeJobs++;

        return Promise.resolve();
    }

    return new Promise((resolve) => {
        jobWaiters.push(() => {
            activeJobs++;
            resolve();
        });
    });
}

function releaseJobSlot() {
    activeJobs--;

    const next = jobWaiters.shift();

    if (next) {
        next();
    }
}

async function withJobConcurrency(fn) {
    await acquireJobSlot();

    try {
        return await fn();
    } finally {
        releaseJobSlot();
    }
}

process.on('uncaughtException', (error) => {
    console.error('[browser-service] uncaughtException', error);
    process.exit(1);
});

process.on('unhandledRejection', (reason) => {
    console.error('[browser-service] unhandledRejection', reason);
    process.exit(1);
});

function authorize(req) {
    if (TOKEN === '') {
        return true;
    }

    const header = req.headers.authorization || '';

    return header === `Bearer ${TOKEN}`;
}

function readJson(req) {
    return new Promise((resolve, reject) => {
        const chunks = [];

        req.on('data', (chunk) => chunks.push(chunk));
        req.on('end', () => {
            try {
                const body = Buffer.concat(chunks).toString('utf8');

                resolve(body === '' ? {} : JSON.parse(body));
            } catch (error) {
                reject(error);
            }
        });
        req.on('error', reject);
    });
}

function runNodeScript(scriptName, args) {
    return new Promise((resolve, reject) => {
        const scriptPath = path.join(SCRIPTS_ROOT, scriptName);
        const child = spawn(process.execPath, [scriptPath, ...args], {
            cwd: APP_ROOT,
            env: process.env,
        });

        let stdout = '';
        let stderr = '';

        child.stdout.on('data', (chunk) => {
            stdout += chunk.toString();
        });
        child.stderr.on('data', (chunk) => {
            stderr += chunk.toString();
        });

        child.on('close', (code) => {
            const trimmed = stdout.trim();

            if (code === 0) {
                resolve(trimmed);

                return;
            }

            // audit.js / screenshot.js write structured JSON to stdout before exiting 1.
            if (trimmed !== '') {
                try {
                    const payload = JSON.parse(trimmed);

                    if (payload && typeof payload === 'object' && ('error' in payload || 'url' in payload)) {
                        resolve(trimmed);

                        return;
                    }
                } catch {
                    // fall through
                }
            }

            reject(
                new Error(
                    formatAuditError(
                        trimmed || `${scriptName} exited with code ${code}`,
                        { stderr, stdout: trimmed },
                    ),
                ),
            );
        });
    });
}

function withTempDir(fn) {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'nth-browser-'));

    try {
        return fn(dir);
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
}

async function handleAudit(url) {
    return withJobConcurrency(() => withTempDir(async (tmpDir) => {
        const stdout = await runNodeScript('audit.js', [url, tmpDir]);
        const payload = JSON.parse(stdout);

        if (payload.error) {
            return payload;
        }

        payload.violation_screenshots = (payload.violation_screenshots || []).map((shot) => {
            if (!shot?.file) {
                return shot;
            }

            const filePath = path.join(tmpDir, shot.file);

            if (!fs.existsSync(filePath)) {
                return shot;
            }

            return {
                ...shot,
                content_base64: fs.readFileSync(filePath).toString('base64'),
            };
        });

        return payload;
    }));
}

async function handleDetectCms(url) {
    return withJobConcurrency(async () => {
        const stdout = await runNodeScript('detect-cms.js', [url]);

        return JSON.parse(stdout);
    });
}

async function handleScreenshot(url) {
    return withJobConcurrency(() => withTempDir(async (tmpDir) => {
        const stdout = await runNodeScript('screenshot.js', [url, tmpDir]);
        const payload = JSON.parse(stdout);

        if (payload.error) {
            return payload;
        }

        const filename = payload.desktop || 'desktop.png';
        const filePath = path.join(tmpDir, filename);

        if (!fs.existsSync(filePath)) {
            throw new Error('Screenshot file was not created');
        }

        return {
            desktop: filename,
            content_base64: fs.readFileSync(filePath).toString('base64'),
        };
    }));
}

function sendJson(res, status, body) {
    const data = JSON.stringify(body);
    res.writeHead(status, {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(data),
    });
    res.end(data);
}

function requestPathname(req) {
    try {
        return new URL(req.url ?? '/', 'http://localhost').pathname;
    } catch {
        return req.url ?? '/';
    }
}

const server = createServer(async (req, res) => {
    try {
        const pathname = requestPathname(req);

        // Fly health checks do not send Authorization — must stay public.
        if (req.method === 'GET' && pathname === '/health') {
            sendJson(res, 200, { ok: true });

            return;
        }

        if (!authorize(req)) {
            sendJson(res, 401, { error: 'Unauthorized' });

            return;
        }

        if (req.method !== 'POST') {
            sendJson(res, 405, { error: 'Method not allowed' });

            return;
        }

        const body = await readJson(req);
        const url = body.url;

        if (!url || typeof url !== 'string') {
            sendJson(res, 422, { error: 'url is required' });

            return;
        }

        if (pathname === '/audit') {
            sendJson(res, 200, await handleAudit(url));

            return;
        }

        if (pathname === '/detect-cms') {
            sendJson(res, 200, await handleDetectCms(url));

            return;
        }

        if (pathname === '/screenshot') {
            sendJson(res, 200, await handleScreenshot(url));

            return;
        }

        sendJson(res, 404, { error: 'Not found' });
    } catch (error) {
        sendJson(res, 500, {
            error: formatAuditError(error, { stage: 'browser-service' }),
        });
    }
});

if (!Number.isFinite(PORT) || PORT <= 0) {
    console.error(`[browser-service] invalid PORT: ${process.env.PORT}`);
    process.exit(1);
}

server.on('error', (error) => {
    console.error('[browser-service] server error', error);
    process.exit(1);
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`[browser-service] listening on 0.0.0.0:${PORT} (max concurrent jobs: ${MAX_CONCURRENT})`);
});
