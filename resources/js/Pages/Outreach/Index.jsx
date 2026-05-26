import { Head, Link, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OutreachEmailCard from '@/Components/OutreachEmailCard';

export default function OutreachIndex({ auth, selection, emailsByProspect, defaults, flash }) {
    const { data, setData, post, processing } = useForm({
        agency_name: defaults.agency_name,
        pitch_angle: defaults.pitch_angle,
        cpc_benchmark: defaults.cpc_benchmark,
    });

    const removeFromQueue = (prospectId) => {
        router.delete(`/outreach/selections/${prospectId}`);
    };

    const clearQueue = () => {
        router.delete('/outreach/selections');
    };

    const generateAll = (e) => {
        e.preventDefault();
        post('/outreach/generate');
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Outreach" />

            <div className="max-w-7xl mx-auto py-10 px-4">
                <h1 className="text-2xl font-semibold text-gray-900 mb-6">Outreach</h1>

                {flash?.success && (
                    <div className="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                        {flash.success}
                    </div>
                )}

                {flash?.skipped?.length > 0 && (
                    <div className="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm px-4 py-3">
                        Skipped (no report): {flash.skipped.join(', ')}
                    </div>
                )}

                <div className="grid lg:grid-cols-2 gap-8">
                    <section className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider">
                                Queue ({selection.length})
                            </h2>
                            {selection.length > 0 && (
                                <button type="button" onClick={clearQueue} className="text-xs text-gray-500 hover:text-gray-700">Clear all</button>
                            )}
                        </div>

                        {selection.length === 0 ? (
                            <p className="text-sm text-gray-400 py-8 text-center border border-dashed border-gray-200 rounded-xl">
                                Add prospects from a <Link href="/search" className="text-indigo-600 hover:underline">search</Link> or <Link href="/saved" className="text-indigo-600 hover:underline">saved list</Link>.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {selection.map(item => (
                                    <li key={item.id} className="bg-white border border-gray-200 rounded-lg px-4 py-3 flex items-center justify-between gap-3">
                                        <div>
                                            <div className="font-medium text-gray-900 text-sm">{item.business_name}</div>
                                            <div className="text-xs text-gray-500 mt-0.5">
                                                {item.dominant_angle}
                                                {item.report_ready ? ' · report ready' : ' · needs report'}
                                            </div>
                                        </div>
                                        <button type="button" onClick={() => removeFromQueue(item.prospect_id)} className="text-xs text-gray-500 hover:text-red-600 shrink-0">Remove</button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="space-y-6">
                        <form onSubmit={generateAll} className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider">Generate emails</h2>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Agency name (optional)</label>
                                <input type="text" value={data.agency_name} onChange={e => setData('agency_name', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="nthdesigns" />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Pitch angle</label>
                                <select value={data.pitch_angle} onChange={e => setData('pitch_angle', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    <option value="auto">Auto</option>
                                    <option value="gbp">GBP</option>
                                    <option value="accessibility">Accessibility</option>
                                    <option value="combined">Combined</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">CPC benchmark £ (optional)</label>
                                <input type="number" min="0" step="0.01" value={data.cpc_benchmark} onChange={e => setData('cpc_benchmark', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                            </div>
                            <button type="submit" disabled={processing || selection.length === 0} className="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium py-2.5 rounded-lg">
                                {processing ? 'Queuing...' : 'Generate all'}
                            </button>
                        </form>

                        {selection.map(item => {
                            const emails = emailsByProspect[item.prospect_id] ?? [];
                            if (emails.length === 0) return null;
                            return (
                                <div key={item.prospect_id} className="space-y-3">
                                    <h3 className="text-sm font-medium text-gray-900">{item.business_name}</h3>
                                    {emails.map(email => (
                                        <OutreachEmailCard key={email.id} email={email} />
                                    ))}
                                </div>
                            );
                        })}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
