export default function LighthouseDial({ label, score, caption }) {
    const color = score < 50 ? 'var(--color-sev-critical)' : score < 70 ? 'var(--color-sev-serious)' : 'var(--color-positive)';
    const circumference = 2 * Math.PI * 45;
    const offset = circumference - (score / 100) * circumference;

    return (
        <div className="lighthouse-dial">
            <svg width="120" height="120" viewBox="0 0 120 120" className="lighthouse-dial-svg">
                <circle cx="60" cy="60" r="45" fill="none" stroke="var(--color-stone-200)" strokeWidth="8" />
                <circle
                    cx="60"
                    cy="60"
                    r="45"
                    fill="none"
                    stroke={color}
                    strokeWidth="8"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    strokeLinecap="round"
                    transform="rotate(-90 60 60)"
                />
                <text x="60" y="58" textAnchor="middle" fontFamily="var(--font-serif)" fontSize="28" fill={color}>{score}</text>
                <text x="60" y="78" textAnchor="middle" fontFamily="var(--font-mono)" fontSize="9" fill="var(--color-stone-500)">{label}</text>
            </svg>
            {caption && (
                <p className="lighthouse-dial-caption">{caption}</p>
            )}
        </div>
    );
}
