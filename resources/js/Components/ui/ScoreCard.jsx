function healthScoreColor(value) {
    if (value < 50) return 'var(--color-sev-critical)';
    if (value < 70) return 'var(--color-sev-serious)';
    return 'var(--color-positive)';
}

export default function ScoreCard({ label, value, highlight = false, delta, healthScore = false }) {
    const hasValue = value != null && value !== '';
    const valueStyle = healthScore && hasValue ? { color: healthScoreColor(Number(value)) } : undefined;

    return (
        <div className={`score-card${highlight ? ' highlight' : ''}`}>
            <div className="label">{label}</div>
            <div className="value tabular" style={valueStyle}>{hasValue ? value : '—'}</div>
            {delta && <div className="delta">{delta}</div>}
        </div>
    );
}
