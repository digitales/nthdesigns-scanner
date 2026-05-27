import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Button,
    DataTable,
    EmptyState,
    Field,
    FilterBar,
    Icons,
    PageHeader,
    RowActions,
    ScoreBadge,
    Segmented,
    Select,
    Status,
    Toast,
} from '@/Components/ui';

function formatPct(value) {
    if (value == null) return '—';
    return `${Number(value).toFixed(1)}%`;
}

export default function NichesIndex({ scans, cities, filters }) {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(null);

    useEffect(() => {
        if (flash?.success) {
            setToast(flash.success);
        }
    }, [flash?.success]);

    const applyFilters = (overrides = {}) => {
        const params = {
            city: filters.city ?? '',
            sort: filters.sort ?? 'opportunity_score',
            ...overrides,
        };
        Object.keys(params).forEach((k) => {
            if (params[k] === '' || params[k] == null) delete params[k];
        });
        router.get('/niches', params, { preserveState: true });
    };

    const runFullScan = (row) => {
        router.post('/searches', {
            niche: row.niche_query,
            city: row.city,
            country: row.country,
            scan_type: 'gbp_only',
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Niches" />

            <main className="page page-wide">
                <PageHeader
                    eyebrow="G · Niche opportunity"
                    title="Rank markets before you scan."
                    sub="Sampled GBP weakness by niche and city. Higher opportunity scores suggest denser prospect potential."
                />

                <FilterBar onSubmit={(e) => e.preventDefault()}>
                    <div className="filter-action">
                        <Button
                            type="button"
                            onClick={() => router.post('/niches/scan')}
                            disabled={false}
                        >
                            Run Now
                        </Button>
                    </div>
                    <Field label="City">
                        <Select
                            value={filters.city ?? ''}
                            onChange={(e) => applyFilters({ city: e.target.value })}
                        >
                            <option value="">All cities</option>
                            {cities.map((city) => (
                                <option key={city} value={city}>
                                    {city}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Sort by">
                        <Segmented
                            value={filters.sort ?? 'opportunity_score'}
                            onChange={(v) => applyFilters({ sort: v })}
                            options={[
                                { value: 'opportunity_score', label: 'Opportunity' },
                                { value: 'result_count', label: 'Result count' },
                            ]}
                        />
                    </Field>
                </FilterBar>

                {scans.length === 0 ? (
                    <EmptyState
                        icon={Icons.Search}
                        title="No niche scans yet."
                        sub='Click "Run Now" to queue a sample scan across default cities and niches.'
                    />
                ) : (
                    <DataTable>
                        <thead>
                            <tr>
                                <th>Niche</th>
                                <th>City</th>
                                <th>Results</th>
                                <th>Avg GBP</th>
                                <th>No website</th>
                                <th>Low reviews</th>
                                <th>Opportunity</th>
                                <th>Last scanned</th>
                                <th style={{ textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {scans.map((row) => (
                                <tr key={`${row.niche}-${row.city}`}>
                                    <td className="biz">
                                        {row.niche}
                                        {row.status !== 'complete' && (
                                            <div style={{ marginTop: 4 }}>
                                                <Status kind={row.status === 'failed' ? 'failed' : 'pending'}>
                                                    {row.status}
                                                </Status>
                                            </div>
                                        )}
                                    </td>
                                    <td>{row.city}</td>
                                    <td className="tabular">{row.result_count ?? '—'}</td>
                                    <td>
                                        <ScoreBadge value={row.avg_gbp_score != null ? Math.round(row.avg_gbp_score) : null} withBar={false} />
                                    </td>
                                    <td className="tabular">{formatPct(row.pct_no_website)}</td>
                                    <td className="tabular">{formatPct(row.pct_low_reviews)}</td>
                                    <td>
                                        <ScoreBadge
                                            value={row.opportunity_score != null ? Math.round(row.opportunity_score) : null}
                                            withBar={false}
                                        />
                                    </td>
                                    <td className="micro">{row.ran_at_human}</td>
                                    <td style={{ textAlign: 'right' }}>
                                        <RowActions>
                                            <button
                                                type="button"
                                                className="btn-ghost btn-xs"
                                                onClick={() => runFullScan(row)}
                                            >
                                                Run Full Scan
                                            </button>
                                        </RowActions>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                )}

                {toast && <Toast duration={2200} onClose={() => setToast(null)}>{toast}</Toast>}
            </main>
        </AuthenticatedLayout>
    );
}
