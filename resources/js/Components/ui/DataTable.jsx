export default function DataTable({ className = '', style, children }) {
    return (
        <div
            className={className}
            style={{ border: '1px solid var(--color-line)', borderRadius: 6, overflow: 'hidden', ...style }}
        >
            <table className="ptable">{children}</table>
        </div>
    );
}
