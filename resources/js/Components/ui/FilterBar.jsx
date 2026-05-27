export default function FilterBar({ onSubmit, children, className = '' }) {
    return (
        <form onSubmit={onSubmit} className={`filter-bar ${className}`.trim()}>
            {children}
        </form>
    );
}
