import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SavedIndex({ auth, prospects, warmLeads, filters, meta }) {
    const submitFilters = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        const params = Object.fromEntries(form.entries());
        router.get('/saved', params, { preserveState: true });
    };

    const addToOutreach = (prospectId) => {
        router.post('/outreach/selections', { prospect_ids: [prospectId] });
    };

    const exportCsv = () => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/exports';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrf) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = csrf;
            form.appendChild(input);
        }
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== '' && value != null) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
        });
        document.body.appendChild(form);
        form.submit();
        form.remove();
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Saved prospects" />

            <div className="max-w-7xl mx-auto py-10 px-4 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold text-gray-900">Saved prospects</h1>
                    <button
                        type="button"
                        onClick={exportCsv}
                        className="text-sm bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg"
                    >
                        Export CSV
                    </button>
                </div>

                <form onSubmit={submitFilters} className="bg-white rounded-xl border border-gray-200 p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <FilterInput label="Niche" name="niche" defaultValue={filters.niche ?? ''} />
                    <FilterInput label="City" name="city" defaultValue={filters.city ?? ''} />
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Min score</label>
                        <input type="number" name="min_score" min="0" max="100" defaultValue={filters.min_score ?? ''} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Angle</label>
                        <select name="dominant_angle" defaultValue={filters.dominant_angle ?? ''} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Any</option>
                            <option value="gbp">GBP</option>
                            <option value="accessibility">Accessibility</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Scan type</label>
                        <select name="scan_type" defaultValue={filters.scan_type ?? ''} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Any</option>
                            <option value="combined">Combined</option>
                            <option value="gbp_only">GBP only</option>
                            <option value="accessibility_only">Accessibility only</option>
                        </select>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-gray-700 self-end pb-2">
                        <input type="checkbox" name="warm" value="1" defaultChecked={!!filters.warm} />
                        Warm leads only
                    </label>
                    <div className="col-span-2 md:col-span-4 flex gap-2">
                        <button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Apply filters</button>
                        <Link href="/saved" className="text-sm text-gray-500 hover:text-gray-700 px-4 py-2">Clear</Link>
                    </div>
                </form>

                {warmLeads.length > 0 && !filters.warm && (
                    <section className="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <h2 className="text-sm font-medium text-amber-900 mb-3">Warm leads</h2>
                        <ul className="space-y-2">
                            {warmLeads.map(p => (
                                <li key={p.id} className="flex items-center justify-between text-sm">
                                    <Link href={`/prospects/${p.id}`} className="font-medium text-gray-900 hover:text-indigo-600">{p.business_name}</Link>
                                    <span className="text-gray-500">{p.city}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <p className="text-sm text-gray-500">{meta.total} prospect{meta.total !== 1 ? 's' : ''}</p>

                <ProspectTable prospects={prospects} onAddToOutreach={addToOutreach} />
            </div>
        </AuthenticatedLayout>
    );
}

function FilterInput({ label, name, defaultValue }) {
    return (
        <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">{label}</label>
            <input type="text" name={name} defaultValue={defaultValue} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
        </div>
    );
}

function ProspectTable({ prospects, onAddToOutreach }) {
    if (prospects.length === 0) {
        return <p className="text-center py-12 text-gray-400 text-sm">No prospects match your filters.</p>;
    }

    return (
        <div className="overflow-x-auto rounded-xl border border-gray-200">
            <table className="w-full text-sm">
                <thead className="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th className="text-left px-4 py-3 font-medium text-gray-600">Business</th>
                        <th className="text-center px-4 py-3 font-medium text-gray-600">Score</th>
                        <th className="text-left px-4 py-3 font-medium text-gray-600">Location</th>
                        <th className="text-left px-4 py-3 font-medium text-gray-600">Report</th>
                        <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {prospects.map(p => (
                        <tr key={p.id} className="hover:bg-gray-50">
                            <td className="px-4 py-3">
                                <Link href={`/prospects/${p.id}`} className="font-medium text-gray-900 hover:text-indigo-600">{p.business_name}</Link>
                            </td>
                            <td className="px-4 py-3 text-center">
                                <ScoreBadge score={p.combined_score} />
                            </td>
                            <td className="px-4 py-3 text-gray-600">{p.niche} · {p.city}</td>
                            <td className="px-4 py-3">
                                {p.report_url ? (
                                    <button type="button" onClick={() => navigator.clipboard.writeText(p.report_url)} className="text-indigo-600 hover:underline text-xs">Copy link</button>
                                ) : (
                                    <span className="text-gray-400 text-xs">—</span>
                                )}
                            </td>
                            <td className="px-4 py-3 text-right">
                                <button type="button" onClick={() => onAddToOutreach(p.id)} className="text-xs text-indigo-600 hover:underline">Add to outreach</button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ScoreBadge({ score }) {
    const color = score >= 70 ? 'bg-red-100 text-red-700' : score >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700';
    return <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${color}`}>{score}</span>;
}
