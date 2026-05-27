import { Card, SevChip } from '@/Components/ui';
import ViolationCard from '@/Components/audit/ViolationCard';
import LighthouseDial from '@/Components/audit/LighthouseDial';
import ViolationsTable from '@/Components/audit/ViolationsTable';

export default function SiteAuditSection({ audit }) {
    if (!audit) {
        return null;
    }

    const lh = audit.lighthouse ?? {};
    const hasLighthouse = lh.performance != null || lh.accessibility != null || lh.seo != null || lh.best_practices != null;
    const summary = audit.summary ?? {};
    const auditedLabel = audit.audited_at
        ? new Date(audit.audited_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })
        : null;
    const showScannerScore = audit.performance_score != null
        && (lh.performance == null || lh.performance !== audit.performance_score);

    return (
        <Card title="Site audit" style={{ marginBottom: 24 }}>
            <div style={{ marginBottom: 20 }}>
                {auditedLabel && <div className="micro" style={{ marginBottom: 6 }}>Audited {auditedLabel}</div>}
                {audit.url && (
                    <a href={audit.url} target="_blank" rel="noopener noreferrer" className="micro">
                        {audit.url.replace(/^https?:\/\//, '')}
                    </a>
                )}
            </div>

            <div style={{ marginBottom: 24 }}>
                <div className="eyebrow" style={{ marginBottom: 10 }}>Summary</div>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 8 }}>
                    {summary.critical > 0 && <SevChip level="critical" count={summary.critical} />}
                    {summary.serious > 0 && <SevChip level="serious" count={summary.serious} />}
                    {summary.moderate > 0 && <SevChip level="moderate" count={summary.moderate} />}
                    {summary.minor > 0 && <SevChip level="minor" count={summary.minor} />}
                    {summary.total === 0 && <span className="micro">No issues detected</span>}
                </div>
                <p className="micro">
                    {audit.pass_count} passes · {audit.incomplete_count} incomplete checks
                </p>
            </div>

            {hasLighthouse && (
                <div style={{ marginBottom: 28 }}>
                    <div className="eyebrow" style={{ marginBottom: 16 }}>Lighthouse</div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(100px, 1fr))', gap: 16 }}>
                        {lh.performance != null && <LighthouseDial label="Performance" score={lh.performance} />}
                        {lh.accessibility != null && <LighthouseDial label="Accessibility" score={lh.accessibility} />}
                        {lh.seo != null && <LighthouseDial label="SEO" score={lh.seo} />}
                        {lh.best_practices != null && <LighthouseDial label="Best practices" score={lh.best_practices} />}
                    </div>
                    {showScannerScore && (
                        <p className="micro" style={{ marginTop: 12 }}>
                            Scanner score: <span className="num">{audit.performance_score}</span>
                        </p>
                    )}
                </div>
            )}

            {(audit.top_violations ?? []).length > 0 && (
                <div style={{ marginBottom: 28 }}>
                    <div className="eyebrow" style={{ marginBottom: 16 }}>Priority issues</div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                        {audit.top_violations.map((v) => (
                            <ViolationCard key={v.id} violation={v} screenshotUrl={v.screenshot_url} />
                        ))}
                    </div>
                </div>
            )}

            <div>
                <div className="eyebrow" style={{ marginBottom: 12 }}>All violations</div>
                <ViolationsTable violations={audit.all_violations ?? []} />
            </div>
        </Card>
    );
}
