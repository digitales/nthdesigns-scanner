import { Head } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';
import CmsBadge from '@/Components/cms/CmsBadge';
import CpcBenchmarkPanel from '@/Components/Search/CpcBenchmarkPanel';
import {
    AnglePill,
    Badge,
    DataTable,
    EmptyState,
    Field,
    FilterBar,
    IconButton,
    Icons,
    Input,
    RowActions,
    ScoreBadge,
    Segmented,
    Status,
} from '@/Components/ui';
import { normalizeAngle } from '@/Components/ui/scoreBand';
import { showA11yForSearch } from '@/utils/auditVisibility';
import { prospectUrlDisplay } from '@/utils/prospectAuditDisplay';

const CPC_SOURCE_LABELS = {
    manual: 'Manual',
    google_ads: 'Google Ads',
    market_default: 'Market default',
    keyword_planner_csv: 'Keyword Planner CSV',
};

export default function SharedSearchShow({ search = {}, prospects = [], sharedAt }) {
    const [expanded, setExpanded] = useState(null);
    const [angleFilter, setAngleFilter] = useState('all');
    const [minScore, setMinScore] = useState(0);

    const isDirectUrl = search.source === 'direct_url';
    const showA11y = showA11yForSearch(search.scan_type);
    const sharedDate = sharedAt ? new Date(sharedAt).toLocaleDateString('en-GB') : '';
    const directHost = search.submitted_url?.replace(/^https?:\/\//, '') ?? 'Single site';
    const pageTitle = isDirectUrl ? directHost : `${search.niche} in ${search.city}`;
    const prospectCount = search.prospect_count ?? prospects.length;
    const cpcSourceLabel = CPC_SOURCE_LABELS[search.cpc_source] ?? search.cpc_source;

    const visible = useMemo(() => {
        return prospects.filter((p) => {
            if (minScore > 0 && (p.combined_score ?? 0) < minScore) return false;
            if (angleFilter === 'all') return true;
            return normalizeAngle(p.dominant_angle) === angleFilter;
        });
    }, [prospects, minScore, angleFilter]);

    return (
        <>
            <Head title={pageTitle}>
                <meta name="robots" content="noindex,nofollow" />
            </Head>

            <main className="page page-wide public-sheet">
                <header className="public-sheet-header mb-24">
                    <div className="micro text-stone">Shared search results</div>
                    <h1 className="page-title">{pageTitle}</h1>
                    <div className="micro">
                        {prospectCount} prospect{prospectCount === 1 ? '' : 's'}
                        {sharedDate ? ` · Snapshot from ${sharedDate}` : ''}
                    </div>
                </header>

                {!isDirectUrl && (
                    <CpcBenchmarkPanel
                        readOnly
                        niche={search.niche}
                        city={search.city}
                        cpcSource={search.cpc_source}
                        cpcSourceLabel={cpcSourceLabel}
                        cpcBenchmark={search.cpc_benchmark ?? ''}
                        cpcKeywords={search.cpc_keywords ?? []}
                    />
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
                        <span className="micro">Showing {visible.length} of {prospects.length}</span>
                    </div>
                </FilterBar>

                {visible.length === 0 ? (
                    <EmptyState
                        icon={Icons.Search}
                        title="No prospects match these filters."
                        sub="Try lowering the minimum score or clearing the angle filter."
                    />
                ) : (
                    <DataTable tableClassName="ptable--prospects ptable--paper">
                        <thead>
                            <tr>
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
                                <th className="col-report">Report</th>
                                <th className="col-actions">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visible.map((p, index) => (
                                <ProspectRow
                                    key={index}
                                    prospect={p}
                                    showA11y={showA11y}
                                    isExpanded={expanded === index}
                                    onToggleExpand={() => setExpanded(expanded === index ? null : index)}
                                />
                            ))}
                        </tbody>
                    </DataTable>
                )}

                <p className="micro text-stone mt-24">
                    Snapshot from {sharedDate || 'share date'}. Contact details and operator tools are not included.
                </p>
            </main>
        </>
    );
}

function ProspectRow({ prospect: p, showA11y, isExpanded, onToggleExpand }) {
    const isFailed = p.audit_status === 'failed';
    const isPending = p.audit_status === 'pending';
    const urlDisplay = prospectUrlDisplay(p);

    const rowClass = [
        isFailed ? 'failed' : '',
        isExpanded ? 'expanded' : '',
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <Fragment>
            <tr className={rowClass}>
                <td className="biz">
                    <div className="biz-title-row">{p.business_name}</div>
                    {p.website_url && urlDisplay.showUrl ? (
                        <a
                            className="url"
                            href={p.website_url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {urlDisplay.text}
                        </a>
                    ) : (
                        <span
                            className={`url${urlDisplay.critical ? ' text-critical' : ''}`}
                        >
                            {urlDisplay.text}
                        </span>
                    )}
                </td>
                <td>
                    <ScoreBadge value={p.combined_score} />
                </td>
                {showA11y && (
                    <>
                        <td className="num">{p.gbp_score ?? '—'}</td>
                        <td className="num">{isPending ? '…' : (p.a11y_score ?? '—')}</td>
                        <td className="num">
                            <PerfScore value={p.performance_score} />
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
                        <Status kind="failed">{isSiteUnreachable ? 'Site unreachable' : 'Audit failed'}</Status>
                    ) : isPending ? (
                        <Status kind="pending">Auditing</Status>
                    ) : !p.report_url ? (
                        <Status kind="pending">No report</Status>
                    ) : (
                        <a href={p.report_url} target="_blank" rel="noopener noreferrer" className="micro">
                            View report
                        </a>
                    )}
                </td>
                <td className="col-actions">
                    <RowActions>
                        <IconButton
                            icon={isExpanded ? Icons.ChevronU : Icons.ChevronD}
                            title="Expand weaknesses"
                            onClick={onToggleExpand}
                        />
                        {p.report_url && !isFailed && !isPending ? (
                            <IconButton
                                icon={Icons.Eye}
                                title="Preview report"
                                href={p.report_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            />
                        ) : (
                            <IconButton icon={Icons.Eye} title="Preview report" disabled />
                        )}
                    </RowActions>
                </td>
            </tr>
            {isExpanded && (
                <tr className="expanded-row">
                    <td colSpan={showA11y ? 8 : 5}>
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
                        </div>
                    </td>
                </tr>
            )}
        </Fragment>
    );
}

function PerfScore({ value }) {
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
