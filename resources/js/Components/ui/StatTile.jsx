export default function StatTile({ label, value, warm = false, className = '' }) {
    return (
        <div className={`stat-tile${warm ? ' warm' : ''} ${className}`.trim()}>
            <div className="stat-label">{label}</div>
            <div className="stat-value tabular">{value}</div>
        </div>
    );
}
