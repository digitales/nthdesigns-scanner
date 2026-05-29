const METRIC_AUDITS = {
    lcp: 'largest-contentful-paint',
    inp: ['interaction-to-next-paint', 'total-blocking-time'],
    cls: 'cumulative-layout-shift',
    fcp: 'first-contentful-paint',
};

export function ratingFromScore(score) {
    if (score == null) return null;
    if (score >= 0.9) return 'good';
    if (score >= 0.5) return 'needs_improvement';
    return 'poor';
}

function firstAudit(audits, ids) {
    const keys = Array.isArray(ids) ? ids : [ids];
    for (const id of keys) {
        if (audits[id]) return audits[id];
    }
    return null;
}

function shapeMetric(audit) {
    if (!audit) return null;
    return {
        value_ms: audit.numericValue ?? null,
        display: audit.displayValue ?? String(audit.numericValue ?? ''),
        rating: ratingFromScore(audit.score),
    };
}

export function extractMetrics(audits = {}) {
    return {
        lcp: shapeMetric(audits[METRIC_AUDITS.lcp]),
        inp: shapeMetric(firstAudit(audits, METRIC_AUDITS.inp)),
        cls: shapeMetric(audits[METRIC_AUDITS.cls]),
        fcp: shapeMetric(audits[METRIC_AUDITS.fcp]),
    };
}

export function extractOpportunities(audits = {}, limit = 8) {
    const candidates = Object.values(audits)
        .filter((audit) => {
            if (audit.score == null || audit.score >= 0.9) return false;
            const type = audit.details?.type;
            const mode = audit.scoreDisplayMode;
            return type === 'opportunity' || mode === 'metricSavings';
        })
        .map((audit) => ({
            id: audit.id ?? audit.title,
            title: audit.title ?? audit.id,
            description: audit.description ?? '',
            savings_ms: audit.details?.overallSavingsMs ?? 0,
            savings_display: audit.displayValue ?? '',
        }))
        .sort((a, b) => {
            if (b.savings_ms !== a.savings_ms) return b.savings_ms - a.savings_ms;
            return a.title.localeCompare(b.title);
        });

    return candidates.slice(0, limit);
}

export function buildLighthousePayload(report) {
    const categories = report.categories ?? {};
    const audits = report.audits ?? {};

    return {
        performance: Math.round((categories.performance?.score ?? 0) * 100),
        accessibility: Math.round((categories.accessibility?.score ?? 0) * 100),
        seo: Math.round((categories.seo?.score ?? 0) * 100),
        metrics: extractMetrics(audits),
        opportunities: extractOpportunities(audits, 8),
    };
}
