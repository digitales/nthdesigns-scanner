import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Card, Field, FormError, Input, Page, PageHeader } from '@/Components/ui';

export default function McpKeys({ keys, newKey, mcpUrl }) {
    const { flash } = usePage().props;
    const [copied, setCopied] = useState(false);
    const createForm = useForm({ label: '' });

    const createKey = (e) => {
        e.preventDefault();
        createForm.post('/settings/mcp-keys');
    };

    const updateLabel = (keyId, label) => {
        router.patch(`/settings/mcp-keys/${keyId}`, { [`label_${keyId}`]: label });
    };

    const revokeKey = (keyId) => {
        if (!confirm('Revoke this key? Clients using it will stop working until you add a new key.')) {
            return;
        }
        router.delete(`/settings/mcp-keys/${keyId}`);
    };

    const copyNewKey = async () => {
        if (!newKey) {
            return;
        }
        await navigator.clipboard.writeText(newKey);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <AuthenticatedLayout>
            <Head title="MCP keys" />

            <Page width="narrow">
                <PageHeader
                    eyebrow="Settings"
                    title="MCP keys"
                    sub="Personal API keys for Cursor, Claude, or other clients that cannot use OAuth. Revoke anytime."
                />

                <p className="micro mb-16">
                    <Link href="/settings">← Back to settings</Link>
                    {' · '}
                    <Link href="/settings/connected-apps">Connected apps (OAuth)</Link>
                </p>

                {flash?.success && (
                    <p className="micro text-positive mb-16">
                        {flash.success}
                    </p>
                )}

                {newKey && (
                    <Card title="Your new MCP key — copy it now" className="mb-24">
                        <p className="micro mb-12">
                            This is the only time you will see it. Store it in a password manager.
                        </p>
                        <div className="input-row">
                            <code className="code-block">
                                {newKey}
                            </code>
                            <Button type="button" kind="primary" size="sm" onClick={copyNewKey}>
                                {copied ? 'Copied' : 'Copy'}
                            </Button>
                        </div>
                        <p className="micro mt-12">
                            Header: <code>x-scanner-key: {newKey}</code>
                        </p>
                    </Card>
                )}

                <Card title="Your MCP keys">
                    {keys.length === 0 ? (
                        <p className="micro">No keys yet. Create one to connect clients that use header auth.</p>
                    ) : (
                        <ul className="settings-list">
                            {keys.map((key) => (
                                <KeyRow key={key.id} keyData={key} onUpdate={updateLabel} onRevoke={revokeKey} />
                            ))}
                        </ul>
                    )}

                    <form onSubmit={createKey} className="settings-form-divider">
                        <Field label="Label (optional)">
                            <Input
                                type="text"
                                value={createForm.data.label}
                                onChange={(e) => createForm.setData('label', e.target.value)}
                                placeholder="e.g. Cursor — work laptop"
                                maxLength={64}
                            />
                            <FormError message={createForm.errors.label} />
                        </Field>
                        <div className="mt-12">
                            <Button type="submit" kind="primary" disabled={createForm.processing}>
                                {keys.length === 0 ? 'Create MCP key' : 'Create another key'}
                            </Button>
                        </div>
                    </form>
                </Card>

                <Card title="Endpoint" className="mt-24">
                    <code className="micro break-all">
                        {mcpUrl}
                    </code>
                    <p className="micro mt-8">
                        Send your key in the <code>x-scanner-key</code> header (preferred). OAuth is recommended when your
                        client supports it — use Connected apps to revoke OAuth sessions.
                    </p>
                </Card>
            </Page>
        </AuthenticatedLayout>
    );
}

function KeyRow({ keyData, onUpdate, onRevoke }) {
    const [label, setLabel] = useState(keyData.label);

    return (
        <li className="settings-row">
            <div className="settings-row-body">
                <Field label="Label">
                    <div className="input-row">
                        <Input
                            type="text"
                            value={label}
                            onChange={(e) => setLabel(e.target.value)}
                            maxLength={64}
                            className="input-grow"
                        />
                        <Button type="button" kind="secondary" size="sm" onClick={() => onUpdate(keyData.id, label)}>
                            Update label
                        </Button>
                    </div>
                </Field>
                <p className="micro mt-8">
                    Key: <code>scanner_••••••••••••••••••••••••••••••••</code>
                </p>
                {keyData.last_used_at && (
                    <p className="micro">Last used {new Date(keyData.last_used_at).toLocaleString()}</p>
                )}
            </div>
            <Button type="button" kind="secondary" size="sm" onClick={() => onRevoke(keyData.id)}>
                Revoke
            </Button>
        </li>
    );
}
