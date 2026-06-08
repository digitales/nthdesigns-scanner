import { useEffect } from 'react';

const POLL_MS = 4000;
const MAX_POLL_MS = 5 * 60 * 1000;

/**
 * Poll niche+city scan status while refreshes are in flight.
 *
 * @param {string[]} pendingKeys - "niche|city" combo keys still pending
 * @param {Array<{ id: number, niche: string, city: string }>} rows
 * @param {(comboKey: string, payload: object) => void} onUpdate
 */
export function useNicheScanStatusPoll(pendingKeys, rows, onUpdate) {
    const pendingKey = pendingKeys.join(',');

    useEffect(() => {
        if (pendingKeys.length === 0) {
            return undefined;
        }

        const startedAt = Date.now();
        let cancelled = false;

        const poll = async () => {
            if (cancelled) {
                return;
            }

            if (Date.now() - startedAt > MAX_POLL_MS) {
                return;
            }

            for (const comboKey of pendingKeys) {
                const row = rows.find((r) => `${r.niche}|${r.city}` === comboKey);
                if (!row) {
                    continue;
                }

                try {
                    const res = await fetch(`/niches/${row.id}/status`, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!res.ok || cancelled) {
                        continue;
                    }

                    const data = await res.json();
                    onUpdate(comboKey, data);
                } catch {
                    // Retry on next interval.
                }
            }
        };

        poll();
        const timer = setInterval(poll, POLL_MS);

        return () => {
            cancelled = true;
            clearInterval(timer);
        };
    }, [pendingKey, rows, onUpdate]);
}

export function nicheCityKey(row) {
    return `${row.niche}|${row.city}`;
}
