import { Head } from '@inertiajs/react';
import ViolationCard from '@/Components/audit/ViolationCard';
import LighthouseDial from '@/Components/audit/LighthouseDial';
import ReportSummarySection from '@/Components/report/ReportSummarySection';
import ReportBookingSection from '@/Components/ReportBookingSection';
import { DataTable, LinkButton } from '@/Components/ui';
import { hasA11yAuditContent, hasLighthouseMetrics } from '@/utils/auditVisibility';

export default function PublicReport({ report }) {
    const p = report.prospect ?? {};
    const benchmark = report.benchmark;
    const summary = report.violation_summary ?? {};
    const lighthouse = report.lighthouse ?? {};
    const grade = report.grade ?? 'C';
    const hasA11y = hasA11yAuditContent({ summary, topViolations: report.top_violations });
    const hasLighthouse = hasLighthouseMetrics(lighthouse);
    const hasGbp = benchmark != null;

    return (
        <>
            <Head title={`${report.business_name} — Independent audit`} />

            <div className="public-report-wrap">
                <article className="public-report">
                    <header>
                        <div className="public-report-header-bar">
                            <div className="public-report-brand">
                                <span className="brand-mark brand-mark--md" />
                                <span className="public-report-brand-name">nthdesigns</span>
                            </div>
                            <div className="micro micro--upper">
                                Audit · {new Date(report.generated_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
                            </div>
                        </div>

                        <div className="eyebrow eyebrow--spaced">Independent audit · WCAG 2.2 + Google Business Profile</div>
                        <h1 className="public-report-title">
                            {report.business_name}
                        </h1>
                        <div className="public-report-meta">
                            {report.website_url && (
                                <span className="micro public-report-meta-url">
                                    {report.website_url.replace(/^https?:\/\//, '')}
                                </span>
                            )}
                            {report.address && (
                                <>
                                    <span className="public-report-meta-sep">·</span>
                                    <span>{report.address}</span>
                                </>
                            )}
                        </div>
                    </header>

                    <section className="public-report-section">
                        <ReportSummarySection
                            reportContext={report.report_context}
                            grade={grade}
                            gradeLabel={report.grade_label}
                            combinedScore={report.combined_score}
                            violationSummary={summary}
                            city={report.city}
                        />
                    </section>

                    {hasA11y && (
                        <section className="public-report-section public-report-section--lg">
                            <div className="eyebrow eyebrow--section">Section 1 · Accessibility</div>
                            <h2 className="public-report-h2">
                                The issues to fix first.
                            </h2>
                            <p className="public-report-prose">
                                Every issue is mapped to a WCAG 2.2 success criterion. The fixes are usually a few lines of HTML or CSS — but the consequence of leaving them is a visitor who cannot complete a booking or enquiry.
                            </p>
                            <div className="public-report-violations">
                                {(report.top_violations ?? []).map((v, i) => (
                                    <ViolationCard key={i} violation={v} screenshotUrl={v.screenshot_url} />
                                ))}
                            </div>
                        </section>
                    )}

                    {hasGbp && (
                        <section className="public-report-section public-report-section--lg public-report-section--alt">
                            <div className="eyebrow eyebrow--section">Section 2 · Google Business Profile</div>
                            <h2 className="public-report-h2">
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
                        <section className="public-report-section public-report-section--lg">
                            <div className="eyebrow eyebrow--section">Section 3 · Site performance</div>
                            <h2 className="public-report-h2 public-report-h2--perf">
                                How the site loads, for real users.
                            </h2>
                            <p className="public-report-prose">
                                Measured via Google Lighthouse on a mid-range mobile connection. Below 50 in any dial is where Google starts penalising the site in mobile search.
                            </p>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-8">
                                {lighthouse.performance != null && (
                                    <LighthouseDial
                                        label="Performance"
                                        score={lighthouse.performance}
                                        caption={report.report_context?.lighthouse_captions?.performance}
                                    />
                                )}
                                {lighthouse.accessibility != null && (
                                    <LighthouseDial
                                        label="Accessibility"
                                        score={lighthouse.accessibility}
                                        caption={report.report_context?.lighthouse_captions?.accessibility}
                                    />
                                )}
                                {lighthouse.seo != null && (
                                    <LighthouseDial
                                        label="SEO"
                                        score={lighthouse.seo}
                                        caption={report.report_context?.lighthouse_captions?.seo}
                                    />
                                )}
                                {lighthouse.best_practices != null && (
                                    <LighthouseDial
                                        label="Best practices"
                                        score={lighthouse.best_practices}
                                        caption={report.report_context?.lighthouse_captions?.best_practices}
                                    />
                                )}
                            </div>
                            {lighthouse.performance != null && lighthouse.performance < 30 && (
                                <p className="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mt-4">
                                    Slow load times affect search rankings and increase bounce rate — visitors often leave before the page finishes loading.
                                </p>
                            )}
                        </section>
                    )}

                    {(report.native_booking || report.booking_url) && (
                        <section id="book" className="public-report-section public-report-section--cta">
                            <div className="eyebrow eyebrow--cta">Next step</div>
                            <h2 className="public-report-cta-title">
                                Let&apos;s scope what fixing this would involve.
                            </h2>
                            <p className="public-report-cta-copy">
                                You&apos;ve seen the findings above. On a free 30-minute call we&apos;ll estimate effort, cost, and timeline — and answer any questions. No obligation.
                            </p>
                            {report.native_booking ? (
                                <ReportBookingSection
                                    token={report.token}
                                    businessName={report.business_name}
                                    existingBooking={report.booking}
                                    timezoneLabel={report.booking_timezone_label}
                                />
                            ) : (
                                <>
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
                                    <div className="micro public-report-cta-note">
                                        {report.book_cta_external
                                            ? `${report.booking_url.replace(/^https?:\/\//, '')} · `
                                            : ''}
                                        Typical reply within one working day
                                    </div>
                                </>
                            )}
                        </section>
                    )}

                    <footer>
                        <div className="public-report-footer-brand">
                            <span className="brand-mark brand-mark--sm" />
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

function formatGbpRating(value) {
    if (value == null || value === '') {
        return '—';
    }

    const rating = Number(value);

    return Number.isFinite(rating) ? rating.toFixed(1) : '—';
}

function ComparisonTable({ businessName, you, benchmark }) {
    const rows = [
        { label: 'Reviews', you: you.review_count ?? 0, them: benchmark.review_count },
        { label: 'Photos', you: you.photo_count ?? 0, them: benchmark.photo_count },
        { label: 'Rating', you: formatGbpRating(you.rating), them: formatGbpRating(benchmark.rating) },
        { label: 'Description', you: you.has_description ? 'Yes' : 'No', them: (benchmark.has_description ?? false) ? 'Yes' : 'No' },
        { label: 'Hours', you: you.hours_complete ? 'Complete' : 'Incomplete', them: (benchmark.hours_complete ?? false) ? 'Complete' : 'Incomplete' },
    ];

    return (
        <DataTable tableClassName="ptable--comparison" className="public-report-table-wrap">
            <colgroup>
                <col className="col-signal" />
                <col className="col-you" />
                <col className="col-benchmark" />
            </colgroup>
            <thead>
                <tr>
                    <th>Signal</th>
                    <th>You</th>
                    <th className="col-benchmark">{benchmark.name}</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.label}>
                        <td className="comparison-label">{row.label}</td>
                        <td className="comparison-value">{row.you}</td>
                        <td className="comparison-value col-benchmark">{row.them}</td>
                    </tr>
                ))}
            </tbody>
        </DataTable>
    );
}
