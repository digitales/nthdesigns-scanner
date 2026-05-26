import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    AnglePill,
    Button,
    EmptyState,
    Field,
    Icons,
    PageHeader,
    ScoreBadge,
    Segmented,
    Status,
    Toast,
} from '@/Components/ui';
import { normalizeAngle } from '@/Components/ui/scoreBand';

export default function SavedIndex({ prospects, warmLeads, filters, meta }) {
    const [toast, setToast] = useState(null);

    const submitFilters = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        const params = Object.fromEntries(form.entries());
        if (!params.warm) delete params.warm;
        router.get('/saved', params, { preserveState: true });
    };

    const addToOutreach = (prospectId) => {
        router.post('/outreach/selections', { prospect_ids: [prospectId] });
    };

    const copyUrl = (url) => {
        navigator.clipboard.writeText(url);
        setToast(`${url.replace(/^https?:\/\/[^/]+/, '')} copied`);
    };

    const exportCsv = () => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/exports';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrf) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = csrf;
            form.appendChild(input);
        }
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== '' && value != null) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
        });
        document.body.appendChild(form);
        form.submit();
        form.remove();
    };

    return (
        <AuthenticatedLayout>
            <Head title="Saved prospects" />

            <main className="page page-wide">
                <PageHeader
                    eyebrow="E · Saved prospects"
                    title={`${meta.total} prospect${meta.total !== 1 ? 's' : ''} across searches.`}
                    sub="Filter by niche, score, or warm-lead status. Export matches your current filters as CSV."
                    actions={
                        <Button kind="secondary" size="sm" icon={Icons.Download} onClick={exportCsv}>
                            Export CSV
                        </Button>
                    }
                />

                {warmLeads.length > 0 && !filters.warm && (
                    <section className="warm-panel">
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                            <div className="card-title" style={{ margin: 0 }}>Warm leads</div>
                            <Link href="/saved?warm=1" className="micro" style={{ color: 'var(--color-accent-deep)' }}>
                                Filter to warm →
                            </Link>
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12 }}>
                            {warmLeads.slice(0, 3).map((p) => (
                                <Link
                                    key={p.id}
                                    href={`/prospects/${p.id}`}
                                    className="card card-pad"
                                    style={{ textDecoration: 'none', color: 'inherit', padding: '14px 16px' }}
                                >
                                    <div style={{ fontWeight: 500, fontSize: 13 }}>{p.business_name}</div>
                                    <div className="micro" style={{ marginTop: 4 }}>{p.niche} · {p.city}</div>
                                    <div style={{ marginTop: 10 }}>
                                        <ScoreBadge value={p.combined_score} withBar={false} />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                <form onSubmit={submitFilters} className="filter-bar">
                    <Field label="From">
                        <input type="date" name="from" className="input" defaultValue={filters.from ?? ''} />
                    </Field>
                    <Field label="To">
                        <input type="date" name="to" className="input" defaultValue={filters.to ?? ''} />
                    </Field>
                    <Field label="Niche">
                        <input type="text" name="niche" className="input" defaultValue={filters.niche ?? ''} />
                    </Field>
                    <Field label="City">
                        <input type="text" name="city" className="input" defaultValue={filters.city ?? ''} />
                    </Field>
                    <Field label="Scan type">
                        <select name="scan_type" className="select" defaultValue={filters.scan_type ?? ''}>
                            <option value="">Any</option>
                            <option value="combined">Combined</option>
                            <option value="gbp_only">GBP only</option>
                            <option value="accessibility_only">Accessibility only</option>
                        </select>
                    </Field>
                    <Field label="Angle">
                        <select name="dominant_angle" className="select" defaultValue={filters.dominant_angle ?? ''}>
                            <option value="">Any</option>
                            <option value="gbp">GBP</option>
                            <option value="accessibility">Accessibility</option>
                            <option value="both">Both</option>
                        </select>
                    </Field>
                    <Field label="Min score">
                        <input type="number" name="min_score" min="0" max="100" className="input" defaultValue={filters.min_score ?? ''} />
                    </Field>
                    <Field label="Warm">
                        <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, paddingTop: 8 }}>
                            <input type="checkbox" name="warm" value="1" defaultChecked={!!filters.warm} />
                            Warm only
                        </label>
                    </Field>
                    <div className="filter-action">
                        <Button kind="primary" size="sm" type="submit">Apply</Button>
                        <Link href="/saved" className="micro">Reset</Link>
                    </div>
                </form>

                {prospects.length === 0 ? (
                    <EmptyState
                        icon={Icons.Search}
                        title="No prospects match these filters."
                        sub="Try widening your date range or lowering the minimum score."
                    />
                ) : (
                    <div style={{ border: '1px solid var(--color-line)', borderRadius: 6, overflow: 'hidden' }}>
                        <table className="ptable">
                            <thead>
                                <tr>
                                    <th>Business</th>
                                    <th>Niche / City</th>
                                    <th>Combined</th>
                                    <th>GBP</th>
                                    <th>A11y</th>
                                    <th>Angle</th>
                                    <th>Outreach history</th>
                                    <th style={{ textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {prospects.map((p) => (
                                    <tr key={p.id} className={p.is_warm ? 'warm' : ''} onClick={() => router.visit(`/prospects/${p.id}`)}>
                                        <td className="biz">{p.business_name}</td>
                                        <td className="micro">{p.niche} · {p.city}</td>
                                        <td><ScoreBadge value={p.combined_score} withBar={false} /></td>
                                        <td className="num">{p.gbp_score ?? '—'}</td>
                                        <td className="num">{p.a11y_score ?? '—'}</td>
                                        <td><AnglePill angle={p.dominant_angle} /></td>
                                        <td onClick={(e) => e.stopPropagation()}>
                                            {p.outreach_sent_label ? (
                                                <div className="micro">
                                                    Sent {p.outreach_sent_label}
                                                    {p.report_viewed_label && (
                                                        <div style={{ color: 'var(--color-accent-ink)', marginTop: 2 }}>
                                                            Viewed {p.report_viewed_label}
                                                        </div>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="micro">—</span>
                                            )}
                                        </td>
                                        <td onClick={(e) => e.stopPropagation()} style={{ textAlign: 'right' }}>
                                            <div className="row-actions">
                                                {p.report_url && (
                                                    <button type="button" className="btn-icon" title="Copy report URL" onClick={() => copyUrl(p.report_url)}>
                                                        <span className="micro">Copy</span>
                                                    </button>
                                                )}
                                                <button type="button" className="btn-ghost btn-xs" onClick={() => addToOutreach(p.id)}>
                                                    + Queue
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {toast && <Toast onClose={() => setToast(null)}>{toast}</Toast>}
            </main>
        </AuthenticatedLayout>
    );
}
