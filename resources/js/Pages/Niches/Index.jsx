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
const TOPBAR_HEIGHT = 52;

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

function syncUrlPage(filters, page) {
    const params = new URLSearchParams(buildParams(filters, page));
    const qs = params.toString();
    const next = `${window.location.pathname}${qs ? `?${qs}` : ''}`;
    if (window.location.pathname + window.location.search !== next) {
        window.history.replaceState(window.history.state, '', next);
    }
}

export default function NichesIndex({ scans: initialScans, pagination, cities, filters }) {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(null);
    const [rows, setRows] = useState(initialScans);
    const [meta, setMeta] = useState(pagination);
    const [loadingMore, setLoadingMore] = useState(false);
    const [selected, setSelected] = useState(null);
    const [viewRange, setViewRange] = useState({
        from: 1,
        to: Math.min(PER_PAGE, pagination?.total ?? PER_PAGE),
        page: 1,
    });
    const sentinelRef = useRef(null);
    const metaBarRef = useRef(null);
    const tableWrapRef = useRef(null);
    const hydratingRef = useRef(false);
    const deepLinkDoneRef = useRef(false);
    const scrollRafRef = useRef(null);

    const total = meta?.total ?? 0;
    const lastPage = meta?.last_page ?? 1;

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
        const initialTo = Math.min(PER_PAGE, pagination?.total ?? initialScans.length);
        setViewRange({ from: initialScans.length ? 1 : 0, to: initialTo || 0, page: 1 });
    }, [initialScans, pagination]);

    useEffect(() => {
        const el = metaBarRef.current;
        if (!el) {
            return undefined;
        }

        const setMetaHeight = () => {
            document.documentElement.style.setProperty('--niches-meta-height', `${el.offsetHeight}px`);
        };

        setMetaHeight();
        const ro = new ResizeObserver(setMetaHeight);
        ro.observe(el);
        return () => ro.disconnect();
    }, [rows.length]);

    const getScrollAnchor = useCallback(() => {
        const thead = tableWrapRef.current?.querySelector('thead');
        if (thead) {
            return thead.getBoundingClientRect().bottom;
        }
        const metaBottom = metaBarRef.current?.getBoundingClientRect().bottom ?? TOPBAR_HEIGHT + 37;
        return metaBottom;
    }, []);

    const updateViewRangeFromScroll = useCallback(() => {
        const rowEls = tableWrapRef.current?.querySelectorAll('tbody tr[data-niche-row-index]');
        if (!rowEls?.length || total === 0) {
            return;
        }

        const anchor = getScrollAnchor();
        let topIndex = 0;

        for (let i = 0; i < rowEls.length; i++) {
            const rect = rowEls[i].getBoundingClientRect();
            if (rect.bottom > anchor) {
                topIndex = i;
                break;
            }
            topIndex = i;
        }

        const page = Math.min(lastPage, Math.floor(topIndex / PER_PAGE) + 1);
        const from = (page - 1) * PER_PAGE + 1;
        const to = Math.min(page * PER_PAGE, total);

        setViewRange((prev) => {
            if (prev.page === page && prev.from === from && prev.to === to) {
                return prev;
            }
            syncUrlPage(filters, page);
            return { page, from, to };
        });
    }, [filters, getScrollAnchor, lastPage, total]);

    useEffect(() => {
        const onScroll = () => {
            if (scrollRafRef.current) {
                cancelAnimationFrame(scrollRafRef.current);
            }
            scrollRafRef.current = requestAnimationFrame(updateViewRangeFromScroll);
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        onScroll();

        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
            if (scrollRafRef.current) {
                cancelAnimationFrame(scrollRafRef.current);
            }
        };
    }, [updateViewRangeFromScroll, rows.length]);

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
                    requestAnimationFrame(updateViewRangeFromScroll);
                },
            });
        },
        [filters, loadingMore, meta.last_page, updateViewRangeFromScroll],
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
            requestAnimationFrame(updateViewRangeFromScroll);
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
                requestAnimationFrame(updateViewRangeFromScroll);
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
    }, [filters.page, loadPage, meta.current_page, meta.last_page, updateViewRangeFromScroll]);

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
            { rootMargin: '400px 0px' },
        );

        observer.observe(el);
        return () => observer.disconnect();
    }, [loadPage, loadingMore, meta.current_page, meta.last_page]);

    const { from, to, page: visiblePage } = viewRange;

    const pageHeader = (
        <PageHeader
            eyebrow="G · Niche opportunity"
            title="Rank markets before you scan."
            sub="Sampled GBP weakness by niche and city. Higher opportunity scores suggest denser prospect potential."
        />
    );

    const filterBar = (
        <FilterBar onSubmit={(e) => e.preventDefault()}>
            <div className="filter-action">
                <Button type="button" onClick={() => router.post('/niches/scan')}>
                    Run Now
                </Button>
            </div>
            <Field label="City">
                <Select value={filters.city ?? ''} onChange={(e) => applyFilters({ city: e.target.value })}>
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
    );

    return (
        <AuthenticatedLayout>
            <Head title="Niches" />

            <main className="page page-wide">
                {rows.length === 0 && !loadingMore ? (
                    <>
                        {pageHeader}
                        {filterBar}
                        <EmptyState
                            icon={Icons.Search}
                            title="No niche scans yet."
                            sub='Click "Run Now" to queue a sample scan across default cities and niches.'
                        />
                    </>
                ) : (
                    <div className="niches-layout">
                        <div className="niches-main">
                            {pageHeader}
                            {filterBar}

                            <div ref={metaBarRef} className="niches-list-meta">
                                Showing {from}–{to} of {total} · Page {visiblePage} of {lastPage}
                            </div>

                            <div ref={tableWrapRef}>
                                <DataTable className="niches-table" style={{ borderTop: 0, borderRadius: '0 0 6px 6px' }}>
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
