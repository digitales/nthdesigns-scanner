export default function Field({ label, hint, children }) {
    return (
        <div className="field">
            {label && (
                <label className="field-label">
                    <span>{label}</span>
                    {hint && <span className="field-hint">{hint}</span>}
                </label>
            )}
            {children}
        </div>
    );
}
