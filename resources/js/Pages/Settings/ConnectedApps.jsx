import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Card, PageHeader } from '@/Components/ui';

export default function ConnectedApps({ families }) {
    const { flash } = usePage().props;

    const disconnect = (familyId) => {
        if (!confirm('Disconnect this app? It will need to re-authorize.')) {
            return;
        }
        router.delete(`/settings/connected-apps/${familyId}`);
    };

    const disconnectAll = () => {
        if (!confirm('Disconnect all connected apps? Each will need to re-authorize.')) {
            return;
        }
        router.delete('/settings/connected-apps');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Connected apps" />

            <main className="page" style={{ maxWidth: 720 }}>
                <PageHeader
                    eyebrow="Settings"
                    title="Connected apps"
                    sub="OAuth-connected AI tools (Cursor, Claude, ChatGPT). Revoking forces the client to sign in again."
                />

                <p className="micro" style={{ marginBottom: 16 }}>
                    <Link href="/settings">← Back to settings</Link>
                    {' · '}
                    <Link href="/settings/mcp-keys">MCP keys (header auth)</Link>
                </p>

                {flash?.success && (
                    <p className="micro" style={{ color: 'var(--color-positive)', marginBottom: 16 }}>
                        {flash.success}
                    </p>
                )}

                <Card title="Your connections">
                    {families.length > 0 && (
                        <div style={{ marginBottom: 12 }}>
                            <Button type="button" kind="secondary" size="sm" onClick={disconnectAll}>
                                Disconnect all
                            </Button>
                        </div>
                    )}
                    {families.length === 0 ? (
                        <p className="micro">
                            No OAuth-connected apps yet. Add this scanner as a remote MCP server in Cursor or Claude
                            and complete the sign-in flow.
                        </p>
                    ) : (
                        <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {families.map((family) => (
                                <li
                                    key={family.id}
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'flex-start',
                                        gap: 16,
                                        padding: '12px 0',
                                        borderBottom: '1px solid var(--color-border)',
                                    }}
                                >
                                    <div>
                                        <p className="micro" style={{ fontWeight: 600, marginBottom: 4 }}>
                                            {family.redirect_host}
                                        </p>
                                        <p className="micro">Scope: {family.scope}</p>
                                        <p className="micro">
                                            Connected {new Date(family.issued_at).toLocaleString()} · Expires{' '}
                                            {new Date(family.absolute_expires_at).toLocaleString()}
                                        </p>
                                        {family.last_used_at && (
                                            <p className="micro">
                                                Last used {new Date(family.last_used_at).toLocaleString()}
                                                {family.ip_address ? ` · ${family.ip_address}` : ''}
                                            </p>
                                        )}
                                    </div>
                                    <Button type="button" kind="secondary" size="sm" onClick={() => disconnect(family.id)}>
                                        Disconnect
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <p className="micro" style={{ marginTop: 24 }}>
                    MCP endpoint: <code>{`${window.location.origin}/api/mcp`}</code> — use OAuth when adding the remote
                    connector in your AI client.
                </p>
            </main>
        </AuthenticatedLayout>
    );
}
