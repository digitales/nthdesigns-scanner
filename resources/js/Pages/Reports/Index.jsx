import { Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function ReportsIndex({ auth, reports, filters }) {
    const submitFilters = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get('/reports', Object.fromEntries(form.entries()), { preserveState: true });
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Reports" />

            <div className="max-w-7xl mx-auto py-10 px-4 space-y-6">
                <h1 className="text-2xl font-semibold text-gray-900">Reports</h1>

                <form onSubmit={submitFilters} className="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3 items-end">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Niche</label>
                        <input type="text" name="niche" defaultValue={filters.niche ?? ''} className="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Viewed</label>
                        <select name="viewed" defaultValue={filters.viewed ?? ''} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Any</option>
                            <option value="1">Viewed</option>
                            <option value="0">Not viewed</option>
                        </select>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-gray-700 pb-2">
                        <input type="checkbox" name="warm" value="1" defaultChecked={!!filters.warm} />
                        Viewed in last 7 days
                    </label>
                    <button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Filter</button>
                    <Link href="/reports" className="text-sm text-gray-500 pb-2">Clear</Link>
                </form>

                <div className="overflow-x-auto rounded-xl border border-gray-200">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Business</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Location</th>
                                <th className="text-center px-4 py-3 font-medium text-gray-600">Views</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">First viewed</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Report</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Prospect</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {reports.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-gray-400">No reports yet.</td>
                                </tr>
                            ) : reports.map(r => (
                                <tr key={r.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-gray-900">{r.business_name}</div>
                                        {r.is_engaged_badge && (
                                            <span className="text-xs bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded mt-1 inline-block">Engaged</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{r.niche} · {r.city}</td>
                                    <td className="px-4 py-3 text-center">{r.view_count}</td>
                                    <td className="px-4 py-3 text-gray-600 text-xs">
                                        {r.viewed_at ? new Date(r.viewed_at).toLocaleString() : '—'}
                                    </td>
                                    <td className="px-4 py-3 space-x-2">
                                        <button type="button" onClick={() => navigator.clipboard.writeText(r.public_url)} className="text-indigo-600 hover:underline text-xs">Copy</button>
                                        <a href={r.public_url} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:underline text-xs">Open</a>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link href={`/prospects/${r.prospect_id}`} className="text-indigo-600 hover:underline text-xs">View</Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
