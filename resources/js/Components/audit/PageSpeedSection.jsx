import { Card } from '@/Components/ui';

const RATING_COLOR = {
    good: 'var(--color-positive)',
    needs_improvement: 'var(--color-sev-serious)',
    poor: 'var(--color-sev-critical)',
};

const METRIC_LABELS = {
    lcp: 'LCP',
    inp: 'INP',
    cls: 'CLS',
    fcp: 'FCP',
};

function MetricCell({ label, metric }) {
    if (!metric) return null;

    return (
        <div className="pagespeed-metric">
            <div className="eyebrow pagespeed-metric-eyebrow">{label}</div>
            <div
                className="tabular pagespeed-metric-value"
                style={{ color: RATING_COLOR[metric.rating] ?? 'inherit' }}
            >
                {metric.display}
            </div>
        </div>
    );
}

export default function PageSpeedSection({ pageSpeed }) {
    if (!pageSpeed?.has_detail) {
        return null;
    }

    const metrics = pageSpeed.metrics ?? {};
    const opportunities = pageSpeed.opportunities ?? [];
    const auditedLabel = pageSpeed.audited_at
        ? new Date(pageSpeed.audited_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })
        : null;

    return (
        <Card title="Page speed" className="audit-section-card">
            {auditedLabel && <div className="micro audit-section-meta">Audited {auditedLabel}</div>}
            {pageSpeed.url && (
                <a
                    href={pageSpeed.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="micro pagespeed-link"
                >
                    {pageSpeed.url.replace(/^https?:\/\//, '')}
                </a>
            )}

            <div className="eyebrow audit-eyebrow-spaced">Core Web Vitals</div>
            <div className="pagespeed-metrics-grid">
                {Object.entries(METRIC_LABELS).map(([key, label]) => (
                    <MetricCell key={key} label={label} metric={metrics[key]} />
                ))}
            </div>
            <p className="micro mb-24">Measured via Google Lighthouse · mobile</p>

            <div className="eyebrow audit-eyebrow-spaced-lg">Opportunities</div>
            {opportunities.length === 0 ? (
                <p className="micro">No significant opportunities detected</p>
            ) : (
                <div className="audit-stack--sm">
                    {opportunities.map((opp) => (
                        <div
                            key={opp.id}
                            className={`pagespeed-opportunity${opp.highlight ? ' pagespeed-opportunity--highlight' : ''}`}
                        >
                            <div className="pagespeed-opportunity-row">
                                <span className="pagespeed-opportunity-title">{opp.title}</span>
                                {opp.savings_display && (
                                    <span className="micro tabular pagespeed-opportunity-savings">{opp.savings_display}</span>
                                )}
                            </div>
                            {opp.description && <p className="micro m-0">{opp.description}</p>}
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}
