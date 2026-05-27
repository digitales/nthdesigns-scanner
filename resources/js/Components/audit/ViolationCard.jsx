import { SevChip } from '@/Components/ui';

export default function ViolationCard({ violation: v, screenshotUrl }) {
    const sevColor = {
        critical: 'var(--color-sev-critical)',
        serious: 'var(--color-sev-serious)',
        moderate: 'var(--color-sev-moderate)',
    }[v.impact] ?? 'var(--color-sev-moderate)';

    return (
        <article style={{ display: 'grid', gridTemplateColumns: '240px 1fr', gap: 28 }}>
            <div style={{
                background: 'var(--color-stone-100)',
                borderRadius: 4,
                border: '1px solid var(--color-line)',
                height: 160,
                position: 'relative',
                overflow: 'hidden',
            }}>
                {screenshotUrl ? (
                    <img src={screenshotUrl} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                ) : (
                    <div style={{
                        position: 'absolute',
                        top: '30%',
                        left: '20%',
                        width: '60%',
                        height: '40%',
                        border: `2px solid ${sevColor}`,
                        borderRadius: 2,
                    }} />
                )}
            </div>
            <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                    <SevChip level={v.impact === 'minor' ? 'moderate' : v.impact} />
                    {v.wcag && <span className="micro">{v.wcag}</span>}
                </div>
                <h3 style={{ fontFamily: 'var(--font-serif)', fontSize: 20, fontWeight: 500, margin: '0 0 10px' }}>
                    {v.description}
                </h3>
                {v.help && v.help !== v.description && (
                    <p style={{ fontSize: 14, color: 'var(--color-stone-600)', lineHeight: 1.6, margin: '0 0 14px' }}>{v.help}</p>
                )}
                {v.user_impact && (
                    <p style={{ fontSize: 14, color: 'var(--color-stone-600)', lineHeight: 1.6, margin: '8px 0 0' }}>{v.user_impact}</p>
                )}
                {v.fix_hint && (
                    <p style={{ fontSize: 14, color: 'var(--color-accent-deep)', lineHeight: 1.6, margin: '4px 0 0' }}>
                        <strong style={{ fontWeight: 500 }}>Fix:</strong> {v.fix_hint}
                    </p>
                )}
                {v.fix && (
                    <div style={{
                        borderLeft: '3px solid var(--color-accent)',
                        paddingLeft: 14,
                        fontSize: 14,
                        color: 'var(--color-stone-700)',
                        lineHeight: 1.55,
                    }}>
                        <strong style={{ fontWeight: 500, color: 'var(--color-ink)' }}>Fix · </strong>
                        {v.fix}
                    </div>
                )}
            </div>
        </article>
    );
}
