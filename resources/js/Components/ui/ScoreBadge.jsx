import { scoreBand } from './scoreBand';

export default function ScoreBadge({ value, withBar = true, weakPip = false }) {
    if (value == null || value === '—') {
        return (
            <span className="score-badge low" style={{ opacity: 0.5 }}>
                <span className="num">—</span>
            </span>
        );
    }

    const band = scoreBand(value);
    const barPct = Math.min(100, Math.max(8, value));

    const badge = (
        <span className={`score-badge ${band}`}>
            {withBar && (
                <span className="bar">
                    <span
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'currentColor',
                            opacity: 0.7,
                            right: `${100 - barPct}%`,
                        }}
                    />
                </span>
            )}
            <span className="num">{value}</span>
        </span>
    );

    if (weakPip) {
        return (
            <span className="score-with-pip">
                {badge}
                <span
                    className="weak-pip"
                    title="Below 30 — meaningful weakness in this column"
                />
            </span>
        );
    }

    return badge;
}
