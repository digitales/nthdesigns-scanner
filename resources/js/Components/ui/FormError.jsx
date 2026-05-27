export default function FormError({ message, className = '' }) {
    if (!message) return null;
    return (
        <p className={`text-xs text-sev-critical mt-1 ${className}`.trim()} role="alert">
            {message}
        </p>
    );
}
