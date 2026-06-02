export default function DataTable({ className = '', tableClassName = '', style, children }) {
    const tableClasses = ['ptable', tableClassName].filter(Boolean).join(' ');

    return (
        <div
            className={className}
            style={{ border: '1px solid var(--color-line)', borderRadius: 6, overflow: 'hidden', ...style }}
        >
            <table className={tableClasses}>{children}</table>
        </div>
    );
}
