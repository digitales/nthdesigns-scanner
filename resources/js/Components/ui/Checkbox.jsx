import { useEffect, useRef } from 'react';

export default function Checkbox({ checked, onChange, indeterminate, ...rest }) {
    const ref = useRef(null);

    useEffect(() => {
        if (ref.current) {
            ref.current.indeterminate = !!indeterminate;
        }
    }, [indeterminate]);

    return (
        <input
            ref={ref}
            type="checkbox"
            className="checkbox"
            checked={!!checked}
            onChange={(e) => onChange?.(e.target.checked)}
            onClick={(e) => e.stopPropagation()}
            {...rest}
        />
    );
}
