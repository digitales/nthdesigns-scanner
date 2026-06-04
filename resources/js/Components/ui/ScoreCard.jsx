import Status from './Status';

function healthScoreColor(value) {
    if (value < 50) return 'var(--color-sev-critical)';
    if (value < 70) return 'var(--color-sev-serious)';
    return 'var(--color-positive)';
}

export default function ScoreCard({ label, value, highlight = false, unit, delta, healthScore = false, pendingLabel = null }) {
    const hasValue = !pendingLabel && value != null && value !== '';
    const valueStyle = healthScore && hasValue ? { color: healthScoreColor(Number(value)) } : undefined;
    const footer = delta && delta !== unit ? delta : null;

    return (
        <div className={`score-card${highlight ? ' highlight' : ''}${footer ? ' has-footer' : ''}`}>
            <div className="label">{label}</div>
            <div className={`value${pendingLabel ? ' value--status' : ' tabular'}`} style={valueStyle}>
                {pendingLabel ? (
                    <Status kind="pending">{pendingLabel}</Status>
                ) : (
                    <>
                        {hasValue ? value : '—'}
                        {unit && hasValue ? <span className="unit">{unit}</span> : null}
                    </>
                )}
            </div>
            {footer ? <div className="footer">{footer}</div> : null}
        </div>
    );
}
