import { Card, SevChip } from '@/Components/ui';
import ViolationCard from '@/Components/audit/ViolationCard';
import ViolationsTable from '@/Components/audit/ViolationsTable';

export default function SiteAuditSection({ audit }) {
    if (!audit) {
        return null;
    }

    const summary = audit.summary ?? {};
    const auditedLabel = audit.audited_at
        ? new Date(audit.audited_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })
        : null;

    return (
        <Card title="Site audit" className="audit-section-card">
            <div className="audit-section-intro">
                {auditedLabel && <div className="micro audit-section-meta">Audited {auditedLabel}</div>}
                {audit.url && (
                    <a href={audit.url} target="_blank" rel="noopener noreferrer" className="micro">
                        {audit.url.replace(/^https?:\/\//, '')}
                    </a>
                )}
            </div>

            <div className="audit-block">
                <div className="eyebrow audit-eyebrow-spaced">Summary</div>
                <div className="audit-chips">
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

            {(audit.top_violations ?? []).length > 0 && (
                <div className="audit-block--lg">
                    <div className="eyebrow audit-eyebrow-spaced-xl">Priority issues</div>
                    <div className="audit-stack">
                        {audit.top_violations.map((v) => (
                            <ViolationCard key={v.id} violation={v} screenshotUrl={v.screenshot_url} />
                        ))}
                    </div>
                </div>
            )}

            <div>
                <div className="eyebrow audit-eyebrow-spaced-lg">All violations</div>
                <ViolationsTable violations={audit.all_violations ?? []} />
            </div>
        </Card>
    );
}
