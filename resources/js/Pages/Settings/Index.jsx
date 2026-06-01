import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Button,
    Card,
    Checkbox,
    Field,
    FormError,
    Input,
    PageHeader,
    Select,
} from '@/Components/ui';

export default function SettingsIndex({ settings, nicheMaintenance, health, env }) {
    const { flash } = usePage().props;
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        default_country: settings.default_country,
        agency_name: settings.agency_name,
        booking_url: settings.booking_url,
    });
    const scanForm = useForm({ force: false });
    const bootstrapForm = useForm({ confirm: '' });

    const submit = (e) => {
        e.preventDefault();
        patch('/settings');
    };

    const formatKey = (key) => key.replace(/_/g, ' ');

    return (
        <AuthenticatedLayout>
            <Head title="Settings" />

            <main className="page" style={{ maxWidth: 720 }}>
                <PageHeader
                    eyebrow="Settings"
                    title="Workspace defaults."
                    sub="API health, storage drivers, and values that pre-fill search and outreach."
                />

                {flash?.success && (
                    <p className="micro" style={{ color: 'var(--color-positive)', marginBottom: 16 }}>
                        {flash.success}
                    </p>
                )}

                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    <Card title="AI client access">
                        <p className="micro" style={{ margin: '0 0 8px' }}>
                            Connect Cursor, Claude, or ChatGPT to monitor scans and start single-site audits from chat.
                        </p>
                        <p className="micro" style={{ margin: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                            <Link href="/settings/connected-apps" className="micro" style={{ fontWeight: 500 }}>
                                Connected apps (OAuth) →
                            </Link>
                            <Link href="/settings/mcp-keys" className="micro" style={{ fontWeight: 500 }}>
                                MCP keys (header auth) →
                            </Link>
                        </p>
                    </Card>

                    <Card title="API & storage health">
                        <ul style={{ display: 'flex', flexDirection: 'column', gap: 12, margin: 0, padding: 0, listStyle: 'none' }}>
                            {Object.entries(health).map(([key, status]) => (
                                <li
                                    key={key}
                                    style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 16 }}
                                >
                                    <span className="micro" style={{ fontWeight: 500, textTransform: 'capitalize' }}>
                                        {formatKey(key)}
                                    </span>
                                    <span
                                        className="micro"
                                        style={status.ok ? undefined : { color: 'var(--color-sev-critical)' }}
                                    >
                                        {status.message}
                                    </span>
                                </li>
                            ))}
                            <li style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 16 }}>
                                <span className="micro" style={{ fontWeight: 500 }}>Reports disk</span>
                                <span className="micro">{env.reports_disk}</span>
                            </li>
                            <li style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 16 }}>
                                <span className="micro" style={{ fontWeight: 500 }}>Audit driver</span>
                                <span className="micro">{env.audit_driver}</span>
                            </li>
                            <li style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 16 }}>
                                <span className="micro" style={{ fontWeight: 500 }}>Screenshot driver</span>
                                <span className="micro">{env.screenshot_driver}</span>
                            </li>
                        </ul>
                    </Card>

                    <Card title="Defaults">
                        <form onSubmit={submit}>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                                <Field label="Default country">
                                    <Select
                                        value={data.default_country}
                                        onChange={(e) => setData('default_country', e.target.value)}
                                    >
                                        <option value="GB">United Kingdom</option>
                                        <option value="IE">Ireland</option>
                                        <option value="US">United States</option>
                                    </Select>
                                    <FormError message={errors.default_country} />
                                </Field>

                                <Field label="Agency name" hint="pre-fills the outreach generator">
                                    <Input
                                        type="text"
                                        value={data.agency_name}
                                        onChange={(e) => setData('agency_name', e.target.value)}
                                        placeholder="nthdesigns"
                                    />
                                </Field>

                                <Field label="Booking URL" hint="public report CTA; TidyCal URLs open on /book with your branding; overrides REPORT_BOOKING_URL">
                                    <Input
                                        type="url"
                                        value={data.booking_url}
                                        onChange={(e) => setData('booking_url', e.target.value)}
                                        placeholder="https://tidycal.com/yourhandle"
                                    />
                                    <FormError message={errors.booking_url} />
                                </Field>

                                <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginTop: 4 }}>
                                    <Button kind="primary" type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save settings'}
                                    </Button>
                                    {recentlySuccessful && (
                                        <p className="micro" style={{ color: 'var(--color-positive)' }}>
                                            Saved.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </form>
                    </Card>

                    <Card title="Niche maintenance">
                        <ul style={{ display: 'flex', flexDirection: 'column', gap: 12, margin: '0 0 24px', padding: 0, listStyle: 'none' }}>
                            <li style={{ display: 'flex', justifyContent: 'space-between', gap: 16 }}>
                                <span className="micro" style={{ fontWeight: 500 }}>Niches configured</span>
                                <span className="micro">{nicheMaintenance.niche_count}</span>
                            </li>
                            <li style={{ display: 'flex', justifyContent: 'space-between', gap: 16 }}>
                                <span className="micro" style={{ fontWeight: 500 }}>Cities configured</span>
                                <span className="micro">{nicheMaintenance.city_count}</span>
                            </li>
                            <li style={{ display: 'flex', justifyContent: 'space-between', gap: 16 }}>
                                <span className="micro" style={{ fontWeight: 500 }}>Last market scan</span>
                                <span className="micro">{nicheMaintenance.last_scan_human}</span>
                            </li>
                            <li style={{ display: 'flex', justifyContent: 'space-between', gap: 16 }}>
                                <span className="micro" style={{ fontWeight: 500 }}>Config generated</span>
                                <span className="micro">{nicheMaintenance.config_generated ?? 'Unknown'}</span>
                            </li>
                        </ul>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                scanForm.post('/settings/niches/scan');
                            }}
                            style={{ display: 'flex', flexDirection: 'column', gap: 16, marginBottom: 32 }}
                        >
                            <p className="micro" style={{ margin: 0 }}>
                                Dispatches sample scans for all configured niche×city pairs (respects ignored niches).
                                A full catalog is ~6,000 queue jobs.
                            </p>
                            <label className="micro" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <Checkbox
                                    checked={scanForm.data.force}
                                    onChange={(checked) => scanForm.setData('force', checked)}
                                />
                                Force re-scan (include rows already complete today)
                            </label>
                            <div>
                                <Button kind="secondary" type="submit" disabled={scanForm.processing}>
                                    {scanForm.processing ? 'Queuing…' : 'Run market scan'}
                                </Button>
                            </div>
                        </form>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                bootstrapForm.post('/settings/niches/bootstrap');
                            }}
                            style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
                        >
                            <p className="micro" style={{ margin: 0, color: 'var(--color-sev-critical)' }}>
                                Re-fetches UK cities and Google Places types, validates in Birmingham, and overwrites{' '}
                                <code>config/niches.php</code>. On Laravel Cloud, commit and redeploy the updated config
                                for changes to persist.
                            </p>
                            <Field label="Type REFRESH to confirm">
                                <Input
                                    type="text"
                                    value={bootstrapForm.data.confirm}
                                    onChange={(e) => bootstrapForm.setData('confirm', e.target.value)}
                                    placeholder="REFRESH"
                                    autoComplete="off"
                                />
                                <FormError message={bootstrapForm.errors.confirm} />
                            </Field>
                            <div>
                                <Button
                                    kind="destructive"
                                    type="submit"
                                    disabled={bootstrapForm.processing || bootstrapForm.data.confirm !== 'REFRESH'}
                                >
                                    {bootstrapForm.processing ? 'Queuing…' : 'Refresh catalog'}
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
