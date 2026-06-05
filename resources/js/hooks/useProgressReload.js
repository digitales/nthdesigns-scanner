import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Poll Inertia partial reloads while a long-running backend job is in flight.
 *
 * @param {boolean} active
 * @param {string[]} only
 * @param {number} intervalMs
 */
export function useProgressReload(active, only, intervalMs = 4000) {
    const onlyKey = only.join(',');

    useEffect(() => {
        if (!active) {
            return undefined;
        }

        const timer = setInterval(() => {
            router.reload({ only });
        }, intervalMs);

        return () => clearInterval(timer);
    }, [active, onlyKey, intervalMs]);
}
