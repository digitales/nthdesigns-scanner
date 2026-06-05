import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AgencyBookingSettingsCard from '@/Components/AgencyBookingSettingsCard';
import ApiUsageQuotasCard from '@/Components/ApiUsageQuotasCard';
import {
    Button,
    Card,
    Checkbox,
    Field,
    FormError,
    Input,
    MetaList,
    Page,
    PageHeader,
    Select,
    SplitRow,
    Stack,
} from '@/Components/ui';

export default function SettingsIndex({ settings, agencyBooking, nicheMaintenance, health, apiUsage, env }) {
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

            <Page width="narrow">
                <PageHeader
                    eyebrow="Settings"
                    title="Workspace defaults."
                    sub="API health, storage drivers, and values that pre-fill search and outreach."
                />

                {flash?.success && (
                    <p className="micro text-positive mb-16">
                        {flash.success}
                    </p>
                )}

                <Stack gap={24}>
                    <Card title="AI client access">
                        <p className="micro mb-8">
                            Connect Cursor, Claude, or ChatGPT to monitor scans and start single-site audits from chat.
                        </p>
                        <Stack as="p" className="micro m-0" gap={4}>
                            <Link href="/settings/connected-apps" className="micro text-medium">
                                Connected apps (OAuth) →
                            </Link>
                            <Link href="/settings/mcp-keys" className="micro text-medium">
                                MCP keys (header auth) →
                            </Link>
                        </Stack>
                    </Card>

                    <Card title="API & storage health">
                        <MetaList>
                            {Object.entries(health).map(([key, status]) => (
                                <SplitRow key={key}>
                                    <span className="micro text-medium capitalize">
                                        {formatKey(key)}
                                    </span>
                                    <span className={`micro${status.ok ? '' : ' text-critical'}`}>
                                        {status.message}
                                    </span>
                                </SplitRow>
                            ))}
                            <SplitRow>
                                <span className="micro text-medium">Reports disk</span>
                                <span className="micro">{env.reports_disk}</span>
                            </SplitRow>
                            <SplitRow>
                                <span className="micro text-medium">Audit driver</span>
                                <span className="micro">{env.audit_driver}</span>
                            </SplitRow>
                            <SplitRow>
                                <span className="micro text-medium">Screenshot driver</span>
                                <span className="micro">{env.screenshot_driver}</span>
                            </SplitRow>
                        </MetaList>
                    </Card>

                    {apiUsage ? <ApiUsageQuotasCard apiUsage={apiUsage} /> : null}

                    <AgencyBookingSettingsCard agencyBooking={agencyBooking} />

                    <Card title="Defaults">
                        <form onSubmit={submit}>
                            <Stack gap={16}>
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

                                <Field label="Booking URL (fallback)" hint="Used when Fastmail inline booking is off; TidyCal URLs open on /book with your branding">
                                    <Input
                                        type="url"
                                        value={data.booking_url}
                                        onChange={(e) => setData('booking_url', e.target.value)}
                                        placeholder="https://tidycal.com/yourhandle"
                                    />
                                    <FormError message={errors.booking_url} />
                                </Field>

                                <Stack direction="row" gap={12} align="center" className="mt-4">
                                    <Button kind="primary" type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save settings'}
                                    </Button>
                                    {recentlySuccessful && (
                                        <p className="micro text-positive">
                                            Saved.
                                        </p>
                                    )}
                                </Stack>
                            </Stack>
                        </form>
                    </Card>

                    <Card title="Niche maintenance">
                        <MetaList className="meta-list--spaced">
                            <SplitRow className="split-row--center">
                                <span className="micro text-medium">Niches configured</span>
                                <span className="micro">{nicheMaintenance.niche_count}</span>
                            </SplitRow>
                            <SplitRow className="split-row--center">
                                <span className="micro text-medium">Cities configured</span>
                                <span className="micro">{nicheMaintenance.city_count}</span>
                            </SplitRow>
                            <SplitRow className="split-row--center">
                                <span className="micro text-medium">Last market scan</span>
                                <span className="micro">{nicheMaintenance.last_scan_human}</span>
                            </SplitRow>
                            <SplitRow className="split-row--center">
                                <span className="micro text-medium">Config generated</span>
                                <span className="micro">{nicheMaintenance.config_generated ?? 'Unknown'}</span>
                            </SplitRow>
                        </MetaList>

                        <Stack
                            as="form"
                            gap={16}
                            className="mb-32"
                            onSubmit={(e) => {
                                e.preventDefault();
                                scanForm.post('/settings/niches/scan');
                            }}
                        >
                            <p className="micro m-0">
                                Dispatches sample scans for all configured niche×city pairs (respects ignored niches).
                                A full catalog is ~6,000 queue jobs.
                            </p>
                            <Stack as="label" className="micro" direction="row" gap={8} align="center">
                                <Checkbox
                                    checked={scanForm.data.force}
                                    onChange={(checked) => scanForm.setData('force', checked)}
                                />
                                Force re-scan (include rows already complete today)
                            </Stack>
                            <div>
                                <Button kind="secondary" type="submit" disabled={scanForm.processing}>
                                    {scanForm.processing ? 'Queuing…' : 'Run market scan'}
                                </Button>
                            </div>
                        </Stack>

                        <Stack
                            as="form"
                            gap={16}
                            onSubmit={(e) => {
                                e.preventDefault();
                                bootstrapForm.post('/settings/niches/bootstrap');
                            }}
                        >
                            <p className="micro m-0 text-critical">
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
                        </Stack>
                    </Card>
                </Stack>
            </Page>
        </AuthenticatedLayout>
    );
}
