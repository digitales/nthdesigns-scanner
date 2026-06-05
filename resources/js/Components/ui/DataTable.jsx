export default function DataTable({ className = '', tableClassName = '', style, children }) {
    const tableClasses = ['ptable', tableClassName].filter(Boolean).join(' ');
    const wrapperClasses = ['data-table', className].filter(Boolean).join(' ');

    return (
        <div className={wrapperClasses} style={style}>
            <table className={tableClasses}>{children}</table>
        </div>
    );
}
