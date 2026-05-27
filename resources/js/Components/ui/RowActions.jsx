export default function RowActions({ children, className = '' }) {
    return <div className={`row-actions ${className}`.trim()}>{children}</div>;
}
