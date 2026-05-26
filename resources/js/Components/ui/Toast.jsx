import { useEffect } from 'react';

export default function Toast({ children, onClose, duration = 3000 }) {
    useEffect(() => {
        const t = setTimeout(() => onClose?.(), duration);
        return () => clearTimeout(t);
    }, [onClose, duration]);

    return (
        <div className="toast">
            <span className="check">
                <svg viewBox="0 0 12 12">
                    <path d="M3 6.5l2 2 4-5" />
                </svg>
            </span>
            {children}
        </div>
    );
}
