import { useForm } from '@inertiajs/react';
import { Button, Card, Checkbox, Field, FormError, Grid, Input, SkipBanner, Stack } from '@/Components/ui';

function Subsection({ title, children }) {
    return (
        <Stack gap={12} className="stack--section">
            <div className="micro micro-uppercase">{title}</div>
            {children}
        </Stack>
    );
}

function Stat({ label, value }) {
    return (
        <div className="niche-stat">
            <span className="micro text-medium niche-stat-label">{label}</span>
            <span className="micro tabular">{value}</span>
        </div>
    );
}

export default function NicheMaintenanceCard({ nicheMaintenance }) {
    const scanForm = useForm({ force: false });
    const bootstrapForm = useForm({ confirm: '' });

    return (
        <Card title="Niche maintenance">
            <Stack gap={16}>
                <Grid cols={2} gap={12}>
                    <Stat label="Niches configured" value={nicheMaintenance.niche_count} />
                    <Stat label="Cities configured" value={nicheMaintenance.city_count} />
                    <Stat label="Last market scan" value={nicheMaintenance.last_scan_human} />
                    <Stat label="Config generated" value={nicheMaintenance.config_generated ?? 'Unknown'} />
                </Grid>

                <Subsection title="Market scan">
                    <Stack
                        as="form"
                        gap={12}
                        onSubmit={(e) => {
                            e.preventDefault();
                            scanForm.post('/settings/niches/scan');
                        }}
                    >
                        <p className="micro m-0">
                            Dispatches sample scans for all configured niche×city pairs (respects ignored niches).
                            A full catalog is ~6,000 queue jobs.
                        </p>
                        <Stack direction="row" justify="between" align="center" wrap gap={12}>
                            <Stack as="label" className="micro" direction="row" gap={8} align="center">
                                <Checkbox
                                    checked={scanForm.data.force}
                                    onChange={(checked) => scanForm.setData('force', checked)}
                                />
                                Force re-scan (include rows already complete today)
                            </Stack>
                            <Button kind="secondary" type="submit" disabled={scanForm.processing}>
                                {scanForm.processing ? 'Queuing…' : 'Run market scan'}
                            </Button>
                        </Stack>
                    </Stack>
                </Subsection>

                <Subsection title="Refresh catalog">
                    <Stack
                        as="form"
                        gap={12}
                        onSubmit={(e) => {
                            e.preventDefault();
                            bootstrapForm.post('/settings/niches/bootstrap');
                        }}
                    >
                        <SkipBanner kind="critical" className="m-0">
                            Re-fetches UK cities and Google Places types, validates in Birmingham, and overwrites{' '}
                            <code>config/niches.php</code>. On Laravel Cloud, commit and redeploy the updated config
                            for changes to persist.
                        </SkipBanner>
                        <Stack direction="row" gap={12} align="end" wrap>
                            <div className="niche-confirm-field">
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
                            </div>
                            <Button
                                kind="destructive"
                                type="submit"
                                disabled={bootstrapForm.processing || bootstrapForm.data.confirm !== 'REFRESH'}
                            >
                                {bootstrapForm.processing ? 'Queuing…' : 'Refresh catalog'}
                            </Button>
                        </Stack>
                    </Stack>
                </Subsection>
            </Stack>
        </Card>
    );
}
