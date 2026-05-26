import { Head, useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SearchIndex({ auth, recentSearches }) {
    const { data, setData, post, processing, errors } = useForm({
        niche: '',
        city: '',
        country: 'GB',
        scan_type: 'combined',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/searches');
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="New Search" />

            <div className="max-w-3xl mx-auto py-10 px-4">
                <h1 className="text-2xl font-semibold text-gray-900 mb-6">
                    Prospect Scanner
                </h1>

                <form onSubmit={submit} className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Niche
                            </label>
                            <input
                                type="text"
                                placeholder="e.g. dental practice"
                                value={data.niche}
                                onChange={e => setData('niche', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                required
                            />
                            {errors.niche && <p className="text-red-500 text-xs mt-1">{errors.niche}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                City
                            </label>
                            <input
                                type="text"
                                placeholder="e.g. Birmingham"
                                value={data.city}
                                onChange={e => setData('city', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                required
                            />
                            {errors.city && <p className="text-red-500 text-xs mt-1">{errors.city}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Country
                            </label>
                            <select
                                value={data.country}
                                onChange={e => setData('country', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="GB">United Kingdom</option>
                                <option value="IE">Ireland</option>
                                <option value="US">United States</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Scan type
                            </label>
                            <select
                                value={data.scan_type}
                                onChange={e => setData('scan_type', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="combined">Combined (GBP + Accessibility)</option>
                                <option value="gbp_only">GBP only</option>
                                <option value="accessibility_only">Accessibility only</option>
                            </select>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-medium py-2.5 rounded-lg text-sm transition-colors"
                    >
                        {processing ? 'Starting scan...' : 'Run scan'}
                    </button>
                </form>

                {recentSearches.length > 0 && (
                    <div className="mt-8">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">
                            Recent searches
                        </h2>
                        <div className="space-y-2">
                            {recentSearches.map(s => (
                                <Link
                                    key={s.id}
                                    href={`/searches/${s.id}`}
                                    className="flex items-center justify-between bg-white border border-gray-200 rounded-lg px-4 py-3 hover:border-indigo-300 transition-colors"
                                >
                                    <span className="text-sm font-medium text-gray-900">
                                        {s.niche} in {s.city}
                                    </span>
                                    <div className="flex items-center gap-3">
                                        <StatusBadge status={s.status} />
                                        <span className="text-xs text-gray-400">{s.created_at}</span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function StatusBadge({ status }) {
    const map = {
        pending:     'bg-gray-100 text-gray-600',
        discovering: 'bg-blue-100 text-blue-700',
        auditing:    'bg-yellow-100 text-yellow-700',
        complete:    'bg-green-100 text-green-700',
        failed:      'bg-red-100 text-red-700',
    };
    return (
        <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${map[status] ?? map.pending}`}>
            {status}
        </span>
    );
}
