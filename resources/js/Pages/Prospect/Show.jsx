import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useProgressReload } from '@/hooks/useProgressReload';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AuditFailureSection from '@/Components/audit/AuditFailureSection';
import SiteAuditSection from '@/Components/audit/SiteAuditSection';
import TechnologySection from '@/Components/cms/TechnologySection';
import PageSpeedSection from '@/Components/audit/PageSpeedSection';
import OutreachEmailCard from '@/Components/OutreachEmailCard';
import { shouldShowA11yAudit } from '@/utils/auditVisibility';
import {
    AnglePill,
    Button,
    Card,
    Grid,
    Page,
    PageHeader,
    ScoreCard,
    SidebarLayout,
    Stack,
    Status,
} from '@/Components/ui';

const LIGHTHOUSE_METRICS = [
    { label: 'LH a11y', key: 'accessibility' },
    { label: 'SEO', key: 'seo' },
    { label: 'Best practices', key: 'best_practices' },
];

export default function ProspectShow({
    prospect,
    search,
    navigation,
    report,
    outreachEmails,
    auditFailure,
    audit,
    cms,
    lighthouse,
    pageSpeed,
    notes = [],
    ignored = null,
    ignoreReasons = [],
    progress_flow: progressFlow = {},
}) {
    const { flash } = usePage().props;
    const [copied, setCopied] = useState(false);
    const [editing, setEditing] = useState(false);
    const [showIgnoreForm, setShowIgnoreForm] = useState(false);
    const [ignoreReason, setIgnoreReason] = useState('acquired');
    const [ignoreNote, setIgnoreNote] = useState('');
    const [form, setForm] = useState({
        business_name: prospect.business_name ?? '',
        phone: prospect.phone ?? '',
        website_url: prospect.website_url ?? '',
        address: prospect.address ?? '',
    });
    const [noteBody, setNoteBody] = useState('');
    const [actionProcessing, setActionProcessing] = useState(null);

    const postAction = (key, url, options = {}) => {
        if (actionProcessing) {
            return;
        }

        setActionProcessing(key);
        router.post(url, options.data ?? {}, {
            preserveScroll: options.preserveScroll ?? false,
            onFinish: () => setActionProcessing(null),
        });
    };

    const generateReport = () => postAction('report', `/prospects/${prospect.id}/report`);
    const reauditSite = () => postAction('audit', `/prospects/${prospect.id}/audit`, { preserveScroll: true });
    const generateOutreach = () => postAction('outreach', `/prospects/${prospect.id}/outreach`);
    const addToOutreach = () => postAction('selection', '/outreach/selections', {
        data: { prospect_ids: [prospect.id] },
    });

    const copyReportLink = () => {
        if (!report?.public_url) return;
        navigator.clipboard.writeText(report.public_url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const saveDetails = (e) => {
        e.preventDefault();
        router.patch(`/prospects/${prospect.id}`, form, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const addNote = (e) => {
        e.preventDefault();
        if (!noteBody.trim()) return;
        router.post(`/prospects/${prospect.id}/notes`, { body: noteBody }, {
            preserveScroll: true,
            onSuccess: () => setNoteBody(''),
        });
    };

    const ignoreProspect = (e) => {
        e.preventDefault();
        router.post(`/prospects/${prospect.id}/ignore`, {
            reason: ignoreReason,
            note: ignoreNote.trim() || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowIgnoreForm(false);
                setIgnoreNote('');
            },
        });
    };

    const unignoreProspect = () => {
        router.delete(`/prospects/${prospect.id}/ignore`, { preserveScroll: true });
    };

    const auditPending = prospect.audit_status === 'pending';
    const auditSkipped = prospect.audit_status === 'skipped';
    const hasSiteAudit = Boolean(audit);
    const effectiveScanType = search.effective_scan_type ?? search.scan_type;
    const isGbpOnlySearch = search.scan_type === 'gbp_only';
    const showA11y = shouldShowA11yAudit({
        scanType: search.scan_type,
        effectiveScanType,
        auditPending,
        auditFailure,
    });
    const a11yAuditMessage = progressFlow.status_message ?? 'Running accessibility audit';
    const canRunSiteAudit = Boolean(prospect.website_url)
        && !auditPending
        && ['accessibility_only', 'combined', 'gbp_only'].includes(search.scan_type);

    useProgressReload(
        auditPending && showA11y,
        ['prospect', 'audit', 'auditFailure', 'lighthouse', 'pageSpeed', 'progress_flow', 'report'],
    );
    const latestOutreach = outreachEmails[0] ?? null;
    const isDirectUrl = search.source === 'direct_url';
    const directHost = search.submitted_url?.replace(/^https?:\/\//, '') ?? 'Single site';
    const eyebrow = isDirectUrl
        ? `C · Single site · ${directHost}`
        : `C · ${search.niche} · ${search.city}`;

    return (
        <AuthenticatedLayout>
            <Head title={prospect.business_name} />

            <Page width="wide">
                <PageHeader
                    eyebrow={eyebrow}
                    title={prospect.business_name}
                    sub={prospect.address ?? prospect.website_url?.replace(/^https?:\/\//, '')}
                    back={navigation.back_label}
                    onBack={() => router.visit(navigation.back_href)}
                />

                {flash?.success && (
                    <div className="skip-banner" style={{ background: 'var(--color-positive-soft)', marginBottom: 20 }}>
                        {flash.success}
                    </div>
                )}

                {ignored && (
                    <div
                        className="skip-banner"
                        style={{
                            background: 'var(--color-stone-100)',
                            border: '1px solid var(--color-stone-300)',
                            marginBottom: 20,
                        }}
                    >
                        <Stack direction="row" justify="between" align="start" gap={16}>
                            <div>
                                <strong style={{ fontSize: 13 }}>Ignored from future scans</strong>
                                <p className="micro" style={{ margin: '4px 0 0' }}>
                                    {ignored.reason_label}
                                    {ignored.note ? ` — ${ignored.note}` : ''}
                                    {' · '}
                                    {ignored.ignored_at}
                                </p>
                            </div>
                            <Stack direction="row" gap={8} className="stack--shrink-0">
                                <Button kind="ghost" size="sm" onClick={() => router.visit('/ignored')}>
                                    View all ignored
                                </Button>
                                <Button kind="ghost" size="sm" onClick={unignoreProspect}>
                                    Undo ignore
                                </Button>
                            </Stack>
                        </Stack>
                    </div>
                )}

                <SidebarLayout>
                    <div>
                        <div className="score-card-stack">
                            <div
                                className="score-card-row score-card-row--primary"
                                style={!showA11y ? { gridTemplateColumns: 'repeat(2, minmax(0, 1fr))' } : undefined}
                            >
                                <ScoreCard
                                    label="Combined"
                                    value={prospect.combined_score}
                                    unit="/100"
                                    highlight={(prospect.combined_score ?? 0) >= 71}
                                />
                                <ScoreCard label="GBP" value={prospect.gbp_score} unit="/100" />
                                {showA11y && (
                                    <ScoreCard
                                        label="Accessibility"
                                        value={auditPending || auditSkipped ? null : prospect.a11y_score}
                                        unit="/100"
                                        pendingLabel={auditPending ? a11yAuditMessage : null}
                                    />
                                )}
                            </div>
                            {showA11y && (
                                <div className="score-card-row score-card-row--speed">
                                    <ScoreCard
                                        label="Page speed"
                                        value={prospect.performance_score > 0 ? prospect.performance_score : null}
                                        healthScore
                                        unit="/100"
                                    />
                                    {LIGHTHOUSE_METRICS.map(({ label, key }) => {
                                        const score = lighthouse?.[key];
                                        if (score == null) return null;
                                        return (
                                            <ScoreCard
                                                key={key}
                                                label={label}
                                                value={score}
                                                healthScore
                                                unit="/100"
                                            />
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        <AuditFailureSection auditFailure={auditFailure} />

                        {showA11y
                            && prospect.audit_status === 'complete'
                            && prospect.performance_score > 0
                            && !pageSpeed && (
                            <p className="micro" style={{ marginTop: -16, marginBottom: 28, color: 'var(--color-stone-500)' }}>
                                Re-run site audit for Core Web Vitals breakdown
                            </p>
                        )}

                        {auditPending && showA11y && (
                            <p className="micro" style={{ marginTop: -16, marginBottom: 28, color: 'var(--color-stone-500)' }}>
                                Site audit in progress — scores update automatically when complete.
                            </p>
                        )}

                        <Card title="Weakness flags" style={{ marginBottom: 24 }}>
                            <Grid cols={showA11y ? 2 : 1} gap={32}>
                                <div>
                                    <div className="eyebrow" style={{ marginBottom: 10 }}>GBP</div>
                                    <Stack gap={8}>
                                        {(prospect.gbp_flags ?? []).length === 0 ? (
                                            <span className="micro">None flagged</span>
                                        ) : (
                                            (prospect.gbp_flags ?? []).map((flag, i) => (
                                                <Stack key={i} direction="row" gap={8} align="center">
                                                    <span style={{ width: 6, height: 6, background: 'var(--color-stone-400)', borderRadius: '50%' }} />
                                                    <span style={{ fontSize: 13 }}>{flag}</span>
                                                </Stack>
                                            ))
                                        )}
                                    </Stack>
                                </div>
                                {showA11y && (
                                    <div>
                                        <div className="eyebrow" style={{ marginBottom: 10 }}>Accessibility</div>
                                        <Stack gap={8}>
                                            {auditPending ? (
                                                <Status kind="pending">{a11yAuditMessage}</Status>
                                            ) : auditSkipped ? (
                                                <span className="micro">
                                                    {prospect.website_url
                                                        ? 'Site audit skipped.'
                                                        : 'Site audit skipped — no website URL to audit.'}
                                                </span>
                                            ) : (prospect.a11y_flags ?? []).length === 0 ? (
                                                <span className="micro">None flagged</span>
                                            ) : (
                                                (prospect.a11y_flags ?? []).map((flag, i) => (
                                                    <Stack key={i} direction="row" justify="between" align="center" gap={8}>
                                                        <Stack direction="row" gap={8} align="center">
                                                            <span style={{ width: 6, height: 6, background: 'var(--color-sev-serious)', transform: 'rotate(45deg)' }} />
                                                            <span style={{ fontSize: 13 }}>{flag}</span>
                                                        </Stack>
                                                    </Stack>
                                                ))
                                            )}
                                        </Stack>
                                    </div>
                                )}
                            </Grid>
                        </Card>

                        <PageSpeedSection pageSpeed={pageSpeed} />

                        {canRunSiteAudit && (
                            <div style={{ marginBottom: 24 }}>
                                <Button
                                    kind="secondary"
                                    size="sm"
                                    onClick={reauditSite}
                                    disabled={actionProcessing === 'audit'}
                                >
                                    {actionProcessing === 'audit'
                                        ? 'Queuing…'
                                        : hasSiteAudit
                                          ? 'Re-run site audit'
                                          : 'Run site audit'}
                                </Button>
                                <p className="micro" style={{ marginTop: 8, color: 'var(--color-stone-500)' }}>
                                    {isGbpOnlySearch && !hasSiteAudit
                                        ? 'This prospect was scanned GBP-only. Run a site audit to upgrade it to a full combined audit (accessibility + page speed).'
                                        : 'Re-audits the website only. GBP scores are unchanged and no Google Places API calls are made.'}
                                </p>
                            </div>
                        )}

                        <SiteAuditSection audit={audit} />

                        {outreachEmails.length > 0 && (
                            <section>
                                <div className="card-title">Outreach emails</div>
                                <Stack gap={16}>
                                {outreachEmails.map((email) => (
                                    <div key={email.id}>
                                        <OutreachEmailCard
                                            email={{ ...email, combined_score: prospect.combined_score }}
                                            reportUrl={report?.public_url}
                                            performanceScore={prospect.performance_score}
                                        />
                                    </div>
                                ))}
                                </Stack>
                            </section>
                        )}
                    </div>

                    <div>
                        <Card title="Public report">
                            {auditPending && (
                                <p className="micro" style={{ marginBottom: 8, color: 'var(--color-stone-500)' }}>
                                    Site audit in progress…
                                </p>
                            )}
                            {report ? (
                                <>
                                    <div className="micro" style={{ marginBottom: 8, wordBreak: 'break-all' }}>/r/{report.token}</div>
                                    {report.booking && (
                                        <p className="micro" style={{ color: 'var(--color-positive)', marginBottom: 8 }}>
                                            Booked · {report.booking.label} · {report.booking.attendee_name}
                                        </p>
                                    )}
                                    <Stack direction="row" gap={8} style={{ marginBottom: 16 }}>
                                        <Button kind="secondary" size="sm" onClick={copyReportLink}>
                                            {copied ? 'Copied' : 'Copy link'}
                                        </Button>
                                        <a href={report.public_url} target="_blank" rel="noopener noreferrer">
                                            <Button kind="ghost" size="sm">Preview</Button>
                                        </a>
                                    </Stack>
                                    <div className="micro" style={{ marginBottom: 8 }}>
                                        {report.view_count === 0
                                            ? 'Not yet opened'
                                            : `${report.view_count} view${report.view_count !== 1 ? 's' : ''}`}
                                    </div>
                                    <ViewSparkline viewCount={report.view_count} />
                                    <Button
                                        kind="secondary"
                                        size="sm"
                                        onClick={generateReport}
                                        className="mt-4"
                                        disabled={auditPending || actionProcessing === 'report'}
                                    >
                                        {actionProcessing === 'report' ? 'Queuing…' : 'Regenerate report'}
                                    </Button>
                                    {prospect.audit_status === 'complete' && (
                                        <p className="micro" style={{ marginTop: 8 }}>
                                            Regenerate after editing the website to refresh audit results in the report.
                                        </p>
                                    )}
                                </>
                            ) : (
                                <>
                                    <p className="micro" style={{ marginBottom: 12 }}>No report yet.</p>
                                    <Button
                                        kind="primary"
                                        size="sm"
                                        onClick={generateReport}
                                        disabled={auditPending || actionProcessing === 'report'}
                                    >
                                        {actionProcessing === 'report' ? 'Queuing…' : 'Generate report'}
                                    </Button>
                                </>
                            )}
                            {prospect.audit_status === 'failed' && (
                                <p className="micro" style={{ marginTop: 8, color: 'var(--color-sev-serious)' }}>
                                    Site audit failed. Use Re-run site audit above, or fix the website URL and save.
                                </p>
                            )}
                        </Card>

                        <Card title="Outreach">
                            {latestOutreach ? (
                                <>
                                    <div style={{ fontSize: 13, fontWeight: 500, marginBottom: 4 }}>{latestOutreach.subject_line}</div>
                                    <Status kind={latestOutreach.sent_at ? 'ready' : 'pending'}>
                                        {latestOutreach.sent_at ? 'Sent' : 'Drafted'}
                                    </Status>
                                </>
                            ) : (
                                <>
                                    <p className="micro" style={{ marginBottom: 12 }}>No email drafted.</p>
                                    <Button
                                        kind="primary"
                                        size="sm"
                                        onClick={addToOutreach}
                                        disabled={actionProcessing === 'selection'}
                                    >
                                        {actionProcessing === 'selection' ? 'Adding…' : 'Add to outreach'}
                                    </Button>
                                </>
                            )}
                        </Card>

                        {prospect.place_id && (
                            <Card title="Location">
                                {prospect.address && (
                                    <p style={{ fontSize: 13, marginBottom: 8, lineHeight: 1.45 }}>
                                        {prospect.address}
                                    </p>
                                )}
                                {prospect.place_id && !prospect.place_id.startsWith('direct:') && (
                                <a
                                    href={`https://www.google.com/maps/place/?q=place_id:${prospect.place_id}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="micro"
                                >
                                    View on Maps
                                </a>
                                )}
                            </Card>
                        )}

                        <Card title="Profile">
                            {editing ? (
                                <Stack as="form" gap={10} onSubmit={saveDetails}>
                                    <label className="micro">Business name</label>
                                    <input
                                        className="input"
                                        value={form.business_name}
                                        onChange={(e) => setForm({ ...form, business_name: e.target.value })}
                                        required
                                    />
                                    <label className="micro">Phone</label>
                                    <input
                                        className="input"
                                        value={form.phone}
                                        onChange={(e) => setForm({ ...form, phone: e.target.value })}
                                    />
                                    <label className="micro">Website</label>
                                    <input
                                        className="input"
                                        type="url"
                                        value={form.website_url}
                                        onChange={(e) => setForm({ ...form, website_url: e.target.value })}
                                        placeholder="https://..."
                                    />
                                    <label className="micro">Address</label>
                                    <input
                                        className="input"
                                        value={form.address}
                                        onChange={(e) => setForm({ ...form, address: e.target.value })}
                                    />
                                    <Stack direction="row" gap={8} style={{ marginTop: 8 }}>
                                        <Button kind="primary" size="sm" type="submit">Save</Button>
                                        <Button kind="ghost" size="sm" type="button" onClick={() => setEditing(false)}>Cancel</Button>
                                    </Stack>
                                </Stack>
                            ) : (
                                <>
                                    <Stack as="dl" gap={8} style={{ fontSize: 13 }}>
                                        <div><span className="micro">Angle </span><AnglePill angle={prospect.dominant_angle} /></div>
                                        <div>
                                            <span className="micro">Phone </span>
                                            {prospect.phone || '—'}
                                        </div>
                                        <div>
                                            <span className="micro">Website </span>
                                            {prospect.website_url ? (
                                                <>
                                                    <a href={prospect.website_url} target="_blank" rel="noopener noreferrer" className="micro">
                                                        {prospect.website_url.replace(/^https?:\/\//, '')}
                                                    </a>
                                                    {(prospect.website_url_source === 'google_cse' || prospect.website_url_source === 'brave') && (
                                                        <div className="micro" style={{ marginTop: 4, color: 'var(--color-stone-500)' }}>
                                                            Found via web search
                                                            {prospect.website_discovery_confidence === 'high'
                                                                ? ' · High confidence'
                                                                : prospect.website_discovery_confidence === 'medium'
                                                                  ? ' · Medium confidence'
                                                                  : ''}
                                                        </div>
                                                    )}
                                                    {prospect.website_url_source === 'operator' && (
                                                        <div className="micro" style={{ marginTop: 4, color: 'var(--color-stone-500)' }}>
                                                            Edited manually
                                                        </div>
                                                    )}
                                                </>
                                            ) : (
                                                '—'
                                            )}
                                        </div>
                                        {prospect.address && (
                                            <div><span className="micro">Address </span>{prospect.address}</div>
                                        )}
                                    </Stack>
                                    <Button kind="secondary" size="sm" onClick={() => setEditing(true)} className="mt-4">
                                        Edit details
                                    </Button>
                                </>
                            )}
                        </Card>

                        <TechnologySection cms={cms} />

                        {!ignored && (
                            <Card title="Ignore prospect">
                                <p className="micro" style={{ marginBottom: 12 }}>
                                    Skip this business in future niche and city scans. Use for acquisitions, cold leads, or failed outreach.
                                </p>
                                {showIgnoreForm ? (
                                    <Stack as="form" gap={10} onSubmit={ignoreProspect}>
                                        <label className="micro">Reason</label>
                                        <select
                                            className="input"
                                            value={ignoreReason}
                                            onChange={(e) => setIgnoreReason(e.target.value)}
                                        >
                                            {ignoreReasons.map((r) => (
                                                <option key={r.value} value={r.value}>{r.label}</option>
                                            ))}
                                        </select>
                                        <label className="micro">Note (optional)</label>
                                        <textarea
                                            className="textarea"
                                            rows={2}
                                            value={ignoreNote}
                                            onChange={(e) => setIgnoreNote(e.target.value)}
                                            placeholder="e.g. Acquired by Gallagher in 2024"
                                            style={{ width: '100%' }}
                                        />
                                        <Stack direction="row" gap={8}>
                                            <Button kind="primary" size="sm" type="submit">Ignore from scans</Button>
                                            <Button kind="ghost" size="sm" type="button" onClick={() => setShowIgnoreForm(false)}>
                                                Cancel
                                            </Button>
                                        </Stack>
                                    </Stack>
                                ) : (
                                    <Button kind="secondary" size="sm" onClick={() => setShowIgnoreForm(true)}>
                                        Ignore prospect…
                                    </Button>
                                )}
                            </Card>
                        )}

                        <Card title="Private notes">
                            <p className="micro" style={{ marginBottom: 12 }}>Not included on public reports.</p>
                            {notes.length === 0 ? (
                                <p className="micro" style={{ marginBottom: 12 }}>No notes yet.</p>
                            ) : (
                                <Stack as="ul" gap={12} className="meta-list" style={{ marginBottom: 16 }}>
                                    {notes.map((n) => (
                                        <li key={n.id} style={{ borderBottom: '1px solid var(--color-stone-200)', paddingBottom: 12 }}>
                                            <p style={{ fontSize: 13, margin: '0 0 4px', whiteSpace: 'pre-wrap' }}>{n.body}</p>
                                            <span className="micro">{n.author} · {n.created_at}</span>
                                        </li>
                                    ))}
                                </Stack>
                            )}
                            <form onSubmit={addNote}>
                                <textarea
                                    className="textarea"
                                    rows={3}
                                    value={noteBody}
                                    onChange={(e) => setNoteBody(e.target.value)}
                                    placeholder="Add a note…"
                                    style={{ width: '100%', marginBottom: 8 }}
                                />
                                <Button kind="secondary" size="sm" type="submit" disabled={!noteBody.trim()}>
                                    Add note
                                </Button>
                            </form>
                        </Card>
                    </div>
                </SidebarLayout>
            </Page>
        </AuthenticatedLayout>
    );
}

function ViewSparkline({ viewCount }) {
    const bars = Array.from({ length: 14 }, (_, i) => {
        const active = i >= 14 - Math.min(viewCount, 14);
        return active;
    });

    return (
        <Stack direction="row" align="end" gap={3} style={{ height: 28 }}>
            {bars.map((active, i) => (
                <span
                    key={i}
                    style={{
                        width: 6,
                        height: active ? 20 : 8,
                        background: active ? 'var(--color-accent)' : 'var(--color-stone-200)',
                        borderRadius: 1,
                    }}
                />
            ))}
        </Stack>
    );
}
