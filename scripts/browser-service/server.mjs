#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SCRIPTS_ROOT = path.resolve(__dirname, '..');
const APP_ROOT = path.resolve(SCRIPTS_ROOT, '..');
const PORT = Number(process.env.PORT || 8080);
const TOKEN = process.env.BROWSER_SERVICE_TOKEN || process.env.AUDIT_SERVICE_TOKEN || '';

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
            if (code !== 0) {
                reject(new Error(stderr.trim() || stdout.trim() || `${scriptName} exited with code ${code}`));

                return;
            }

            resolve(stdout.trim());
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
    return withTempDir(async (tmpDir) => {
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
    });
}

async function handleScreenshot(url) {
    return withTempDir(async (tmpDir) => {
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
    });
}

function sendJson(res, status, body) {
    const data = JSON.stringify(body);
    res.writeHead(status, {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(data),
    });
    res.end(data);
}

const server = createServer(async (req, res) => {
    try {
        if (!authorize(req)) {
            sendJson(res, 401, { error: 'Unauthorized' });

            return;
        }

        if (req.method === 'GET' && req.url === '/health') {
            sendJson(res, 200, { ok: true });

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

        if (req.url === '/audit') {
            sendJson(res, 200, await handleAudit(url));

            return;
        }

        if (req.url === '/screenshot') {
            sendJson(res, 200, await handleScreenshot(url));

            return;
        }

        sendJson(res, 404, { error: 'Not found' });
    } catch (error) {
        sendJson(res, 500, { error: error.message || 'Internal server error' });
    }
});

server.listen(PORT, () => {
    console.log(`browser-service listening on ${PORT}`);
});
