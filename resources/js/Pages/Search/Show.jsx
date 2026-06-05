import { Head, Link, router } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';
import { useProgressReload } from '@/hooks/useProgressReload';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CmsBadge from '@/Components/cms/CmsBadge';
import {
    AnglePill,
    Badge,
    Button,
    Checkbox,
    DataTable,
    EmptyState,
    Field,
    FilterBar,
    Icon,
    Icons,
    PageHeader,
    RowActions,
    ScoreBadge,
    Segmented,
    Status,
} from '@/Components/ui';
import { normalizeAngle } from '@/Components/ui/scoreBand';

export default function SearchShow({ search, prospects, outreachProspectIds = [] }) {
    const inQueue = new Set(outreachProspectIds);
    const flow = search.progress_flow ?? {};
    const phase = flow.phase ?? 'queued';
    const isRunning = ['queued', 'discovering', 'auditing'].includes(phase);
    const showA11y = search.scan_type !== 'gbp_only';

    const [selected, setSelected] = useState({});
    const [expanded, setExpanded] = useState(null);
    const [angleFilter, setAngleFilter] = useState('all');
    const [minScore, setMinScore] = useState(0);

    useProgressReload(isRunning, ['search', 'prospects']);

    const visible = useMemo(() => {
        return prospects.filter((p) => {
            if (minScore > 0 && (p.combined_score ?? 0) < minScore) return false;
            if (angleFilter === 'all') return true;
            return normalizeAngle(p.dominant_angle) === angleFilter;
        });
    }, [prospects, minScore, angleFilter]);

    const selectedIds = Object.keys(selected).filter((id) => selected[id]);
    const total = search.total_found ?? prospects.length;
    const discovered = prospects.length;
    const isDirectUrl = search.source === 'direct_url';
    const progressCurrent = flow.progress ?? (phase === 'discovering' ? discovered : prospects.filter((p) => p.audit_status !== 'pending').length);
    const directHost = search.submitted_url?.replace(/^https?:\/\//, '') ?? 'Single site';
    const pageTitle = isDirectUrl ? directHost : `${search.niche} in ${search.city}`;
    const eyebrow = isDirectUrl ? `B · Single site · ${directHost}` : `B · ${search.niche} · ${search.city}`;
    const runningSub = isDirectUrl
        ? 'Looking up Google Business Profile and running the WCAG 2.2 audit. Results appear when complete.'
        : 'Discovering businesses on Google, then running audits in parallel. Rows appear as their audits complete.';
    const completeTitle = isDirectUrl
        ? (prospects.length > 0 ? 'Audit complete.' : 'Waiting for audit…')
        : `${discovered} prospects scanned.`;
    const phaseTitle = {
        queued: 'Queued…',
        discovering: 'Discovering…',
        auditing: 'Auditing…',
    }[phase];
    const phaseLabel = isDirectUrl
        ? 'Auditing website'
        : {
              queued: 'Waiting for worker',
              discovering: 'Discovering businesses',
              auditing: 'Auditing websites',
              complete: 'Complete',
              failed: 'Failed',
          }[phase];
    const flowTotal = flow.total ?? total;
    const pct = flow.percent ?? (flowTotal > 0 ? Math.round((progressCurrent / flowTotal) * 100) : 0);
    const progressMeta = flow.message ?? (phase === 'queued' ? 'starting soon' : `${progressCurrent} of ${flowTotal}`);

    const toggleRow = (id) => setSelected((prev) => ({ ...prev, [id]: !prev[id] }));
    const toggleAll = (checked) => {
        if (checked) {
            setSelected(Object.fromEntries(visible.map((p) => [p.id, true])));
        } else {
            setSelected({});
        }
    };

    const addSelected = () => {
        router.post('/outreach/selections', { prospect_ids: selectedIds.map(Number) });
        setSelected({});
    };

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />

            <main className="page page-wide" style={{ maxWidth: 1440 }}>
                <PageHeader
                    eyebrow={eyebrow}
                    title={isRunning ? (isDirectUrl ? 'Auditing website…' : phaseTitle) : completeTitle}
                    sub={
                        isRunning
                            ? runningSub
                            : 'Sort by combined score for the warmest leads — top decile is auto-tinted ochre. Expand any row to see weakness flags.'
                    }
                    back="Back to search"
                    onBack={() => router.visit('/search')}
                    actions={
                        selectedIds.length > 0 ? (
                            <Button kind="primary" size="sm" icon={Icons.Plus} onClick={addSelected}>
                                Add {selectedIds.length} to outreach
                            </Button>
                        ) : null
                    }
                />

                {isRunning && (
                    <div className="progress-bar">
                        <div className="progress-text">
                            <span className="spinner" />
                            <strong>{phaseLabel}</strong>
                            <span className="progress-meta">{progressMeta}</span>
                        </div>
                        <div className="progress-track">
                            <div className="progress-fill" style={{ width: `${pct}%` }} />
                        </div>
                        <div className="progress-pct tabular">{pct}%</div>
                    </div>
                )}

                <FilterBar onSubmit={(e) => e.preventDefault()}>
                    <Field label="Angle">
                        <Segmented
                            value={angleFilter}
                            onChange={setAngleFilter}
                            options={[
                                { value: 'all', label: 'All' },
                                { value: 'both', label: 'Both' },
                                { value: 'gbp', label: 'GBP' },
                                { value: 'a11y', label: 'A11y' },
                            ]}
                        />
                    </Field>
                    <Field label="Min combined score">
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, paddingTop: 6 }}>
                            <input
                                type="range"
                                min="0"
                                max="100"
                                step="5"
                                value={minScore}
                                onChange={(e) => setMinScore(+e.target.value)}
                                style={{ width: 140, accentColor: 'var(--color-ink)' }}
                            />
                            <span className="micro tabular" style={{ minWidth: 36 }}>{minScore}+</span>
                        </div>
                    </Field>
                    <div className="filter-action">
                        <span className="micro">Showing {visible.length} of {prospects.length}</span>
                    </div>
                </FilterBar>

                {visible.length === 0 && !isRunning ? (
                    <EmptyState
                        icon={Icons.Search}
                        title="No prospects match these filters."
                        sub="Try lowering the minimum score or clearing the angle filter."
                    />
                ) : (
                    <DataTable tableClassName="ptable--prospects" style={{ background: 'var(--color-paper)' }}>
                            <thead>
                                <tr>
                                    <th style={{ width: 36 }}>
                                        <Checkbox
                                            checked={selectedIds.length > 0 && selectedIds.length === visible.length}
                                            indeterminate={selectedIds.length > 0 && selectedIds.length < visible.length}
                                            onChange={toggleAll}
                                        />
                                    </th>
                                    <th style={{ width: '28%' }}>Business</th>
                                    <th style={{ width: '9%' }}>Combined</th>
                                    {showA11y && (
                                        <>
                                            <th style={{ width: '6%' }}>GBP</th>
                                            <th style={{ width: '6%' }}>A11y</th>
                                            <th style={{ width: '6%' }}>Perf</th>
                                        </>
                                    )}
                                    <th style={{ width: '8%' }}>CMS</th>
                                    <th style={{ width: '11%' }}>Angle</th>
                                    <th style={{ width: '14%' }}>Report status</th>
                                    <th style={{ width: '14%', textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((p) => (
                                    <ProspectRow
                                        key={p.id}
                                        prospect={p}
                                        showA11y={showA11y}
                                        inQueue={inQueue.has(p.id)}
                                        isExpanded={expanded === p.id}
                                        isSelected={!!selected[p.id]}
                                        onToggleSelect={() => toggleRow(p.id)}
                                        onToggleExpand={() => setExpanded(expanded === p.id ? null : p.id)}
                                    />
                                ))}
                                {isRunning &&
                                    Array.from({ length: Math.max(0, 3) }).map((_, i) => (
                                        <tr key={`skel-${i}`}>
                                            <td colSpan={showA11y ? 10 : 7} style={{ padding: '14px 20px' }}>
                                                <span className="skel" style={{ width: '60%', display: 'block' }} />
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                    </DataTable>
                )}
            </main>
        </AuthenticatedLayout>
    );
}

function ProspectRow({
    prospect: p,
    showA11y,
    inQueue,
    isExpanded,
    isSelected,
    onToggleSelect,
    onToggleExpand,
}) {
    const isFailed = p.audit_status === 'failed';
    const isPending = p.audit_status === 'pending';
    const isWarm = p.is_warm;
    const urlDisplay = p.website_url?.replace(/^https?:\/\//, '') ?? 'No website';

    const rowClass = [
        isWarm ? 'warm' : '',
        isFailed ? 'failed' : '',
        isSelected ? 'selected' : '',
        isExpanded ? 'expanded' : '',
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <Fragment>
            <tr
                className={rowClass}
                onClick={() => router.visit(`/prospects/${p.id}`)}
            >
                <td onClick={(e) => e.stopPropagation()}>
                    <Checkbox checked={isSelected} onChange={onToggleSelect} />
                </td>
                <td className="biz">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        {p.business_name}
                                        {inQueue && (
                                            <span className="badge" style={{ fontSize: 10, background: 'var(--color-stone-200)' }}>
                                                In outreach
                                            </span>
                                        )}
                    </div>
                    <span
                        className="url"
                        title={isFailed ? undefined : urlDisplay}
                        style={isFailed ? { color: 'var(--color-sev-critical)' } : {}}
                    >
                        {isFailed ? (p.audit_error ?? 'Audit failed') : urlDisplay}
                    </span>
                </td>
                <td>
                    <ScoreBadge value={p.combined_score} />
                </td>
                {showA11y && (
                    <>
                        <td className="num">{p.gbp_score ?? '—'}</td>
                        <td className="num">{isPending ? '…' : (p.a11y_score ?? '—')}</td>
                        <td className="num">
                            <PerfScore value={p.performance_score} auditStatus={p.audit_status} />
                        </td>
                    </>
                )}
                <td>
                    <CmsBadge badge={p.cms_badge} pending={p.cms_pending} />
                </td>
                <td>
                    <AnglePill angle={p.dominant_angle} />
                </td>
                <td>
                    {isFailed ? (
                        <Status kind="failed">Audit failed</Status>
                    ) : isPending ? (
                        <Status kind="pending">{p.progress_flow?.status_message ?? 'Auditing site'}</Status>
                    ) : isWarm ? (
                        <Status kind="warm">Viewed {p.last_viewed}</Status>
                    ) : (
                        <Status kind="ready">Report ready</Status>
                    )}
                </td>
                <td onClick={(e) => e.stopPropagation()} style={{ textAlign: 'right' }}>
                    <RowActions>
                        <button type="button" className="btn-icon" title="Expand weaknesses" onClick={onToggleExpand}>
                            <Icon d={isExpanded ? Icons.ChevronU : Icons.ChevronD} />
                        </button>
                        {p.place_id && !p.place_id.startsWith('direct:') && (
                            <a
                                className="btn-icon"
                                title="View on Maps"
                                href={`https://www.google.com/maps/place/?q=place_id:${p.place_id}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <Icon d={Icons.Map} />
                            </a>
                        )}
                        {p.report_url && !isFailed && !isPending ? (
                            <a
                                className="btn-icon"
                                title="Preview report"
                                href={p.report_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <Icon d={Icons.Eye} />
                            </a>
                        ) : (
                            <button type="button" className="btn-icon" title="Preview report" disabled>
                                <Icon d={Icons.Eye} />
                            </button>
                        )}
                        <Link
                            href={`/prospects/${p.id}`}
                            className="btn-icon"
                            title="Open prospect"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <Icon d={Icons.ChevronR} />
                        </Link>
                    </RowActions>
                </td>
            </tr>
            {isExpanded && (
                <tr className="expanded-row">
                    <td colSpan={showA11y ? 9 : 6}>
                        <div className="ex-inner">
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 32 }}>
                                <div>
                                    <div className="eyebrow" style={{ marginBottom: 10 }}>GBP weaknesses</div>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                        {(p.gbp_flags ?? []).length === 0 ? (
                                            <span className="micro">None flagged</span>
                                        ) : (
                                            (p.gbp_flags ?? []).map((flag, i) => (
                                                <Badge key={i}>{flag}</Badge>
                                            ))
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <div className="eyebrow" style={{ marginBottom: 10 }}>Accessibility weaknesses</div>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                        {(p.a11y_flags ?? []).length === 0 ? (
                                            <span className="micro">None flagged</span>
                                        ) : (
                                            (p.a11y_flags ?? []).map((flag, i) => (
                                                <Badge key={i}>{flag}</Badge>
                                            ))
                                        )}
                                    </div>
                                </div>
                            </div>
                            {!inQueue && (
                                <div style={{ marginTop: 16 }}>
                                    <Button
                                        kind="secondary"
                                        size="sm"
                                        onClick={() => router.post('/outreach/selections', { prospect_ids: [p.id] })}
                                    >
                                        Add to outreach
                                    </Button>
                                </div>
                            )}
                        </div>
                    </td>
                </tr>
            )}
        </Fragment>
    );
}

function PerfScore({ value, auditStatus: _auditStatus }) {
    if (!value || value === 0) {
        return '—';
    }

    const color =
        value < 50 ? 'var(--color-sev-critical)' :
        value < 70 ? 'var(--color-sev-serious)' :
                     'var(--color-positive)';

    return (
        <span className="tabular" style={{ color, fontWeight: 500 }}>
            {value}
        </span>
    );
}
