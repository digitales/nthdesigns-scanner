import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SiteAuditSection from '@/Components/audit/SiteAuditSection';
import OutreachEmailCard from '@/Components/OutreachEmailCard';
import {
    AnglePill,
    Button,
    Card,
    PageHeader,
    ScoreCard,
    Status,
} from '@/Components/ui';

export default function ProspectShow({ prospect, search, report, outreachEmails, audit }) {
    const { flash } = usePage().props;
    const [copied, setCopied] = useState(false);

    const generateReport = () => router.post(`/prospects/${prospect.id}/report`);
    const generateOutreach = () => router.post(`/prospects/${prospect.id}/outreach`);
    const addToOutreach = () => router.post('/outreach/selections', { prospect_ids: [prospect.id] });

    const copyReportLink = () => {
        if (!report?.public_url) return;
        navigator.clipboard.writeText(report.public_url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const latestOutreach = outreachEmails[0] ?? null;

    return (
        <AuthenticatedLayout>
            <Head title={prospect.business_name} />

            <main className="page" style={{ maxWidth: 1200 }}>
                <PageHeader
                    eyebrow={`C · ${search.niche} · ${search.city}`}
                    title={prospect.business_name}
                    sub={prospect.address ?? prospect.website_url?.replace(/^https?:\/\//, '')}
                    back={`Back to ${search.niche}`}
                    onBack={() => router.visit(`/searches/${search.id}`)}
                />

                {flash?.success && (
                    <div className="skip-banner" style={{ background: 'var(--color-positive-soft)', marginBottom: 20 }}>
                        {flash.success}
                    </div>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: 32 }}>
                    <div>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 28 }}>
                            <ScoreCard
                                label="Combined"
                                value={prospect.combined_score}
                                highlight={(prospect.combined_score ?? 0) >= 71}
                            />
                            <ScoreCard label="GBP" value={prospect.gbp_score} />
                            <ScoreCard label="Accessibility" value={prospect.a11y_score} />
                        </div>

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
                                    <Button kind="secondary" size="sm" onClick={generateReport} className="mt-4">
                                        Regenerate report
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <p className="micro" style={{ marginBottom: 12 }}>No report yet.</p>
                                    <Button kind="primary" size="sm" onClick={generateReport}>Generate report</Button>
                                </>
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
                                <a
                                    href={`https://www.google.com/maps/place/?q=place_id:${prospect.place_id}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="micro"
                                >
                                    View on Maps
                                </a>
                            </Card>
                        )}

                        <Card title="Profile">
                            <dl style={{ fontSize: 13, display: 'flex', flexDirection: 'column', gap: 8 }}>
                                <div><span className="micro">Angle </span><AnglePill angle={prospect.dominant_angle} /></div>
                                {prospect.phone && <div><span className="micro">Phone </span>{prospect.phone}</div>}
                                {prospect.website_url && (
                                    <div>
                                        <span className="micro">Website </span>
                                        <a href={prospect.website_url} target="_blank" rel="noopener noreferrer" className="micro">
                                            {prospect.website_url.replace(/^https?:\/\//, '')}
                                        </a>
                                    </div>
                                )}
                            </dl>
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
