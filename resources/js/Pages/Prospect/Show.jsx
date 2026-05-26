import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OutreachEmailCard from '@/Components/OutreachEmailCard';

export default function ProspectShow({ auth, prospect, search, report, outreachEmails }) {
    const { flash } = usePage().props;

    const generateReport = () => {
        router.post(`/prospects/${prospect.id}/report`);
    };

    const generateOutreach = () => {
        router.post(`/prospects/${prospect.id}/outreach`);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={prospect.business_name} />

            <div className="max-w-4xl mx-auto py-10 px-4 space-y-6">
                <div>
                    <Link
                        href={`/searches/${search.id}`}
                        className="text-sm text-indigo-600 hover:underline"
                    >
                        ← Back to {search.niche} in {search.city}
                    </Link>
                    <h1 className="text-2xl font-semibold text-gray-900 mt-2">
                        {prospect.business_name}
                    </h1>
                    {prospect.address && (
                        <p className="text-sm text-gray-500 mt-0.5">{prospect.address}</p>
                    )}
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                        {flash.success}
                    </div>
                )}

                <div className="grid grid-cols-3 gap-4">
                    <ScoreCard label="Combined" score={prospect.combined_score} />
                    <ScoreCard label="GBP" score={prospect.gbp_score} />
                    <ScoreCard label="Accessibility" score={prospect.a11y_score} />
                </div>

                <section className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </h2>
                    <div className="flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={generateReport}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg"
                        >
                            {report ? 'Regenerate report' : 'Generate report'}
                        </button>
                        <button
                            type="button"
                            onClick={generateOutreach}
                            className="bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg"
                        >
                            Generate outreach email
                        </button>
                    </div>

                    {report && (
                        <div className="text-sm space-y-2 pt-2 border-t border-gray-100">
                            <p>
                                <span className="text-gray-500">Public link:</span>{' '}
                                <a
                                    href={report.public_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-indigo-600 hover:underline break-all"
                                >
                                    {report.public_url}
                                </a>
                            </p>
                            <p className="text-gray-400 text-xs">
                                {report.view_count} view{report.view_count !== 1 ? 's' : ''}
                                {report.expires_at && ` · expires ${new Date(report.expires_at).toLocaleDateString()}`}
                            </p>
                            {report.screenshot_paths?.desktop && (
                                <img
                                    src={report.screenshot_paths.desktop}
                                    alt="Website screenshot"
                                    className="rounded-lg border border-gray-200 mt-2 max-w-full"
                                />
                            )}
                        </div>
                    )}
                </section>

                <section className="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">
                        Weaknesses
                    </h2>
                    <div className="flex flex-wrap gap-1">
                        {(prospect.gbp_flags ?? []).map((f, i) => (
                            <Flag key={`g-${i}`} text={f} variant="gbp" />
                        ))}
                        {(prospect.a11y_flags ?? []).map((f, i) => (
                            <Flag key={`a-${i}`} text={f} variant="a11y" />
                        ))}
                    </div>
                </section>

                {outreachEmails.length > 0 && (
                    <section className="space-y-4">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider">
                            Outreach emails
                        </h2>
                        {outreachEmails.map(email => (
                            <OutreachEmailCard key={email.id} email={email} />
                        ))}
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function ScoreCard({ label, score }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div className="text-xs text-gray-500 uppercase tracking-wider">{label}</div>
            <div className="text-2xl font-semibold text-gray-900 mt-1">{score}</div>
        </div>
    );
}

function Flag({ text, variant }) {
    const styles = variant === 'a11y'
        ? 'bg-violet-50 text-violet-700 border-violet-200'
        : 'bg-amber-50 text-amber-700 border-amber-200';
    return (
        <span className={`text-xs border rounded px-2 py-0.5 ${styles}`}>{text}</span>
    );
}

