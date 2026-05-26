import { Head } from '@inertiajs/react';

export default function PublicReport({ report }) {
    const p = report.prospect ?? {};
    const benchmark = report.benchmark;
    const comparison = report.comparison ?? {};
    const summary = report.violation_summary ?? {};
    const lighthouse = report.lighthouse ?? {};
    const hasA11y = (summary.total ?? 0) > 0 || (report.top_violations?.length ?? 0) > 0;
    const hasGbp = (p.gbp_flags?.length ?? 0) > 0 || benchmark;

    return (
        <>
            <Head title={`${report.business_name} — Online presence report`} />

            <div className="min-h-screen bg-gray-50">
                <header className="bg-white border-b border-gray-200">
                    <div className="max-w-3xl mx-auto px-4 py-8 flex items-start justify-between gap-6">
                        <div>
                            <p className="text-sm text-indigo-600 font-medium">nthdesigns</p>
                            <h1 className="text-2xl font-semibold text-gray-900 mt-1">
                                {report.business_name}
                            </h1>
                            <p className="text-gray-500 text-sm mt-1">
                                {report.niche} in {report.city}
                                {report.website_url && (
                                    <> · <a href={report.website_url} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:underline">{stripProtocol(report.website_url)}</a></>
                                )}
                            </p>
                        </div>
                        <GradeBadge grade={report.grade} label={report.grade_label} />
                    </div>
                </header>

                <main className="max-w-3xl mx-auto px-4 py-8 space-y-6">
                    {report.screenshot_paths?.desktop && (
                        <section className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <img
                                src={report.screenshot_paths.desktop}
                                alt="Website preview"
                                className="w-full"
                            />
                        </section>
                    )}

                    {hasA11y && (
                        <section className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Accessibility findings
                            </h2>
                            <div className="flex flex-wrap gap-2">
                                {summary.critical > 0 && <SeverityPill level="critical" count={summary.critical} />}
                                {summary.serious > 0 && <SeverityPill level="serious" count={summary.serious} />}
                                {summary.moderate > 0 && <SeverityPill level="moderate" count={summary.moderate} />}
                                {summary.minor > 0 && <SeverityPill level="minor" count={summary.minor} />}
                            </div>
                            <ul className="space-y-4">
                                {(report.top_violations ?? []).map((v, i) => (
                                    <li key={i} className="border border-gray-100 rounded-lg p-4">
                                        <div className="flex items-center gap-2 mb-1">
                                            <ImpactBadge impact={v.impact} />
                                            {v.wcag && (
                                                <span className="text-xs text-gray-500 font-mono">{v.wcag}</span>
                                            )}
                                        </div>
                                        <p className="text-sm font-medium text-gray-900">{v.description}</p>
                                        {v.help && v.help !== v.description && (
                                            <p className="text-sm text-gray-600 mt-1">{v.help}</p>
                                        )}
                                        {v.nodes > 0 && (
                                            <p className="text-xs text-gray-400 mt-2">
                                                Affects {v.nodes} element{v.nodes !== 1 ? 's' : ''} on the page
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {(lighthouse.performance != null || lighthouse.accessibility != null) && (
                        <section className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">
                                Site performance
                            </h2>
                            <div className="grid grid-cols-3 gap-4">
                                {lighthouse.performance != null && (
                                    <LighthouseDial label="Performance" score={lighthouse.performance} />
                                )}
                                {lighthouse.accessibility != null && (
                                    <LighthouseDial label="Accessibility" score={lighthouse.accessibility} />
                                )}
                                {lighthouse.seo != null && (
                                    <LighthouseDial label="SEO" score={lighthouse.seo} />
                                )}
                            </div>
                        </section>
                    )}

                    {hasGbp && benchmark && (
                        <section className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">
                                Google Business Profile vs top competitor
                            </h2>
                            <div className="grid grid-cols-2 gap-6 text-sm">
                                <div>
                                    <div className="font-medium text-gray-900">{report.business_name}</div>
                                    <dl className="mt-2 space-y-1 text-gray-600">
                                        <MetricRow label="Reviews" value={p.review_count ?? 0} />
                                        <MetricRow label="Photos" value={p.photo_count ?? 0} />
                                        <MetricRow label="Rating" value={p.rating ?? '—'} />
                                    </dl>
                                </div>
                                <div>
                                    <div className="font-medium text-gray-900">{benchmark.name}</div>
                                    <dl className="mt-2 space-y-1 text-gray-600">
                                        <MetricRow label="Reviews" value={benchmark.review_count} />
                                        <MetricRow label="Photos" value={benchmark.photo_count} />
                                        <MetricRow label="Rating" value={benchmark.rating ?? '—'} />
                                    </dl>
                                </div>
                            </div>
                            {(comparison.review_gap > 0 || comparison.photo_gap > 0) && (
                                <p className="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mt-4">
                                    Your top competitor has{' '}
                                    {comparison.review_gap > 0 && `${comparison.review_gap} more reviews`}
                                    {comparison.review_gap > 0 && comparison.photo_gap > 0 && ' and '}
                                    {comparison.photo_gap > 0 && `${comparison.photo_gap} more photos`}
                                    .
                                </p>
                            )}
                        </section>
                    )}

                    {(p.gbp_flags?.length > 0 || p.a11y_flags?.length > 0) && (
                        <section className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">
                                Priority improvements
                            </h2>
                            <ul className="space-y-2">
                                {[...(p.gbp_flags ?? []), ...(p.a11y_flags ?? [])].map((flag, i) => (
                                    <li key={i} className="text-sm text-gray-700 flex items-start gap-2">
                                        <span className="text-amber-500 mt-0.5">•</span>
                                        {flag}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {report.booking_url && (
                        <section className="bg-indigo-600 rounded-xl p-6 text-center text-white">
                            <h2 className="text-lg font-semibold">Want help fixing this?</h2>
                            <p className="text-indigo-100 text-sm mt-1 mb-4">
                                Book a free 30-minute review with nthdesigns.
                            </p>
                            <a
                                href={report.booking_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-block bg-white text-indigo-700 font-medium text-sm px-6 py-2.5 rounded-lg hover:bg-indigo-50"
                            >
                                Book a call
                            </a>
                        </section>
                    )}

                    <footer className="text-xs text-gray-400 text-center space-y-1">
                        <p>Report prepared by nthdesigns</p>
                        <p>
                            Generated {new Date(report.generated_at).toLocaleDateString()}
                            {report.expires_at && (
                                <> · Expires {new Date(report.expires_at).toLocaleDateString()}</>
                            )}
                        </p>
                    </footer>
                </main>
            </div>
        </>
    );
}

function stripProtocol(url) {
    return url.replace(/^https?:\/\//, '');
}

function GradeBadge({ grade, label }) {
    const colors = {
        A: 'bg-green-100 text-green-800 border-green-200',
        B: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        C: 'bg-amber-100 text-amber-800 border-amber-200',
        D: 'bg-orange-100 text-orange-800 border-orange-200',
        F: 'bg-red-100 text-red-800 border-red-200',
    };
    return (
        <div className={`shrink-0 text-center rounded-xl border px-5 py-3 ${colors[grade] ?? colors.C}`}>
            <div className="text-3xl font-bold">{grade}</div>
            <div className="text-xs mt-1 max-w-[120px]">{label}</div>
        </div>
    );
}

function SeverityPill({ level, count }) {
    const styles = {
        critical: 'bg-red-100 text-red-800',
        serious: 'bg-orange-100 text-orange-800',
        moderate: 'bg-amber-100 text-amber-800',
        minor: 'bg-gray-100 text-gray-700',
    };
    return (
        <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${styles[level]}`}>
            {count} {level}
        </span>
    );
}

function ImpactBadge({ impact }) {
    const styles = {
        critical: 'bg-red-600 text-white',
        serious: 'bg-orange-500 text-white',
        moderate: 'bg-amber-500 text-white',
        minor: 'bg-gray-400 text-white',
    };
    return (
        <span className={`text-xs font-medium px-2 py-0.5 rounded capitalize ${styles[impact] ?? styles.moderate}`}>
            {impact}
        </span>
    );
}

function LighthouseDial({ label, score }) {
    const color = score >= 90 ? 'text-green-600' : score >= 50 ? 'text-amber-600' : 'text-red-600';
    const ring = score >= 90 ? 'border-green-200' : score >= 50 ? 'border-amber-200' : 'border-red-200';
    return (
        <div className={`text-center rounded-xl border p-4 ${ring}`}>
            <div className={`text-2xl font-semibold ${color}`}>{score}</div>
            <div className="text-xs text-gray-500 mt-1">{label}</div>
        </div>
    );
}

function MetricRow({ label, value }) {
    return (
        <div className="flex justify-between">
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}
