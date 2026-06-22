import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Button,
    Card,
    Field,
    FormError,
    Input,
    MetaList,
    SplitRow,
    Stack,
    Status,
} from '@/Components/ui';

const PROVIDER_HINTS = {
    gmail: {
        appPasswordUrl: 'https://myaccount.google.com/apppasswords',
        failureHint:
            'Gmail requires a 16-character app password (not your login password). Enable 2-Step Verification, then create one at myaccount.google.com/apppasswords — paste without spaces.',
    },
};

function formatTestError(result) {
    if (result.imap_error && result.smtp_error) {
        return `IMAP: ${result.imap_error} SMTP: ${result.smtp_error}`;
    }
    if (result.imap_error) {
        return `IMAP: ${result.imap_error}`;
    }
    return result.error ?? result.smtp_error ?? 'Connection failed';
}

export default function WarmupConnectionPanel({ mailbox }) {
    const { flash } = usePage().props;
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const { data, setData, patch, processing, errors, reset } = useForm({
        password: '',
    });

    const testConnection = async () => {
        setTesting(true);
        setTestResult(null);

        try {
            const response = await fetch(`/warmup/${mailbox.id}/test`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            });

            const result = await response.json();

            if (response.ok) {
                setTestResult({ ok: true });
            } else {
                setTestResult({
                    ok: false,
                    error: formatTestError(result),
                    imap: result.imap,
                    smtp: result.smtp,
                });
            }
        } catch {
            setTestResult({ ok: false, error: 'Network error during connection test' });
        } finally {
            setTesting(false);
        }
    };

    const updateCredentials = (e) => {
        e.preventDefault();
        setTestResult(null);

        patch(`/warmup/${mailbox.id}/credentials`, {
            preserveScroll: true,
            onSuccess: () => {
                setTestResult({ ok: true });
                reset('password');
            },
        });
    };

    return (
        <Card title="Connection">
            <Stack gap={16}>
                <MetaList>
                    <SplitRow>
                        <span className="micro text-medium">Username</span>
                        <span className="micro">{mailbox.username}</span>
                    </SplitRow>
                    <SplitRow>
                        <span className="micro text-medium">IMAP</span>
                        <span className="micro">
                            {mailbox.imap_host}:{mailbox.imap_port}
                        </span>
                    </SplitRow>
                    <SplitRow>
                        <span className="micro text-medium">SMTP</span>
                        <span className="micro">
                            {mailbox.smtp_host}:{mailbox.smtp_port}
                        </span>
                    </SplitRow>
                </MetaList>

                <div className="warmup-connect-test">
                    <Button type="button" kind="secondary" onClick={testConnection} disabled={testing}>
                        {testing ? 'Testing…' : 'Test connection'}
                    </Button>
                    {testResult?.ok && <Status kind="ready">IMAP and SMTP connected</Status>}
                    {testResult && !testResult.ok && (
                        <Stack gap={8}>
                            {testResult.imap === true && testResult.smtp === false && (
                                <Status kind="pending">IMAP connected</Status>
                            )}
                            {testResult.imap === false && (
                                <Status kind="pending">IMAP failed</Status>
                            )}
                            <span className="micro text-critical">{testResult.error}</span>
                            {mailbox.provider === 'gmail' &&
                                (testResult.error?.includes('535') ||
                                    testResult.error?.includes('BadCredentials')) && (
                                    <p className="micro text-muted m-0">
                                        {PROVIDER_HINTS.gmail.failureHint}{' '}
                                        <a
                                            href={PROVIDER_HINTS.gmail.appPasswordUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="link-inline"
                                        >
                                            Create app password
                                        </a>
                                    </p>
                                )}
                        </Stack>
                    )}
                </div>

                {flash?.success && <div className="skip-banner banner-positive banner-success">{flash.success}</div>}

                <form onSubmit={updateCredentials}>
                    <Stack gap={12}>
                        <Field
                            label="New app password"
                            hint={
                                mailbox.provider === 'gmail'
                                    ? '16-character Gmail app password — paste without spaces. Not your login password.'
                                    : 'Enter a new app password to replace the stored credentials.'
                            }
                        >
                            <Input
                                type="password"
                                name="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="new-password"
                            />
                            <FormError message={errors.password} />
                        </Field>
                        <Button type="submit" kind="secondary" disabled={processing || !data.password}>
                            {processing ? 'Updating…' : 'Update credentials'}
                        </Button>
                    </Stack>
                </form>
            </Stack>
        </Card>
    );
}
