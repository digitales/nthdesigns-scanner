import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import { useProgressReload } from '@/hooks/useProgressReload';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CmsBadge from '@/Components/cms/CmsBadge';
import ListPicker from '@/Components/ListPicker';
import {
    AnglePill,
    Badge,
    Button,
    Card,
    Checkbox,
    DataTable,
    EmptyState,
    Field,
    FilterBar,
    IconButton,
    Icons,
    Input,
    Page,
    PageHeader,
    RowActions,
    ScoreBadge,
    Segmented,
    Stack,
    Status,
} from '@/Components/ui';
import { normalizeAngle } from '@/Components/ui/scoreBand';
import CpcBenchmarkPanel from '@/Components/Search/CpcBenchmarkPanel';
import QualificationControl, {
    QualificationDetails,
    qualifyProspectsWithStagger,
} from '@/Components/QualificationControl';
import { showA11yForSearch } from '@/utils/auditVisibility';
import { prospectHasAuditIssue, prospectStatusLabel, prospectUrlDisplay } from '@/utils/prospectAuditDisplay';

export default function SearchShow({
    search,
    prospects,
    outreachProspectIds = [],
    manualLists = [],
    marketCpcDefault = null,
    googleAdsCpcAvailable = false,
}) {
    const { flash } = usePage().props;
    const cpcForm = useForm({
        cpc_benchmark: search.cpc_benchmark ?? '',
        cpc_keywords: (search.cpc_keywords ?? []).join('\n'),
        save_market_default: true,
    });
    const [fetchingCpc, setFetchingCpc] = useState(false);
    const [importingCpc, setImportingCpc] = useState(false);
    const inQueue = new Set(outreachProspectIds);
    const flow = search.progress_flow ?? {};
    const phase = flow.phase ?? 'queued';
    const isRunning = ['queued', 'discovering', 'auditing'].includes(phase);
    const showA11y = showA11yForSearch(search.scan_type);

    const [selected, setSelected] = useState({});
    const [expanded, setExpanded] = useState(null);
    const [expandedQualification, setExpandedQualification] = useState(null);
    const [qualifyingIds, setQualifyingIds] = useState(() => new Set());
    const [angleFilter, setAngleFilter] = useState('all');
    const [minScore, setMinScore] = useState(0);
    const [sharedUrl, setSharedUrl] = useState(null);
    const [copied, setCopied] = useState(false);

    useProgressReload(isRunning, ['search', 'prospects']);

    const qualificationPolling = useMemo(() => {
        if (qualifyingIds.size === 0) {
            return false;
        }

        return prospects.some(
            (p) => qualifyingIds.has(p.id) && !p.qualification_status,
        );
    }, [prospects, qualifyingIds]);

    useProgressReload(qualificationPolling, ['prospects'], 5000);

    useEffect(() => {
        setQualifyingIds((prev) => {
            if (prev.size === 0) {
                return prev;
            }

            const next = new Set(prev);
            let changed = false;

            for (const id of prev) {
                const prospect = prospects.find((p) => p.id === id);
                if (prospect?.qualification_status) {
                    next.delete(id);
                    changed = true;
                }
            }

            return changed ? next : prev;
        });
    }, [prospects]);

    useEffect(() => {
        if (flash?.shared_url) {
            setSharedUrl(flash.shared_url);
        }
    }, [flash?.shared_url]);

    useEffect(() => {
        cpcForm.setData({
            cpc_benchmark: search.cpc_benchmark ?? '',
            cpc_keywords: (search.cpc_keywords ?? []).join('\n'),
        });
    }, [search.cpc_benchmark, search.cpc_keywords]);

    const visible = useMemo(() => {
        const filtered = prospects.filter((p) => {
            if (minScore > 0 && (p.combined_score ?? 0) < minScore) return false;
            if (angleFilter === 'all') return true;
            return normalizeAngle(p.dominant_angle) === angleFilter;
        });

        return [
            ...filtered.filter((p) => !p.is_hidden),
            ...filtered.filter((p) => p.is_hidden),
        ];
    }, [prospects, minScore, angleFilter]);

    const visibleHiddenCount = useMemo(
        () => visible.filter((p) => p.is_hidden).length,
        [visible],
    );

    const unqualifiedCount = useMemo(
        () => visible.filter((p) => !p.qualification_status).length,
        [visible],
    );

    const markQualifying = (id) => {
        setQualifyingIds((prev) => new Set(prev).add(id));
    };

    const qualifyAll = () => {
        const ids = visible
            .filter((p) => !p.qualification_status)
            .map((p) => p.id);

        qualifyProspectsWithStagger(ids, markQualifying);
    };

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

    const addSelectedToOutreach = () => {
        router.post('/outreach/selections', { prospect_ids: selectedIds.map(Number) });
        setSelected({});
    };

    const addSelectedToList = (listId) => {
        router.post(`/lists/${listId}/items`, { prospect_ids: selectedIds.map(Number) }, {
            preserveScroll: true,
            onSuccess: () => setSelected({}),
        });
    };

    const saveCpc = (e) => {
        e.preventDefault();
        router.patch(`/searches/${search.id}/cpc`, {
            cpc_benchmark: cpcForm.data.cpc_benchmark === '' ? null : cpcForm.data.cpc_benchmark,
            cpc_keywords: cpcForm.data.cpc_keywords
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean),
            save_market_default: true,
        }, { preserveScroll: true });
    };

    const fetchCpcFromGoogleAds = () => {
        setFetchingCpc(true);
        router.post(`/searches/${search.id}/cpc/fetch`, {}, {
            preserveScroll: true,
            onFinish: () => setFetchingCpc(false),
        });
    };

    const importKeywordPlannerCsv = (file) => {
        setImportingCpc(true);
        router.post(`/searches/${search.id}/cpc/import`, { file }, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => setImportingCpc(false),
        });
    };

    const shareSearch = () => {
        router.post(`/searches/${search.id}/share`, {}, { preserveScroll: true });
    };

    const copyShareLink = () => {
        if (!sharedUrl) return;

        navigator.clipboard.writeText(sharedUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const cpcSourceLabel = {
        manual: 'Manual',
        google_ads: 'Google Ads',
        market_default: 'Market default',
        keyword_planner_csv: 'Keyword Planner CSV',
    }[search.cpc_source] ?? search.cpc_source;

    const selectedProspects = useMemo(
        () => prospects.filter((p) => selected[p.id]),
        [prospects, selected],
    );

    const bulkAddableLists = useMemo(() => {
        if (selectedProspects.length === 0) {
            return [];
        }

        const memberListIdsByProspect = selectedProspects.map(
            (p) => new Set((p.list_memberships ?? []).map((m) => m.list_id)),
        );

        return manualLists.filter((list) =>
            memberListIdsByProspect.some((memberIds) => !memberIds.has(list.id)),
        );
    }, [manualLists, selectedProspects]);

    const canBulkAudit = ['auditing', 'complete', 'failed'].includes(phase);
    const bulkAuditBlockedTitle = 'Wait until discovery finishes before bulk re-auditing.';
    const failedEligibleCount = useMemo(
        () => selectedProspects.filter((p) => p.audit_status === 'failed' && p.website_url).length,
        [selectedProspects],
    );
    const forceEligibleCount = useMemo(
        () => selectedProspects.filter((p) => p.website_url).length,
        [selectedProspects],
    );

    const bulkAudit = (mode) => {
        router.post(`/searches/${search.id}/bulk-audit`, {
            prospect_ids: selectedIds.map(Number),
            mode,
        }, {
            preserveScroll: true,
            onSuccess: () => setSelected({}),
        });
    };

    const hideProspect = (id) => {
        router.post(`/prospects/${id}/ignore`, { reason: 'reviewed' }, { preserveScroll: true });
    };

    const restoreProspect = (id) => {
        router.delete(`/prospects/${id}/ignore`, { preserveScroll: true });
    };

    const hideSelected = () => {
        router.post(`/searches/${search.id}/bulk-hide`, {
            prospect_ids: selectedIds.map(Number),
        }, {
            preserveScroll: true,
            onSuccess: () => setSelected({}),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />

            <Page width="xl" className="page-wide">
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
                        <>
                            {prospects.length > 0 && (
                                <Button kind="secondary" size="sm" icon={Icons.Share} onClick={shareSearch}>
                                    Share
                                </Button>
                            )}
                            {unqualifiedCount > 0 && (
                                <Button kind="secondary" size="sm" onClick={qualifyAll}>
                                    Qualify all ({unqualifiedCount})
                                </Button>
                            )}
                            {selectedIds.length > 0 ? (
                                <>
                                    <Button
                                        kind="secondary"
                                        size="sm"
                                        onClick={hideSelected}
                                    >
                                        Hide {selectedIds.length}
                                    </Button>
                                    <Button
                                        kind="secondary"
                                        size="sm"
                                        disabled={failedEligibleCount === 0 || !canBulkAudit}
                                        title={!canBulkAudit ? bulkAuditBlockedTitle : undefined}
                                        onClick={() => bulkAudit('failed')}
                                    >
                                        Re-audit {failedEligibleCount} failed
                                    </Button>
                                    <Button
                                        kind="secondary"
                                        size="sm"
                                        disabled={forceEligibleCount === 0 || !canBulkAudit}
                                        title={!canBulkAudit ? bulkAuditBlockedTitle : undefined}
                                        onClick={() => bulkAudit('force')}
                                    >
                                        Force re-audit {forceEligibleCount}
                                    </Button>
                                    <Button kind="primary" size="sm" icon={Icons.Plus} onClick={addSelectedToOutreach}>
                                        Add {selectedIds.length} to outreach
                                    </Button>
                                    {bulkAddableLists.length > 0 && (
                                        <ListPicker
                                            lists={bulkAddableLists}
                                            placeholder={`Add ${selectedIds.length} to list…`}
                                            onSelect={addSelectedToList}
                                        />
                                    )}
                                </>
                            ) : null}
                        </>
                    }
                />

                {sharedUrl && (
                    <Card className="banner-muted">
                        <div className="micro mb-4">
                            Share link created — anyone with this link can view a snapshot of this search.
                        </div>
                        <div className="micro mb-8 break-all">{sharedUrl}</div>
                        <Stack direction="row" gap={8}>
                            <Button kind="secondary" size="sm" onClick={copyShareLink}>
                                {copied ? 'Copied' : 'Copy link'}
                            </Button>
                            <a href={sharedUrl} target="_blank" rel="noopener noreferrer">
                                <Button kind="ghost" size="sm">Open</Button>
                            </a>
                            <Button kind="ghost" size="sm" onClick={() => setSharedUrl(null)}>
                                Dismiss
                            </Button>
                        </Stack>
                    </Card>
                )}

                {!isDirectUrl && (
                    <CpcBenchmarkPanel
                        niche={search.niche}
                        city={search.city}
                        cpcSource={search.cpc_source}
                        cpcSourceLabel={cpcSourceLabel}
                        cpcBenchmark={cpcForm.data.cpc_benchmark}
                        cpcKeywords={cpcForm.data.cpc_keywords}
                        marketCpcDefault={marketCpcDefault}
                        marketDefaultUpdatedAt={marketCpcDefault?.updated_at}
                        googleAdsCpcAvailable={googleAdsCpcAvailable}
                        fetchingCpc={fetchingCpc}
                        importingCpc={importingCpc}
                        processing={cpcForm.processing}
                        flash={flash}
                        onBenchmarkChange={(value) => cpcForm.setData('cpc_benchmark', value)}
                        onKeywordsChange={(value) => cpcForm.setData('cpc_keywords', value)}
                        onSubmit={saveCpc}
                        onFetchFromGoogleAds={fetchCpcFromGoogleAds}
                        onImportKeywordPlanner={importKeywordPlannerCsv}
                    />
                )}

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
                        <div className="filter-range-row">
                            <Input
                                type="range"
                                className="input-range"
                                min="0"
                                max="100"
                                step="5"
                                value={minScore}
                                onChange={(e) => setMinScore(+e.target.value)}
                            />
                            <span className="micro tabular min-w-36">{minScore}+</span>
                        </div>
                    </Field>
                    <div className="filter-action">
                        <span className="micro">
                            Showing {visible.length} of {prospects.length}
                            {visibleHiddenCount > 0 && (
                                <> · {visibleHiddenCount} reviewed at bottom</>
                            )}
                        </span>
                    </div>
                </FilterBar>

                {visible.length === 0 && !isRunning ? (
                    <EmptyState
                        icon={Icons.Search}
                        title="No prospects match these filters."
                        sub="Try lowering the minimum score or clearing the angle filter."
                    />
                ) : (
                    <DataTable tableClassName="ptable--prospects ptable--paper">
                            <thead>
                                <tr>
                                    <th className="col-check">
                                        <Checkbox
                                            checked={selectedIds.length > 0 && selectedIds.length === visible.length}
                                            indeterminate={selectedIds.length > 0 && selectedIds.length < visible.length}
                                            onChange={toggleAll}
                                        />
                                    </th>
                                    <th className="col-biz">Business</th>
                                    <th className="col-combined">Combined</th>
                                    {showA11y && (
                                        <>
                                            <th className="col-score-sm">GBP</th>
                                            <th className="col-score-sm">A11y</th>
                                            <th className="col-score-sm">Perf</th>
                                        </>
                                    )}
                                    <th className="col-cms">CMS</th>
                                    <th className="col-angle">Angle</th>
                                    <th className="col-qualify">Qualify</th>
                                    <th className="col-report">Report status</th>
                                    <th className="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((p, index) => (
                                    <Fragment key={p.id}>
                                        {visibleHiddenCount > 0
                                            && index > 0
                                            && !visible[index - 1].is_hidden
                                            && p.is_hidden && (
                                            <tr className="hidden-prospects-divider">
                                                <td colSpan={showA11y ? 11 : 8}>
                                                    Reviewed ({visibleHiddenCount})
                                                </td>
                                            </tr>
                                        )}
                                        <ProspectRow
                                            prospect={p}
                                            showA11y={showA11y}
                                            inQueue={inQueue.has(p.id)}
                                            manualLists={manualLists}
                                            isExpanded={expanded === p.id}
                                            isQualificationExpanded={expandedQualification === p.id}
                                            isQualifying={qualifyingIds.has(p.id)}
                                            isSelected={!!selected[p.id]}
                                            onToggleSelect={() => toggleRow(p.id)}
                                            onToggleExpand={() => setExpanded(expanded === p.id ? null : p.id)}
                                            onToggleQualificationExpand={() => setExpandedQualification(
                                                expandedQualification === p.id ? null : p.id,
                                            )}
                                            onQualifyStart={markQualifying}
                                            onHide={() => hideProspect(p.id)}
                                            onRestore={() => restoreProspect(p.id)}
                                        />
                                    </Fragment>
                                ))}
                                {isRunning &&
                                    Array.from({ length: Math.max(0, 3) }).map((_, i) => (
                                        <tr key={`skel-${i}`}>
                                            <td colSpan={showA11y ? 11 : 8} className="skel-row">
                                                <span className="skel skel-block" />
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                    </DataTable>
                )}
            </Page>
        </AuthenticatedLayout>
    );
}

function ProspectRow({
    prospect: p,
    showA11y,
    inQueue,
    manualLists = [],
    isExpanded,
    isQualificationExpanded,
    isQualifying,
    isSelected,
    onToggleSelect,
    onToggleExpand,
    onToggleQualificationExpand,
    onQualifyStart,
    onHide,
    onRestore,
}) {
    const isFailed = p.audit_status === 'failed';
    const isPending = p.audit_status === 'pending';
    const isWarm = p.is_warm;
    const urlDisplay = prospectUrlDisplay(p);
    const statusDisplay = prospectStatusLabel(p);
    const reportUsable = !isFailed && !isPending && !prospectHasAuditIssue(p);
    const listMemberships = p.list_memberships ?? [];
    const memberListIds = new Set(listMemberships.map((m) => m.list_id));
    const addableLists = manualLists.filter((list) => !memberListIds.has(list.id));

    const addToList = (listId) => {
        router.post(`/lists/${listId}/items`, { prospect_ids: [p.id] }, { preserveScroll: true });
    };

    const rowClass = [
        isWarm ? 'warm' : '',
        isFailed ? 'failed' : '',
        isSelected ? 'selected' : '',
        isExpanded ? 'expanded' : '',
        p.is_hidden ? 'hidden-prospect' : '',
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
                    <div className="biz-title-row">
                        {p.business_name}
                        {inQueue && (
                            <span className="badge badge--queue">In outreach</span>
                        )}
                        {listMemberships.length === 1 && (
                            <span className="badge badge--list">{listMemberships[0].list_name}</span>
                        )}
                        {listMemberships.length > 1 && (
                            <span className="badge badge--list">On {listMemberships.length} lists</span>
                        )}
                        {p.is_hidden && (
                            <span className="badge badge--ignored">Hidden</span>
                        )}
                    </div>
                    <span
                        className={`url${urlDisplay.critical ? ' text-critical' : ''}`}
                        title={urlDisplay.critical ? undefined : urlDisplay.text}
                    >
                        {urlDisplay.text}
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
                <td onClick={(e) => e.stopPropagation()}>
                    <QualificationControl
                        prospect={p}
                        isPending={isQualifying}
                        isExpanded={isQualificationExpanded}
                        onToggleExpand={onToggleQualificationExpand}
                        onQualifyStart={onQualifyStart}
                    />
                </td>
                <td>
                    {statusDisplay.kind === 'failed' ? (
                        <Status kind="failed">{statusDisplay.label}</Status>
                    ) : statusDisplay.kind === 'pending' ? (
                        <Status kind="pending">{statusDisplay.label}</Status>
                    ) : statusDisplay.kind === 'warm' ? (
                        <Status kind="warm">{statusDisplay.label}</Status>
                    ) : (
                        <Status kind="ready">{statusDisplay.label}</Status>
                    )}
                </td>
                <td className="col-actions" onClick={(e) => e.stopPropagation()}>
                    <RowActions>
                        <IconButton
                            icon={isExpanded ? Icons.ChevronU : Icons.ChevronD}
                            title="Expand weaknesses"
                            onClick={onToggleExpand}
                        />
                        {p.place_id && !p.place_id.startsWith('direct:') && (
                            <IconButton
                                icon={Icons.Map}
                                title="View on Maps"
                                href={`https://www.google.com/maps/place/?q=place_id:${p.place_id}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                onClick={(e) => e.stopPropagation()}
                            />
                        )}
                        {p.report_url && reportUsable ? (
                            <IconButton
                                icon={Icons.Eye}
                                title="Preview report"
                                href={p.report_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                onClick={(e) => e.stopPropagation()}
                            />
                        ) : (
                            <IconButton icon={Icons.Eye} title="Preview report" disabled />
                        )}
                        <IconButton
                            as={Link}
                            icon={Icons.ChevronR}
                            title="Open prospect"
                            href={`/prospects/${p.id}`}
                            onClick={(e) => e.stopPropagation()}
                        />
                        {p.is_hidden ? (
                            <IconButton
                                icon={Icons.Refresh}
                                title="Restore to results"
                                onClick={onRestore}
                            />
                        ) : (
                            <IconButton
                                icon={Icons.X}
                                title="Hide from results"
                                onClick={onHide}
                            />
                        )}
                    </RowActions>
                </td>
            </tr>
            {isQualificationExpanded && (
                <tr className="expanded-row qualification-expanded-row">
                    <td colSpan={showA11y ? 11 : 8}>
                        <QualificationDetails prospect={p} />
                    </td>
                </tr>
            )}
            {isExpanded && (
                <tr className="expanded-row">
                    <td colSpan={showA11y ? 11 : 8}>
                        <div className="ex-inner">
                            <div className="detail-grid-2">
                                <div>
                                    <div className="eyebrow eyebrow-spaced">GBP weaknesses</div>
                                    <div className="flag-wrap">
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
                                    <div className="eyebrow eyebrow-spaced">Accessibility weaknesses</div>
                                    <div className="flag-wrap">
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
                                <div className="input-row mt-16">
                                    <Button
                                        kind="secondary"
                                        size="sm"
                                        onClick={() => router.post('/outreach/selections', { prospect_ids: [p.id] })}
                                    >
                                        Add to outreach
                                    </Button>
                                    {addableLists.length > 0 && (
                                        <ListPicker
                                            lists={addableLists}
                                            placeholder="Add to list…"
                                            onSelect={addToList}
                                        />
                                    )}
                                </div>
                            )}
                            {inQueue && addableLists.length > 0 && (
                                <div className="input-row mt-16">
                                    <ListPicker
                                        lists={addableLists}
                                        placeholder="Add to list…"
                                        onSelect={addToList}
                                    />
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

    const tone =
        value < 50 ? 'text-critical' :
        value < 70 ? 'text-serious' :
                     'text-positive';

    return (
        <span className={`tabular text-medium ${tone}`}>
            {value}
        </span>
    );
}
