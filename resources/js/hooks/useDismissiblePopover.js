import { useEffect } from 'react';

export function useDismissiblePopover({ open, onClose, rootRef }) {
    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        const onPointerDown = (event) => {
            if (!rootRef.current?.contains(event.target)) {
                onClose();
            }
        };

        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('pointerdown', onPointerDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('pointerdown', onPointerDown);
        };
    }, [open, onClose, rootRef]);
}
