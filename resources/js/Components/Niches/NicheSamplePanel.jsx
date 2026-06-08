import { useCallback, useEffect, useState } from 'react';
import { Button, ScoreBadge } from '@/Components/ui';

const POLL_MS = 2000;
const MAX_POLLS = 30;

export default function NicheSamplePanel({
    scan,
    scanning = false,
    scanPending = false,
    refreshing = false,
    onClose,
    onRefreshScan,
    onRunFullScan,
}) {
    const [state, setState] = useState('loading');
    const [items, setItems] = useState([]);
    const [meta, setMeta] = useState(null);
    const [error, setError] = useState(null);

    const loadSample = useCallback(async (signal) => {
        setState('loading');
        setError(null);

        for (let attempt = 0; attempt < MAX_POLLS; attempt++) {
            if (signal.aborted) {
                return;
            }

            const res = await fetch(`/niches/${scan.id}/sample`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal,
            });

            if (signal.aborted) {
                return;
            }

            const data = await res.json();

            if (res.status === 200 && data.status === 'ready') {
                setItems(data.items ?? []);
                setMeta(data);
                setState(data.items?.length ? 'ready' : 'empty');
                return;
            }

            if (res.status === 422 || data.status === 'failed') {
                setError(data.message ?? 'Sample scan failed.');
                setState('failed');
                return;
            }

            if (res.status !== 202) {
                setError('Could not load sample.');
                setState('failed');
                return;
            }

            await new Promise((resolve) => {
                const t = setTimeout(resolve, POLL_MS);
                signal.addEventListener('abort', () => {
                    clearTimeout(t);
                    resolve();
                });
            });
        }

        setError('Timed out waiting for sample.');
        setState('failed');
    }, [scan.id]);

    useEffect(() => {
        if (scanPending) {
            setState('refreshing');
            return undefined;
        }

        const controller = new AbortController();
        loadSample(controller.signal);
        return () => controller.abort();
    }, [loadSample, scanPending, scan.id]);

    const sampled = meta?.sampled_count ?? scan.sampled_count;
    const total = meta?.result_count ?? scan.result_count;
    const ranAt = meta?.ran_at_human ?? scan.ran_at_human;

    const refreshLabel = refreshing
        ? 'Queuing…'
        : scanPending
          ? 'Scan in progress…'
          : 'Refresh scan';

    return (
        <aside className="niches-panel" aria-label="Sample businesses">
            <div className="niches-panel-header">
                <div>
                    <div className="niches-panel-title">{scan.niche}</div>
                    <div className="micro niches-panel-meta">{scan.city}</div>
                    <div className="niches-panel-score">
                        <ScoreBadge
                            value={scan.opportunity_score != null ? Math.round(scan.opportunity_score) : null}
                            withBar={false}
                        />
                    </div>
                </div>
                <button type="button" className="btn-ghost btn-xs" onClick={onClose} aria-label="Close panel">
                    ×
                </button>
            </div>

            <div className="niches-panel-sub">
                Sampled {sampled ?? '—'} of {total ?? '—'} places · {ranAt}
            </div>

            <div className="niches-panel-body">
                {state === 'refreshing' && (
                    <p className="micro">Refreshing market scan…</p>
                )}

                {state === 'loading' && <p className="micro">Fetching sample…</p>}

                {state === 'failed' && (
                    <div>
                        <p className="micro niches-sample-error">{error}</p>
                        <Button type="button" kind="secondary" onClick={() => loadSample(new AbortController().signal)}>
                            Retry
                        </Button>
                    </div>
                )}

                {state === 'empty' && <p className="micro">No places found in this market.</p>}

                {state === 'ready' &&
                    items.map((item, i) => (
                        <div key={`${item.name}-${i}`} className="niches-sample-item">
                            <div className="niches-sample-name">{item.name}</div>
                            <ScoreBadge value={item.gbp_score} withBar={false} />
                            <div className="niches-sample-chips">
                                {item.no_website && (
                                    <span className="badge badge-muted">No website</span>
                                )}
                                {item.review_count < 20 && (
                                    <span className="badge badge-muted">Low reviews</span>
                                )}
                            </div>
                        </div>
                    ))}
            </div>

            <div className="niches-panel-footer" style={{ display: 'flex', gap: '8px' }}>
                <Button
                    type="button"
                    kind="secondary"
                    disabled={scanPending || refreshing}
                    onClick={() => onRefreshScan?.(scan)}
                >
                    {refreshLabel}
                </Button>
                <Button type="button" disabled={scanning} onClick={() => onRunFullScan(scan)}>
                    {scanning ? 'Queuing…' : 'Run Full Scan'}
                </Button>
            </div>
        </aside>
    );
}
