import { Head } from '@inertiajs/react';

export default function PublicReport({ report }) {
    const p = report.prospect ?? {};
    const benchmark = report.benchmark;
    const comparison = report.comparison ?? {};

    return (
        <>
            <Head title={`${report.business_name} — Online presence report`} />

            <div className="min-h-screen bg-gray-50">
                <header className="bg-white border-b border-gray-200">
                    <div className="max-w-3xl mx-auto px-4 py-8">
                        <p className="text-sm text-indigo-600 font-medium">nthdesigns</p>
                        <h1 className="text-2xl font-semibold text-gray-900 mt-1">
                            {report.business_name}
                        </h1>
                        <p className="text-gray-500 text-sm mt-1">
                            {report.niche} in {report.city} — online presence snapshot
                        </p>
                    </div>
                </header>

                <main className="max-w-3xl mx-auto px-4 py-8 space-y-6">
                    <div className="grid grid-cols-3 gap-4">
                        <MetricCard label="Opportunity score" value={p.combined_score} highlight />
                        <MetricCard label="GBP score" value={p.gbp_score} />
                        <MetricCard label="Accessibility" value={p.a11y_score} />
                    </div>

                    {report.screenshot_paths?.desktop && (
                        <section className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <img
                                src={report.screenshot_paths.desktop}
                                alt="Website preview"
                                className="w-full"
                            />
                        </section>
                    )}

                    {benchmark && (
                        <section className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">
                                vs top local competitor
                            </h2>
                            <div className="grid grid-cols-2 gap-6 text-sm">
                                <div>
                                    <div className="font-medium text-gray-900">{report.business_name}</div>
                                    <dl className="mt-2 space-y-1 text-gray-600">
                                        <div className="flex justify-between">
                                            <dt>Reviews</dt>
                                            <dd>{p.review_count ?? 0}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt>Photos</dt>
                                            <dd>{p.photo_count ?? 0}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt>Rating</dt>
                                            <dd>{p.rating ?? '—'}</dd>
                                        </div>
                                    </dl>
                                </div>
                                <div>
                                    <div className="font-medium text-gray-900">{benchmark.name}</div>
                                    <dl className="mt-2 space-y-1 text-gray-600">
                                        <div className="flex justify-between">
                                            <dt>Reviews</dt>
                                            <dd>{benchmark.review_count}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt>Photos</dt>
                                            <dd>{benchmark.photo_count}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt>Rating</dt>
                                            <dd>{benchmark.rating ?? '—'}</dd>
                                        </div>
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

                    <section className="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">
                            Areas to improve
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

                    {report.booking_url && (
                        <section className="bg-indigo-600 rounded-xl p-6 text-center text-white">
                            <h2 className="text-lg font-semibold">Want help fixing this?</h2>
                            <p className="text-indigo-100 text-sm mt-1 mb-4">
                                Book a free 20-minute call with nthdesigns.
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

                    <p className="text-xs text-gray-400 text-center">
                        Report generated {new Date(report.generated_at).toLocaleDateString()}
                    </p>
                </main>
            </div>
        </>
    );
}

function MetricCard({ label, value, highlight = false }) {
    return (
        <div className={`rounded-xl border p-4 text-center ${highlight ? 'bg-indigo-50 border-indigo-200' : 'bg-white border-gray-200'}`}>
            <div className="text-xs text-gray-500 uppercase tracking-wider">{label}</div>
            <div className={`text-2xl font-semibold mt-1 ${highlight ? 'text-indigo-700' : 'text-gray-900'}`}>
                {value ?? 0}
            </div>
        </div>
    );
}
