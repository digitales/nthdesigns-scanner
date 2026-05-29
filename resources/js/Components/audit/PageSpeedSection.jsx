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
        <div style={{ padding: '12px 16px', background: 'var(--color-paper-2)', borderRadius: 6 }}>
            <div className="eyebrow" style={{ marginBottom: 6 }}>{label}</div>
            <div className="tabular" style={{ fontSize: 20, fontWeight: 500, color: RATING_COLOR[metric.rating] ?? 'inherit' }}>
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
        <Card title="Page speed" style={{ marginBottom: 24 }}>
            {auditedLabel && <div className="micro" style={{ marginBottom: 6 }}>Audited {auditedLabel}</div>}
            {pageSpeed.url && (
                <a href={pageSpeed.url} target="_blank" rel="noopener noreferrer" className="micro" style={{ display: 'block', marginBottom: 20 }}>
                    {pageSpeed.url.replace(/^https?:\/\//, '')}
                </a>
            )}

            <div className="eyebrow" style={{ marginBottom: 10 }}>Core Web Vitals</div>
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))',
                gap: 12,
                marginBottom: 12,
            }}>
                {Object.entries(METRIC_LABELS).map(([key, label]) => (
                    <MetricCell key={key} label={label} metric={metrics[key]} />
                ))}
            </div>
            <p className="micro" style={{ marginBottom: 24 }}>Measured via Google Lighthouse · mobile</p>

            <div className="eyebrow" style={{ marginBottom: 12 }}>Opportunities</div>
            {opportunities.length === 0 ? (
                <p className="micro">No significant opportunities detected</p>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    {opportunities.map((opp) => (
                        <div
                            key={opp.id}
                            style={{
                                padding: '12px 16px',
                                borderRadius: 6,
                                borderLeft: opp.highlight ? '3px solid var(--color-sev-critical)' : '3px solid transparent',
                                background: opp.highlight ? 'var(--color-sev-critical-soft)' : 'var(--color-paper-2)',
                            }}
                        >
                            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, marginBottom: 4 }}>
                                <span style={{ fontSize: 13, fontWeight: 500 }}>{opp.title}</span>
                                {opp.savings_display && (
                                    <span className="micro tabular" style={{ whiteSpace: 'nowrap' }}>{opp.savings_display}</span>
                                )}
                            </div>
                            {opp.description && <p className="micro" style={{ margin: 0 }}>{opp.description}</p>}
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}
