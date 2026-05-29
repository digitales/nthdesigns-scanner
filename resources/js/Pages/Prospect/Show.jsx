import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SiteAuditSection from '@/Components/audit/SiteAuditSection';
import PageSpeedSection from '@/Components/audit/PageSpeedSection';
import OutreachEmailCard from '@/Components/OutreachEmailCard';
import {
    AnglePill,
    Button,
    Card,
    PageHeader,
    ScoreCard,
    Status,
} from '@/Components/ui';

const LIGHTHOUSE_METRICS = [
    { label: 'LH a11y', key: 'accessibility' },
    { label: 'SEO', key: 'seo' },
    { label: 'Best practices', key: 'best_practices' },
];

export default function ProspectShow({ prospect, search, navigation, report, outreachEmails, audit, lighthouse, pageSpeed, notes = [] }) {
    const { flash } = usePage().props;
    const [copied, setCopied] = useState(false);
    const [editing, setEditing] = useState(false);
    const [form, setForm] = useState({
        business_name: prospect.business_name ?? '',
        phone: prospect.phone ?? '',
        website_url: prospect.website_url ?? '',
        address: prospect.address ?? '',
    });
    const [noteBody, setNoteBody] = useState('');

    const generateReport = () => router.post(`/prospects/${prospect.id}/report`);
    const reauditSite = () => router.post(`/prospects/${prospect.id}/audit`, {}, { preserveScroll: true });
    const generateOutreach = () => router.post(`/prospects/${prospect.id}/outreach`);
    const addToOutreach = () => router.post('/outreach/selections', { prospect_ids: [prospect.id] });

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

    const auditPending = prospect.audit_status === 'pending';
    const canReauditSite = Boolean(prospect.website_url)
        && !auditPending
        && ['accessibility_only', 'combined'].includes(search.scan_type);
    const latestOutreach = outreachEmails[0] ?? null;

    return (
        <AuthenticatedLayout>
            <Head title={prospect.business_name} />

            <main className="page" style={{ maxWidth: 1200 }}>
                <PageHeader
                    eyebrow={`C · ${search.niche} · ${search.city}`}
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

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: 32 }}>
                    <div>
                        <div className="score-card-row">
                            <ScoreCard
                                label="Combined"
                                value={prospect.combined_score}
                                unit="/100"
                                highlight={(prospect.combined_score ?? 0) >= 71}
                            />
                            <ScoreCard label="GBP" value={prospect.gbp_score} unit="/100" />
                            <ScoreCard label="Accessibility" value={prospect.a11y_score} unit="/100" />
                            {search.scan_type !== 'gbp_only' && (
                                <ScoreCard
                                    label="Page speed"
                                    value={prospect.performance_score > 0 ? prospect.performance_score : null}
                                    healthScore
                                    unit="/100"
                                />
                            )}
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

                        {search.scan_type !== 'gbp_only'
                            && prospect.audit_status === 'complete'
                            && prospect.performance_score > 0
                            && !pageSpeed && (
                            <p className="micro" style={{ marginTop: -16, marginBottom: 28, color: 'var(--color-stone-500)' }}>
                                Re-run site audit for Core Web Vitals breakdown
                            </p>
                        )}

                        <Card title="Weakness flags" style={{ marginBottom: 24 }}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 32 }}>
                                <div>
                                    <div className="eyebrow" style={{ marginBottom: 10 }}>GBP</div>
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                        {(prospect.gbp_flags ?? []).length === 0 ? (
                                            <span className="micro">None flagged</span>
                                        ) : (
                                            (prospect.gbp_flags ?? []).map((flag, i) => (
                                                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                    <span style={{ width: 6, height: 6, background: 'var(--color-stone-400)', borderRadius: '50%' }} />
                                                    <span style={{ fontSize: 13 }}>{flag}</span>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <div className="eyebrow" style={{ marginBottom: 10 }}>Accessibility</div>
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                        {(prospect.a11y_flags ?? []).length === 0 ? (
                                            <span className="micro">None flagged</span>
                                        ) : (
                                            (prospect.a11y_flags ?? []).map((flag, i) => (
                                                <div key={i} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                        <span style={{ width: 6, height: 6, background: 'var(--color-sev-serious)', transform: 'rotate(45deg)' }} />
                                                        <span style={{ fontSize: 13 }}>{flag}</span>
                                                    </div>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>
                            </div>
                        </Card>

                        <PageSpeedSection pageSpeed={pageSpeed} />

                        {canReauditSite && (
                            <div style={{ marginBottom: 24 }}>
                                <Button kind="secondary" size="sm" onClick={reauditSite}>
                                    Re-run site audit
                                </Button>
                                <p className="micro" style={{ marginTop: 8, color: 'var(--color-stone-500)' }}>
                                    Re-audits the website only. GBP scores are unchanged and no Google Places API calls are made.
                                </p>
                            </div>
                        )}

                        <SiteAuditSection audit={audit} />

                        {outreachEmails.length > 0 && (
                            <section>
                                <div className="card-title">Outreach emails</div>
                                {outreachEmails.map((email) => (
                                    <div key={email.id} style={{ marginBottom: 16 }}>
                                        <OutreachEmailCard
                                            email={{ ...email, combined_score: prospect.combined_score }}
                                            reportUrl={report?.public_url}
                                            performanceScore={prospect.performance_score}
                                        />
                                    </div>
                                ))}
                            </section>
                        )}
                    </div>

                    <aside style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <Card title="Public report">
                            {auditPending && (
                                <p className="micro" style={{ marginBottom: 8, color: 'var(--color-stone-500)' }}>
                                    Site audit in progress…
                                </p>
                            )}
                            {report ? (
                                <>
                                    <div className="micro" style={{ marginBottom: 8, wordBreak: 'break-all' }}>/r/{report.token}</div>
                                    <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
                                        <Button kind="secondary" size="sm" onClick={copyReportLink}>
                                            {copied ? 'Copied' : 'Copy link'}
                                        </Button>
                                        <a href={report.public_url} target="_blank" rel="noopener noreferrer">
                                            <Button kind="ghost" size="sm">Preview</Button>
                                        </a>
                                    </div>
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
                                        disabled={auditPending}
                                    >
                                        Regenerate report
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
                                    <Button kind="primary" size="sm" onClick={generateReport} disabled={auditPending}>
                                        Generate report
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
                                    <Button kind="primary" size="sm" onClick={addToOutreach}>Add to outreach</Button>
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
                                <form onSubmit={saveDetails} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
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
                                    <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                                        <Button kind="primary" size="sm" type="submit">Save</Button>
                                        <Button kind="ghost" size="sm" type="button" onClick={() => setEditing(false)}>Cancel</Button>
                                    </div>
                                </form>
                            ) : (
                                <>
                                    <dl style={{ fontSize: 13, display: 'flex', flexDirection: 'column', gap: 8 }}>
                                        <div><span className="micro">Angle </span><AnglePill angle={prospect.dominant_angle} /></div>
                                        <div>
                                            <span className="micro">Phone </span>
                                            {prospect.phone || '—'}
                                        </div>
                                        <div>
                                            <span className="micro">Website </span>
                                            {prospect.website_url ? (
                                                <a href={prospect.website_url} target="_blank" rel="noopener noreferrer" className="micro">
                                                    {prospect.website_url.replace(/^https?:\/\//, '')}
                                                </a>
                                            ) : (
                                                '—'
                                            )}
                                        </div>
                                        {prospect.address && (
                                            <div><span className="micro">Address </span>{prospect.address}</div>
                                        )}
                                    </dl>
                                    <Button kind="secondary" size="sm" onClick={() => setEditing(true)} className="mt-4">
                                        Edit details
                                    </Button>
                                </>
                            )}
                        </Card>

                        <Card title="Private notes">
                            <p className="micro" style={{ marginBottom: 12 }}>Not included on public reports.</p>
                            {notes.length === 0 ? (
                                <p className="micro" style={{ marginBottom: 12 }}>No notes yet.</p>
                            ) : (
                                <ul style={{ listStyle: 'none', padding: 0, margin: '0 0 16px', display: 'flex', flexDirection: 'column', gap: 12 }}>
                                    {notes.map((n) => (
                                        <li key={n.id} style={{ borderBottom: '1px solid var(--color-stone-200)', paddingBottom: 12 }}>
                                            <p style={{ fontSize: 13, margin: '0 0 4px', whiteSpace: 'pre-wrap' }}>{n.body}</p>
                                            <span className="micro">{n.author} · {n.created_at}</span>
                                        </li>
                                    ))}
                                </ul>
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
                    </aside>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function ViewSparkline({ viewCount }) {
    const bars = Array.from({ length: 14 }, (_, i) => {
        const active = i >= 14 - Math.min(viewCount, 14);
        return active;
    });

    return (
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 3, height: 28 }}>
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
        </div>
    );
}
