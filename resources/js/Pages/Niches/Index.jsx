import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NicheSamplePanel from '@/Components/Niches/NicheSamplePanel';
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

const PER_PAGE = 50;

function formatPct(value) {
    if (value == null) return '—';
    return `${Number(value).toFixed(1)}%`;
}

function mergeRows(prev, incoming) {
    const ids = new Set(prev.map((r) => r.id));
    const merged = [...prev];
    for (const row of incoming) {
        if (!ids.has(row.id)) {
            merged.push(row);
        }
    }
    return merged;
}

function buildParams(filters, page) {
    const params = {
        city: filters.city ?? '',
        sort: filters.sort ?? 'opportunity_score',
        page,
    };
    Object.keys(params).forEach((k) => {
        if (params[k] === '' || params[k] == null) delete params[k];
    });
    return params;
}

export default function NichesIndex({ scans: initialScans, pagination, cities, filters }) {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(null);
    const [rows, setRows] = useState(initialScans);
    const [meta, setMeta] = useState(pagination);
    const [loadingMore, setLoadingMore] = useState(false);
    const [selected, setSelected] = useState(null);
    const sentinelRef = useRef(null);
    const hydratingRef = useRef(false);
    const deepLinkDoneRef = useRef(false);

    useEffect(() => {
        if (flash?.success) {
            setToast(flash.success);
        }
    }, [flash?.success]);

    useEffect(() => {
        setRows(initialScans);
        setMeta(pagination);
        setSelected(null);
        deepLinkDoneRef.current = false;
    }, [initialScans, pagination]);

    const applyFilters = (overrides = {}) => {
        setSelected(null);
        const params = buildParams({ ...filters, ...overrides }, 1);
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

    const loadPage = useCallback(
        (nextPage, { replace = true, onDone, force = false } = {}) => {
            if (!force && (loadingMore || nextPage > meta.last_page)) {
                return;
            }

            setLoadingMore(true);
            router.get('/niches', buildParams(filters, nextPage), {
                preserveState: true,
                preserveScroll: true,
                replace,
                only: ['scans', 'pagination'],
                onSuccess: ({ props }) => {
                    setRows((prev) => mergeRows(prev, props.scans));
                    setMeta(props.pagination);
                },
                onFinish: () => {
                    setLoadingMore(false);
                    onDone?.();
                },
            });
        },
        [filters, loadingMore, meta.last_page],
    );

    useEffect(() => {
        if (deepLinkDoneRef.current || hydratingRef.current) {
            return;
        }

        const urlPage = Number(new URLSearchParams(window.location.search).get('page') || filters.page || 1);
        if (urlPage <= 1 || meta.last_page < urlPage) {
            deepLinkDoneRef.current = true;
            return;
        }

        if (meta.current_page >= urlPage) {
            deepLinkDoneRef.current = true;
            const target = document.querySelector(`[data-niche-row-index="${(urlPage - 1) * PER_PAGE}"]`);
            target?.scrollIntoView({ block: 'start' });
            return;
        }

        hydratingRef.current = true;
        let page = meta.current_page + 1;

        const loadNext = () => {
            if (page > urlPage) {
                hydratingRef.current = false;
                deepLinkDoneRef.current = true;
                const target = document.querySelector(`[data-niche-row-index="${(urlPage - 1) * PER_PAGE}"]`);
                target?.scrollIntoView({ block: 'start' });
                return;
            }

            loadPage(page, {
                replace: page < urlPage,
                force: true,
                onDone: () => {
                    page += 1;
                    loadNext();
                },
            });
        };

        loadNext();
    }, [filters.page, loadPage, meta.current_page, meta.last_page]);

    useEffect(() => {
        const el = sentinelRef.current;
        if (!el || loadingMore || meta.current_page >= meta.last_page) {
            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0]?.isIntersecting && !loadingMore && meta.current_page < meta.last_page) {
                    loadPage(meta.current_page + 1);
                }
            },
            { rootMargin: '200px' },
        );

        observer.observe(el);
        return () => observer.disconnect();
    }, [loadPage, loadingMore, meta.current_page, meta.last_page]);

    const loadedCount = rows.length;
    const from = loadedCount === 0 ? 0 : 1;
    const to = loadedCount;
    const total = meta?.total ?? 0;
    const currentPage = meta?.current_page ?? 1;
    const lastPage = meta?.last_page ?? 1;

    return (
        <AuthenticatedLayout>
            <Head title="Niches" />

            <main className="page page-wide">
                {rows.length === 0 && !loadingMore ? (
                    <>
                        <PageHeader
                            eyebrow="G · Niche opportunity"
                            title="Rank markets before you scan."
                            sub="Sampled GBP weakness by niche and city. Higher opportunity scores suggest denser prospect potential."
                        />
                        <FilterBar onSubmit={(e) => e.preventDefault()}>
                            <div className="filter-action">
                                <Button type="button" onClick={() => router.post('/niches/scan')}>
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
                        <EmptyState
                            icon={Icons.Search}
                            title="No niche scans yet."
                            sub='Click "Run Now" to queue a sample scan across default cities and niches.'
                        />
                    </>
                ) : (
                    <div className="niches-layout">
                        <div className="niches-main">
                            <div className="niches-sticky-stack">
                                <PageHeader
                                    eyebrow="G · Niche opportunity"
                                    title="Rank markets before you scan."
                                    sub="Sampled GBP weakness by niche and city. Higher opportunity scores suggest denser prospect potential."
                                />

                                <FilterBar onSubmit={(e) => e.preventDefault()}>
                                    <div className="filter-action">
                                        <Button type="button" onClick={() => router.post('/niches/scan')}>
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

                                <div className="niches-list-meta">
                                    Showing {from}–{to} of {total} · Page {currentPage} of {lastPage}
                                </div>

                                <DataTable style={{ borderRadius: 0, borderLeft: 0, borderRight: 0 }}>
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
                                </DataTable>
                            </div>

                            <div className="niches-table-scroll">
                                <DataTable style={{ borderTop: 0, borderRadius: 0 }}>
                                    <tbody>
                                        {rows.map((row, index) => (
                                            <tr
                                                key={row.id}
                                                data-niche-row-index={index}
                                                className={selected?.id === row.id ? 'selected' : ''}
                                                onClick={() => setSelected(row)}
                                            >
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
                                                    <ScoreBadge
                                                        value={row.avg_gbp_score != null ? Math.round(row.avg_gbp_score) : null}
                                                        withBar={false}
                                                    />
                                                </td>
                                                <td className="tabular">{formatPct(row.pct_no_website)}</td>
                                                <td className="tabular">{formatPct(row.pct_low_reviews)}</td>
                                                <td>
                                                    <ScoreBadge
                                                        value={
                                                            row.opportunity_score != null
                                                                ? Math.round(row.opportunity_score)
                                                                : null
                                                        }
                                                        withBar={false}
                                                    />
                                                </td>
                                                <td className="micro">{row.ran_at_human}</td>
                                                <td style={{ textAlign: 'right' }}>
                                                    <RowActions>
                                                        <button
                                                            type="button"
                                                            className="btn-ghost btn-xs"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                runFullScan(row);
                                                            }}
                                                        >
                                                            Run Full Scan
                                                        </button>
                                                    </RowActions>
                                                </td>
                                            </tr>
                                        ))}
                                        {loadingMore && (
                                            <tr>
                                                <td colSpan={9} className="micro" style={{ textAlign: 'center' }}>
                                                    Loading more…
                                                </td>
                                            </tr>
                                        )}
                                        <tr ref={sentinelRef}>
                                            <td colSpan={9} style={{ height: 1, padding: 0, border: 0 }} />
                                        </tr>
                                    </tbody>
                                </DataTable>
                            </div>
                        </div>

                        {selected && (
                            <NicheSamplePanel
                                scan={selected}
                                onClose={() => setSelected(null)}
                                onRunFullScan={runFullScan}
                            />
                        )}
                    </div>
                )}

                {toast && <Toast duration={2200} onClose={() => setToast(null)}>{toast}</Toast>}
            </main>
        </AuthenticatedLayout>
    );
}
