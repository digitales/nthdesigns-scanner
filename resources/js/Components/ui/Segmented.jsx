export default function Segmented({ value, onChange, options }) {
    return (
        <div className="segmented">
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    className={o.value === value ? 'active' : ''}
                    onClick={() => onChange(o.value)}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}
