import { Head, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Button,
    Card,
    Field,
    FormError,
    Input,
    PageHeader,
    Select,
} from '@/Components/ui';

export default function SettingsIndex({ settings, health, env }) {
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

                                <Field label="Booking URL" hint="public report CTA; overrides REPORT_BOOKING_URL">
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
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
