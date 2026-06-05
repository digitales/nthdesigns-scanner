import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Card, Page, PageHeader } from '@/Components/ui';

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

            <Page width="narrow">
                <PageHeader
                    eyebrow="Settings"
                    title="Connected apps"
                    sub="OAuth-connected AI tools (Cursor, Claude, ChatGPT). Revoking forces the client to sign in again."
                />

                <p className="micro mb-16">
                    <Link href="/settings">← Back to settings</Link>
                    {' · '}
                    <Link href="/settings/mcp-keys">MCP keys (header auth)</Link>
                </p>

                {flash?.success && (
                    <p className="micro text-positive mb-16">
                        {flash.success}
                    </p>
                )}

                <Card title="Your connections">
                    {families.length > 0 && (
                        <div className="mb-12">
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
                        <ul className="settings-list settings-list--compact">
                            {families.map((family) => (
                                <li key={family.id} className="settings-row">
                                    <div>
                                        <p className="micro settings-family-name">
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

                <p className="micro mt-24">
                    MCP endpoint: <code>{`${window.location.origin}/api/mcp`}</code> — use OAuth when adding the remote
                    connector in your AI client.
                </p>
            </Page>
        </AuthenticatedLayout>
    );
}
