import { Head } from '@inertiajs/react';
import ViolationCard from '@/Components/audit/ViolationCard';
import LighthouseDial from '@/Components/audit/LighthouseDial';
import { DataTable, LinkButton, SevChip } from '@/Components/ui';
import { gradeColor } from '@/Components/ui/scoreBand';

export default function PublicReport({ report }) {
    const p = report.prospect ?? {};
    const benchmark = report.benchmark;
    const summary = report.violation_summary ?? {};
    const lighthouse = report.lighthouse ?? {};
    const grade = report.grade ?? 'C';
    const color = gradeColor(report.combined_score ?? 0);
    const hasA11y = (summary.total ?? 0) > 0 || (report.top_violations?.length ?? 0) > 0;
    const hasLighthouse = lighthouse.performance != null
        || lighthouse.accessibility != null
        || lighthouse.seo != null
        || lighthouse.best_practices != null;
    const hasGbp = benchmark != null;

    return (
        <>
            <Head title={`${report.business_name} — Independent audit`} />

            <div className="public-report-wrap">
                <article className="public-report">
                    <header style={{ padding: '56px 80px 40px', borderBottom: '1px solid var(--color-line)' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 48 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                <span className="brand-mark" style={{ width: 22, height: 22 }} />
                                <span style={{ fontFamily: 'var(--font-serif)', fontStyle: 'italic', fontSize: 17 }}>nthdesigns</span>
                            </div>
                            <div className="micro" style={{ textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                                Audit · {new Date(report.generated_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
                            </div>
                        </div>

                        <div className="eyebrow" style={{ marginBottom: 14 }}>Independent audit · WCAG 2.2 + Google Business Profile</div>
                        <h1 style={{
                            fontFamily: 'var(--font-serif)',
                            fontWeight: 400,
                            fontSize: 56,
                            lineHeight: 1.05,
                            letterSpacing: '-0.022em',
                            margin: '0 0 18px',
                        }}>
                            {report.business_name}
                        </h1>
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: 14, flexWrap: 'wrap', color: 'var(--color-stone-600)', fontSize: 14 }}>
                            {report.website_url && (
                                <span className="micro" style={{ fontSize: 13, color: 'var(--color-stone-700)' }}>
                                    {report.website_url.replace(/^https?:\/\//, '')}
                                </span>
                            )}
                            {report.address && (
                                <>
                                    <span style={{ color: 'var(--color-stone-400)' }}>·</span>
                                    <span>{report.address}</span>
                                </>
                            )}
                        </div>
                    </header>

                    <section style={{ padding: '64px 80px', borderBottom: '1px solid var(--color-line)' }}>
                        <div className="eyebrow" style={{ marginBottom: 14 }}>Overall grade</div>
                        <div style={{ display: 'grid', gridTemplateColumns: 'auto 1fr', gap: 48, alignItems: 'center' }}>
                            <div>
                                <div style={{
                                    fontFamily: 'var(--font-serif)',
                                    fontSize: 160,
                                    lineHeight: 0.85,
                                    fontWeight: 400,
                                    color,
                                    letterSpacing: '-0.04em',
                                }}>
                                    {grade}
                                </div>
                                <div className="micro" style={{ textTransform: 'uppercase', letterSpacing: '0.1em', marginTop: 8 }}>
                                    {report.grade_label}
                                </div>
                            </div>
                            <div>
                                <p style={{
                                    fontFamily: 'var(--font-serif)',
                                    fontSize: 22,
                                    lineHeight: 1.5,
                                    color: 'var(--color-stone-700)',
                                    margin: 0,
                                }}>
                                    We audited your website and Google Business Profile against WCAG 2.2 and local competitors in {report.city}.
                                    {summary.total > 0 && (
                                        <> The audit found <strong style={{ fontWeight: 400, color: 'var(--color-ink)' }}>{summary.total} issues</strong> worth addressing.</>
                                    )}
                                </p>
                                <div style={{ marginTop: 24, display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                    {summary.critical > 0 && <SevChip level="critical" count={summary.critical} />}
                                    {summary.serious > 0 && <SevChip level="serious" count={summary.serious} />}
                                    {summary.moderate > 0 && <SevChip level="moderate" count={summary.moderate} />}
                                </div>
                            </div>
                        </div>
                    </section>

                    {hasA11y && (
                        <section style={{ padding: '72px 80px', borderBottom: '1px solid var(--color-line)' }}>
                            <div className="eyebrow" style={{ marginBottom: 10 }}>Section 1 · Accessibility</div>
                            <h2 style={{
                                fontFamily: 'var(--font-serif)',
                                fontWeight: 500,
                                fontSize: 38,
                                letterSpacing: '-0.018em',
                                margin: '0 0 16px',
                                lineHeight: 1.15,
                            }}>
                                The issues to fix first.
                            </h2>
                            <p style={{ color: 'var(--color-stone-600)', fontSize: 15, lineHeight: 1.6, maxWidth: 620, margin: '0 0 36px' }}>
                                Every issue is mapped to a WCAG 2.2 success criterion. The fixes are usually a few lines of HTML or CSS — but the consequence of leaving them is a visitor who cannot complete a booking or enquiry.
                            </p>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 36 }}>
                                {(report.top_violations ?? []).map((v, i) => (
                                    <ViolationCard key={i} violation={v} screenshotUrl={v.screenshot_url} />
                                ))}
                            </div>
                        </section>
                    )}

                    {hasGbp && (
                        <section style={{ padding: '72px 80px', borderBottom: '1px solid var(--color-line)', background: 'var(--color-paper-2)' }}>
                            <div className="eyebrow" style={{ marginBottom: 10 }}>Section 2 · Google Business Profile</div>
                            <h2 style={{
                                fontFamily: 'var(--font-serif)',
                                fontWeight: 500,
                                fontSize: 38,
                                letterSpacing: '-0.018em',
                                margin: '0 0 16px',
                                lineHeight: 1.15,
                            }}>
                                You, next to the top-ranking practice in {report.city}.
                            </h2>
                            <ComparisonTable
                                businessName={report.business_name}
                                you={p}
                                benchmark={benchmark}
                            />
                        </section>
                    )}

                    {hasLighthouse && (
                        <section style={{ padding: '72px 80px', borderBottom: '1px solid var(--color-line)' }}>
                            <div className="eyebrow" style={{ marginBottom: 10 }}>Section 3 · Site performance</div>
                            <h2 style={{
                                fontFamily: 'var(--font-serif)',
                                fontWeight: 500,
                                fontSize: 38,
                                letterSpacing: '-0.018em',
                                margin: '0 0 28px',
                                lineHeight: 1.15,
                            }}>
                                How the site loads, for real users.
                            </h2>
                            <p style={{ color: 'var(--color-stone-600)', fontSize: 15, lineHeight: 1.6, maxWidth: 620, margin: '0 0 36px' }}>
                                Measured via Google Lighthouse on a mid-range mobile connection. Below 50 in any dial is where Google starts penalising the site in mobile search.
                            </p>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-8">
                                {lighthouse.performance != null && <LighthouseDial label="Performance" score={lighthouse.performance} />}
                                {lighthouse.accessibility != null && <LighthouseDial label="Accessibility" score={lighthouse.accessibility} />}
                                {lighthouse.seo != null && <LighthouseDial label="SEO" score={lighthouse.seo} />}
                                {lighthouse.best_practices != null && <LighthouseDial label="Best practices" score={lighthouse.best_practices} />}
                            </div>
                            {lighthouse.performance != null && lighthouse.performance < 30 && (
                                <p className="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mt-4">
                                    Slow load times affect search rankings and increase bounce rate — visitors often leave before the page finishes loading.
                                </p>
                            )}
                        </section>
                    )}

                    {report.booking_url && (
                        <section style={{ padding: '96px 80px', textAlign: 'center', borderBottom: '1px solid var(--color-line)' }}>
                            <div className="eyebrow" style={{ marginBottom: 18, color: 'var(--color-accent-deep)' }}>Next step</div>
                            <h2 style={{
                                fontFamily: 'var(--font-serif)',
                                fontWeight: 400,
                                fontSize: 48,
                                letterSpacing: '-0.02em',
                                margin: '0 0 18px',
                                lineHeight: 1.1,
                            }}>
                                A free 30-minute call to walk you through every fix.
                            </h2>
                            <p style={{
                                fontFamily: 'var(--font-serif)',
                                fontSize: 18,
                                color: 'var(--color-stone-600)',
                                maxWidth: 480,
                                margin: '0 auto 32px',
                                lineHeight: 1.55,
                            }}>
                                No obligation. We'll go through the audit findings and outline what fixing them would involve.
                            </p>
                            <LinkButton
                                href={report.book_cta_url ?? report.booking_url}
                                kind="accent"
                                size="lg"
                                {...(report.book_cta_external
                                    ? { target: '_blank', rel: 'noopener noreferrer' }
                                    : {})}
                            >
                                Book a free 30-minute review
                            </LinkButton>
                            <div className="micro" style={{ marginTop: 20 }}>
                                {report.book_cta_external
                                    ? `${report.booking_url.replace(/^https?:\/\//, '')} · `
                                    : ''}
                                Typical reply within one working day
                            </div>
                        </section>
                    )}

                    <footer style={{ padding: '32px 80px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span className="brand-mark" style={{ width: 14, height: 14 }} />
                            <span className="micro">nthdesigns · Digital consultancy</span>
                        </div>
                        <div className="micro">
                            {report.token && <>Token {report.token} · </>}
                            {report.expires_at && <>Expires {new Date(report.expires_at).toLocaleDateString('en-GB')}</>}
                        </div>
                    </footer>
                </article>
            </div>
        </>
    );
}

function ComparisonTable({ businessName, you, benchmark }) {
    const rows = [
        { label: 'Reviews', you: you.review_count ?? 0, them: benchmark.review_count },
        { label: 'Photos', you: you.photo_count ?? 0, them: benchmark.photo_count },
        { label: 'Rating', you: you.rating ?? '—', them: benchmark.rating ?? '—' },
        { label: 'Description', you: you.has_description ? 'Yes' : 'No', them: (benchmark.has_description ?? false) ? 'Yes' : 'No' },
        { label: 'Hours', you: you.hours_complete ? 'Complete' : 'Incomplete', them: (benchmark.hours_complete ?? false) ? 'Complete' : 'Incomplete' },
    ];

    return (
        <DataTable style={{ background: 'var(--color-paper)' }}>
            <thead>
                <tr>
                    <th>Signal</th>
                    <th>You</th>
                    <th style={{ background: 'var(--color-accent-soft)' }}>{benchmark.name}</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.label}>
                        <td style={{ color: 'var(--color-stone-600)' }}>{row.label}</td>
                        <td style={{ fontFamily: 'var(--font-serif)', fontSize: 28 }}>{row.you}</td>
                        <td style={{ fontFamily: 'var(--font-serif)', fontSize: 28, background: 'var(--color-accent-soft)' }}>{row.them}</td>
                    </tr>
                ))}
            </tbody>
        </DataTable>
    );
}

