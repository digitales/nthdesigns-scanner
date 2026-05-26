export default function Badge({ children, dot = true, className = '' }) {
    return (
        <span className={`badge ${className}`.trim()}>
            {dot && <span className="dot" />}
            {children}
        </span>
    );
}
