export default function ScoreCard({ label, value, highlight = false, delta }) {
    return (
        <div className={`score-card${highlight ? ' highlight' : ''}`}>
            <div className="label">{label}</div>
            <div className="value tabular">{value ?? '—'}</div>
            {delta && <div className="delta">{delta}</div>}
        </div>
    );
}
