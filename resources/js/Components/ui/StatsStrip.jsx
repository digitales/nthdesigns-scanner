export default function StatsStrip({ children, className = '' }) {
    return <div className={`stats-strip ${className}`.trim()}>{children}</div>;
}
