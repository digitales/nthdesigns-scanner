import { SevChip } from '@/Components/ui';

export default function ViolationCard({ violation: v, screenshotUrl }) {
    const sevColor = {
        critical: 'var(--color-sev-critical)',
        serious: 'var(--color-sev-serious)',
        moderate: 'var(--color-sev-moderate)',
    }[v.impact] ?? 'var(--color-sev-moderate)';

    return (
        <article className="violation-card">
            <div className="violation-card-media">
                {screenshotUrl ? (
                    <img src={screenshotUrl} alt="" className="violation-card-img" />
                ) : (
                    <div className="violation-card-placeholder" style={{ borderColor: sevColor }} />
                )}
            </div>
            <div>
                <div className="violation-card-header">
                    <SevChip level={v.impact === 'minor' ? 'moderate' : v.impact} />
                    {v.wcag && <span className="micro">{v.wcag}</span>}
                </div>
                <h3 className="violation-card-title">
                    {v.description}
                </h3>
                {v.help && v.help !== v.description && (
                    <p className="violation-card-copy">{v.help}</p>
                )}
                {v.user_impact && (
                    <p className="violation-card-copy violation-card-copy--tight">{v.user_impact}</p>
                )}
                {v.fix_hint && (
                    <p className="violation-card-copy violation-card-copy--fix-lead">
                        <strong className="text-medium">Fix:</strong> {v.fix_hint}
                    </p>
                )}
                {v.fix && (
                    <div className="violation-card-fix">
                        <strong className="text-medium">Fix · </strong>
                        {v.fix}
                    </div>
                )}
            </div>
        </article>
    );
}
