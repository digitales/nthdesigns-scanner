import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function Textarea(
    { className = '', isFocused = false, rows = 3, ...props },
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
        <textarea
            ref={localRef}
            rows={rows}
            className={`input textarea ${className}`.trim()}
            {...props}
        />
    );
});
