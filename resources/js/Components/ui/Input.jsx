import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function Input(
    { className = '', isFocused = false, type = 'text', ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) localRef.current?.focus();
    }, [isFocused]);

    return (
        <input
            ref={localRef}
            type={type}
            className={`input ${className}`.trim()}
            {...props}
        />
    );
});
