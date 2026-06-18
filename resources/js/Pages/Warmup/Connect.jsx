import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Button,
    Card,
    Checkbox,
    Field,
    FormError,
    Input,
    LinkButton,
    Page,
    PageHeader,
    Segmented,
    Stack,
} from '@/Components/ui';

const PROVIDERS = {
    fastmail: {
        label: 'Fastmail',
        imap_host: 'imap.fastmail.com',
        imap_port: 993,
        smtp_host: 'smtp.fastmail.com',
        smtp_port: 587,
        hint: 'Generate an app password in Fastmail: Settings → Privacy & Security → App Passwords.',
    },
    gmail: {
        label: 'Gmail',
        imap_host: 'imap.gmail.com',
        imap_port: 993,
        smtp_host: 'smtp.gmail.com',
        smtp_port: 587,
        hint: 'Enable IMAP in Gmail settings, then create an app password at myaccount.google.com/apppasswords.',
    },
    outlook: {
        label: 'Outlook',
        imap_host: 'outlook.office365.com',
        imap_port: 993,
        smtp_host: 'smtp.office365.com',
        smtp_port: 587,
        hint: 'Use an app password if your account has two-factor authentication enabled.',
    },
    generic: {
        label: 'Other',
        imap_host: '',
        imap_port: 993,
        smtp_host: '',
        smtp_port: 587,
        hint: 'Enter IMAP and SMTP host details from your email provider.',
    },
};

export default function WarmupConnect() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        provider: 'fastmail',
        imap_host: PROVIDERS.fastmail.imap_host,
        imap_port: PROVIDERS.fastmail.imap_port,
        smtp_host: PROVIDERS.fastmail.smtp_host,
        smtp_port: PROVIDERS.fastmail.smtp_port,
        username: '',
        password: '',
        is_outreach_mailbox: true,
        is_seed_mailbox: false,
    });

    const [connectionTest, setConnectionTest] = useState(null);
    const [testing, setTesting] = useState(false);

    const selectProvider = (provider) => {
        const preset = PROVIDERS[provider];
        setData({
            ...data,
            provider,
            imap_host: preset.imap_host,
            imap_port: preset.imap_port,
            smtp_host: preset.smtp_host,
            smtp_port: preset.smtp_port,
        });
        setConnectionTest(null);
    };

    const testConnection = async () => {
        setTesting(true);
        setConnectionTest(null);

        try {
            const response = await fetch('/warmup/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({
                    imap_host: data.imap_host,
                    imap_port: data.imap_port,
                    smtp_host: data.smtp_host,
                    smtp_port: data.smtp_port,
                    username: data.username,
                    password: data.password,
                }),
            });

            const result = await response.json();

            if (response.ok) {
                setConnectionTest({ ok: true, imap: result.imap, smtp: result.smtp });
            } else {
                setConnectionTest({ ok: false, error: result.error ?? 'Connection failed' });
            }
        } catch {
            setConnectionTest({ ok: false, error: 'Network error during connection test' });
        } finally {
            setTesting(false);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post('/warmup');
    };

    const providerHint = PROVIDERS[data.provider]?.hint;

    return (
        <AuthenticatedLayout>
            <Head title="Connect mailbox" />

            <Page width="narrow">
                <PageHeader
                    eyebrow="Warmup"
                    title="Connect a mailbox."
                    sub="Use an app password, not your main account password. Test the connection before saving."
                />

                <Card>
                    <form onSubmit={submit}>
                        <Stack gap={16}>
                            <Field label="Provider">
                                <Segmented
                                    value={data.provider}
                                    onChange={selectProvider}
                                    options={Object.entries(PROVIDERS).map(([value, { label }]) => ({
                                        value,
                                        label,
                                    }))}
                                />
                            </Field>

                            {providerHint && (
                                <p className="micro text-muted m-0">{providerHint}</p>
                            )}

                            <Field label="Email address">
                                <Input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    autoComplete="email"
                                />
                                <FormError message={errors.email} />
                            </Field>

                            <Field label="Username">
                                <Input
                                    value={data.username}
                                    onChange={(e) => setData('username', e.target.value)}
                                    autoComplete="username"
                                />
                                <FormError message={errors.username} />
                            </Field>

                            <Field label="App password">
                                <Input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => {
                                        setData('password', e.target.value);
                                        setConnectionTest(null);
                                    }}
                                    autoComplete="new-password"
                                />
                                <FormError message={errors.password} />
                            </Field>

                            <div className="grid grid-cols-2 gap-12">
                                <Field label="IMAP host">
                                    <Input
                                        value={data.imap_host}
                                        onChange={(e) => setData('imap_host', e.target.value)}
                                    />
                                </Field>
                                <Field label="IMAP port">
                                    <Input
                                        type="number"
                                        value={data.imap_port}
                                        onChange={(e) => setData('imap_port', Number(e.target.value))}
                                    />
                                </Field>
                                <Field label="SMTP host">
                                    <Input
                                        value={data.smtp_host}
                                        onChange={(e) => setData('smtp_host', e.target.value)}
                                    />
                                </Field>
                                <Field label="SMTP port">
                                    <Input
                                        type="number"
                                        value={data.smtp_port}
                                        onChange={(e) => setData('smtp_port', Number(e.target.value))}
                                    />
                                </Field>
                            </div>

                            <Stack as="label" className="micro" direction="row" gap={8} align="center">
                                <Checkbox
                                    checked={data.is_outreach_mailbox}
                                    onChange={(checked) => setData('is_outreach_mailbox', checked)}
                                />
                                Use as outreach mailbox (being warmed)
                            </Stack>
                            <Stack as="label" className="micro" direction="row" gap={8} align="center">
                                <Checkbox
                                    checked={data.is_seed_mailbox}
                                    onChange={(checked) => setData('is_seed_mailbox', checked)}
                                />
                                Use as seed mailbox (receives and replies to warmup emails)
                            </Stack>

                            <FormError message={errors.connection} />

                            <div className="flex flex-wrap gap-8 items-center">
                                <Button type="button" kind="secondary" onClick={testConnection} disabled={testing}>
                                    {testing ? 'Testing…' : 'Test connection'}
                                </Button>

                                {connectionTest?.ok && (
                                    <span className="micro text-positive">IMAP and SMTP OK</span>
                                )}
                                {connectionTest && !connectionTest.ok && (
                                    <span className="micro text-critical">{connectionTest.error}</span>
                                )}
                            </div>

                            <div className="flex gap-8">
                                <Button type="submit" disabled={processing || !connectionTest?.ok}>
                                    Save mailbox
                                </Button>
                                <LinkButton href="/warmup" kind="secondary">
                                    Cancel
                                </LinkButton>
                            </div>
                        </Stack>
                    </form>
                </Card>
            </Page>
        </AuthenticatedLayout>
    );
}
