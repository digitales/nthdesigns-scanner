/**
 * Build a single diagnostic string for audit failures (Playwright, script, HTTP).
 *
 * @param {unknown} error
 * @param {{ stderr?: string, stdout?: string, stage?: string }} [context]
 * @returns {string}
 */
export function formatAuditError(error, context = {}) {
    const { stderr = '', stdout = '', stage = null } = context;
    const parts = [];

    if (stage) {
        parts.push(`[${stage}]`);
    }

    const message = error instanceof Error ? error.message : String(error ?? '');

    if (message.trim() !== '') {
        parts.push(message.trim());
    }

    const stderrTrim = stderr.trim();
    const stdoutTrim = stdout.trim();

    if (stderrTrim !== '' && !message.includes(stderrTrim)) {
        parts.push(stderrTrim);
    }

    if (stdoutTrim !== '' && stdoutTrim !== stderrTrim && !message.includes(stdoutTrim)) {
        parts.push(stdoutTrim);
    }

    const combined = parts.join('\n\n') || 'Audit failed';

    return combined.length > 32_768 ? combined.slice(0, 32_768) : combined;
}
