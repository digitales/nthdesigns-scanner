import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Button,
    EmptyState,
    Field,
    Icons,
    PageHeader,
    Segmented,
    Status,
    Toast,
} from '@/Components/ui';

export default function ReportsIndex({ reports, filters, stats }) {
    const [toast, setToast] = useState(null);
    const [viewFilter, setViewFilter] = useState(() => {
        if (filters.warm) return 'warm';
        if (filters.viewed === '1') return 'viewed';
        if (filters.viewed === '0') return 'unviewed';
        return 'all';
    });

    const applyFilters = (overrides = {}) => {
        const params = { niche: filters.niche ?? '', ...overrides };
        if (params.viewFilter) {
            const vf = params.viewFilter;
            delete params.viewFilter;
            if (vf === 'viewed') params.viewed = '1';
            else if (vf === 'unviewed') params.viewed = '0';
            else if (vf === 'warm') params.warm = '1';
        }
        Object.keys(params).forEach((k) => {
            if (params[k] === '' || params[k] == null) delete params[k];
        });
        router.get('/reports', params, { preserveState: true });
    };

    const copyUrl = (report) => {
        navigator.clipboard.writeText(report.public_url);
        setToast(`/r/${report.token} copied`);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Reports" />

            <main className="page page-wide">
                <PageHeader
                    eyebrow="F · Reports dashboard"
                    title="Public report engagement."
                    sub="Track views, warm signals, and copy shareable URLs for outreach."
                />

                <div className="stats-strip">
                    <div className="stat-tile">
                        <div className="stat-label">Reports generated</div>
                        <div className="stat-value tabular">{stats.total_reports}</div>
                    </div>
                    <div className="stat-tile">
                        <div className="stat-label">Total views</div>
                        <div className="stat-value tabular">{stats.total_views}</div>
                    </div>
                    <div className="stat-tile warm">
                        <div className="stat-label">Warm (7d)</div>
                        <div className="stat-value tabular">{stats.warm_7d}</div>
                    </div>
                    <div className="stat-tile">
                        <div className="stat-label">Avg views per report</div>
                        <div className="stat-value tabular">{stats.avg_views}</div>
                    </div>
                </div>

                <div className="filter-bar">
                    <Field label="Filter">
                        <Segmented
                            value={viewFilter}
                            onChange={(v) => {
                                setViewFilter(v);
                                applyFilters({ viewFilter: v, niche: filters.niche ?? '' });
                            }}
                            options={[
                                { value: 'all', label: 'All' },
                                { value: 'viewed', label: 'Viewed' },
                                { value: 'unviewed', label: 'Unviewed' },
                                { value: 'warm', label: 'Warm · 7d' },
                            ]}
                        />
                    </Field>
                    <Field label="Niche">
                        <input
                            type="text"
                            className="input"
                            defaultValue={filters.niche ?? ''}
                            onBlur={(e) => applyFilters({ niche: e.target.value, viewFilter })}
                        />
                    </Field>
                    <div className="filter-action">
                        <Link href="/reports" className="micro">Reset</Link>
                    </div>
                </div>

                {reports.length === 0 ? (
                    <EmptyState icon={Icons.Eye} title="No reports yet." sub="Generate reports from prospect detail pages." />
                ) : (
                    <div style={{ border: '1px solid var(--color-line)', borderRadius: 6, overflow: 'hidden' }}>
                        <table className="ptable">
                            <thead>
                                <tr>
                                    <th>Business</th>
                                    <th>Public URL</th>
                                    <th>Created</th>
                                    <th>Views</th>
                                    <th>Last viewed</th>
                                    <th>Viewer</th>
                                    <th style={{ textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {reports.map((r) => (
                                    <tr key={r.id} onClick={() => router.visit(`/prospects/${r.prospect_id}`)}>
                                        <td className="biz">
                                            {r.business_name}
                                            {r.is_engaged_badge && (
                                                <div style={{ marginTop: 4 }}>
                                                    <Status kind="warm">Warm</Status>
                                                </div>
                                            )}
                                        </td>
                                        <td onClick={(e) => e.stopPropagation()}>
                                            <code className="micro">/r/{r.token}</code>
                                        </td>
                                        <td className="micro">{r.created_at}</td>
                                        <td className="tabular" style={{ color: r.view_count === 0 ? 'var(--color-stone-400)' : undefined }}>
                                            {r.view_count}
                                            {r.is_engaged_badge && r.view_count > 0 && (
                                                <span style={{ color: 'var(--color-accent-deep)', fontSize: 10, marginLeft: 6 }}>● new</span>
                                            )}
                                        </td>
                                        <td className="micro">
                                            {r.viewed_at ? new Date(r.viewed_at).toLocaleDateString() : '—'}
                                        </td>
                                        <td className="micro">{r.viewer_ip ?? '—'}</td>
                                        <td onClick={(e) => e.stopPropagation()} style={{ textAlign: 'right' }}>
                                            <div className="row-actions">
                                                <button type="button" className="btn-ghost btn-xs" onClick={() => copyUrl(r)}>Copy URL</button>
                                                <a href={r.public_url} target="_blank" rel="noopener noreferrer" className="btn-ghost btn-xs">Open</a>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {toast && <Toast duration={1800} onClose={() => setToast(null)}>{toast}</Toast>}
            </main>
        </AuthenticatedLayout>
    );
}
