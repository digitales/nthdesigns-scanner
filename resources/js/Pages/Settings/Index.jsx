import { Head, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SettingsIndex({ auth, settings, health, env }) {
    const { flash } = usePage().props;
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        default_country: settings.default_country,
        agency_name: settings.agency_name,
        booking_url: settings.booking_url,
    });

    const submit = (e) => {
        e.preventDefault();
        patch('/settings');
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Settings" />

            <div className="max-w-3xl mx-auto py-10 px-4 space-y-8">
                <h1 className="text-2xl font-semibold text-gray-900">Settings</h1>

                {flash?.success && (
                    <div className="rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                        {flash.success}
                    </div>
                )}

                <section className="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">
                        API & storage health
                    </h2>
                    <ul className="space-y-3">
                        {Object.entries(health).map(([key, status]) => (
                            <li key={key} className="flex items-start justify-between gap-4 text-sm">
                                <span className="font-medium text-gray-700 capitalize">{key.replace('_', ' ')}</span>
                                <span className={status.ok ? 'text-green-700' : 'text-red-600'}>
                                    {status.message}
                                </span>
                            </li>
                        ))}
                        <li className="flex items-start justify-between gap-4 text-sm">
                            <span className="font-medium text-gray-700">Reports disk</span>
                            <span className="text-gray-600">{env.reports_disk}</span>
                        </li>
                        <li className="flex items-start justify-between gap-4 text-sm">
                            <span className="font-medium text-gray-700">Audit driver</span>
                            <span className="text-gray-600">{env.audit_driver}</span>
                        </li>
                        <li className="flex items-start justify-between gap-4 text-sm">
                            <span className="font-medium text-gray-700">Screenshot driver</span>
                            <span className="text-gray-600">{env.screenshot_driver}</span>
                        </li>
                    </ul>
                </section>

                <form onSubmit={submit} className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wider">
                        Defaults
                    </h2>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Default country</label>
                        <select
                            value={data.default_country}
                            onChange={e => setData('default_country', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="GB">United Kingdom</option>
                            <option value="IE">Ireland</option>
                            <option value="US">United States</option>
                        </select>
                        {errors.default_country && <p className="text-red-500 text-xs mt-1">{errors.default_country}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Agency name</label>
                        <input
                            type="text"
                            value={data.agency_name}
                            onChange={e => setData('agency_name', e.target.value)}
                            placeholder="nthdesigns"
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p className="text-xs text-gray-500 mt-1">Pre-fills the outreach generator.</p>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Booking URL</label>
                        <input
                            type="url"
                            value={data.booking_url}
                            onChange={e => setData('booking_url', e.target.value)}
                            placeholder="https://tidycal.com/yourhandle"
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p className="text-xs text-gray-500 mt-1">Used on public report CTA buttons. Overrides REPORT_BOOKING_URL.</p>
                        {errors.booking_url && <p className="text-red-500 text-xs mt-1">{errors.booking_url}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2.5 rounded-lg"
                    >
                        {processing ? 'Saving...' : 'Save settings'}
                    </button>

                    {recentlySuccessful && (
                        <p className="text-sm text-green-600">Saved.</p>
                    )}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
