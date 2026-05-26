const LABELS = { critical: 'Critical', serious: 'Serious', moderate: 'Moderate' };

export default function SevChip({ level, count, label }) {
    return (
        <span className={`sev-chip ${level}`}>
            <span className="pip" />
            {label || LABELS[level]}
            {count != null ? ` · ${count}` : ''}
        </span>
    );
}
