import { Card } from '@/Components/ui';

const confidenceLabels = {
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};

export default function TechnologySection({ cms }) {
    if (!cms) {
        return null;
    }

    return (
        <Card>
            <div className="eyebrow" style={{ marginBottom: 12 }}>Technology</div>
            {cms.pending ? (
                <p className="micro">Detecting platform…</p>
            ) : (
                <>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
                        <span style={{ fontSize: 15, fontWeight: 500 }}>{cms.label}</span>
                        {cms.confidence && (
                            <span className={`badge cms-confidence cms-confidence--${cms.confidence}`}>
                                {confidenceLabels[cms.confidence] ?? cms.confidence}
                            </span>
                        )}
                    </div>
                    {(cms.signals?.length ?? 0) > 0 && (
                        <details>
                            <summary className="micro" style={{ cursor: 'pointer', marginBottom: 8 }}>
                                Detection signals
                            </summary>
                            <ul className="cms-signals-list">
                                {cms.signals
                                    .filter((s) => s.matched)
                                    .map((s) => (
                                        <li key={s.id} className="micro">
                                            <code>{s.id}</code>
                                            {s.detail ? ` — ${s.detail}` : ''}
                                        </li>
                                    ))}
                            </ul>
                        </details>
                    )}
                </>
            )}
        </Card>
    );
}
