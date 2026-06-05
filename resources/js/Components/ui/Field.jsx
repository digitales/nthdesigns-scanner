export default function Field({ label, hint, htmlFor, children }) {
    return (
        <div className="field">
            {label && (
                <label className="field-label" htmlFor={htmlFor}>
                    <span>{label}</span>
                    {hint && <span className="field-hint">{hint}</span>}
                </label>
            )}
            {children}
        </div>
    );
}
