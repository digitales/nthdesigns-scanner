import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button, Field, Select, Status } from '@/Components/ui';

function reasonLabel(reason) {
    if (reason === 'manual') {
        return 'Ignored manually';
    }
    if (reason === 'low_results') {
        return 'Low Places results';
    }
    return null;
}

export default function ManageNichesPanel({ catalog, ignoredCount, onClose }) {
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState('all');
    const [busy, setBusy] = useState(null);

    const rows = useMemo(() => {
        const q = query.trim().toLowerCase();

        return catalog.filter((item) => {
            if (filter === 'ignored' && !item.ignored) {
                return false;
            }
            if (filter === 'active' && item.ignored) {
                return false;
            }
            if (filter === 'low_results' && !item.is_low_result) {
                return false;
            }
            if (q === '') {
                return true;
            }

            return item.label.toLowerCase().includes(q) || item.query.toLowerCase().includes(q);
        });
    }, [catalog, filter, query]);

    const toggleIgnore = (item) => {
        setBusy(item.label);

        if (item.ignored) {
            router.post(
                '/niches/ignore/remove',
                { niche: item.label },
                {
                    preserveScroll: true,
                    onFinish: () => setBusy(null),
                },
            );

            return;
        }

        router.post(
            '/niches/ignore',
            { niche: item.label },
            {
                preserveScroll: true,
                onFinish: () => setBusy(null),
            },
        );
    };

    return (
        <aside className="niches-panel niches-manage-panel" aria-label="Manage niches">
            <div className="niches-panel-header">
                <div>
                    <div className="niches-panel-title">Manage niches</div>
                    <div className="niches-panel-sub">
                        {ignoredCount} excluded from batch scans · auto-excludes when max results &lt; 3
                    </div>
                </div>
                <button type="button" className="btn-ghost btn-xs" onClick={onClose} aria-label="Close">
                    ×
                </button>
            </div>

            <div className="niches-manage-toolbar">
                <Field label="Search">
                    <input
                        type="search"
                        className="input"
                        placeholder="Filter niches…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                </Field>
                <Field label="Show">
                    <Select value={filter} onChange={(e) => setFilter(e.target.value)}>
                        <option value="all">All</option>
                        <option value="active">Included in scans</option>
                        <option value="ignored">Excluded</option>
                        <option value="low_results">Low result count</option>
                    </Select>
                </Field>
            </div>

            <div className="niches-panel-body niches-manage-list">
                {rows.length === 0 ? (
                    <p className="micro">No niches match this filter.</p>
                ) : (
                    rows.map((item) => (
                        <div key={item.label} className="niches-manage-item">
                            <div className="niches-manage-main">
                                <div className="niches-sample-name">{item.label}</div>
                                <div className="micro">{item.query}</div>
                                <div className="niches-manage-meta">
                                    <span className="micro tabular">
                                        Max results {item.max_result_count ?? '—'}
                                    </span>
                                    {item.ignored && item.ignore_reason && (
                                        <Status kind="pending">{reasonLabel(item.ignore_reason)}</Status>
                                    )}
                                    {!item.ignored && item.is_low_result && (
                                        <Status kind="pending">Below threshold</Status>
                                    )}
                                </div>
                            </div>
                            <Button
                                type="button"
                                className="btn-ghost btn-xs"
                                disabled={busy === item.label}
                                onClick={() => toggleIgnore(item)}
                            >
                                {item.ignored ? 'Include' : 'Ignore'}
                            </Button>
                        </div>
                    ))
                )}
            </div>

            <div className="niches-panel-footer micro">
                Ignored niches are skipped by Run Now and the weekly schedule. Run{' '}
                <code>php artisan niches:sync-exclusions</code> to refresh auto-exclusions from scan data.
            </div>
        </aside>
    );
}
