import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Card, Page, PageHeader, Stack } from '@/Components/ui';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout>
            <Head title="Profile" />

            <Page width="narrow">
                <PageHeader eyebrow="Account" title="Profile & security." />

                <p className="micro profile-nav-links">
                    <Link href="/settings/connected-apps">Connected apps</Link>
                    <Link href="/settings/mcp-keys">MCP keys</Link>
                </p>

                <Stack gap={24}>
                    <Card>
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                        />
                    </Card>
                    <Card>
                        <UpdatePasswordForm />
                    </Card>
                    <Card>
                        <DeleteUserForm />
                    </Card>
                </Stack>
            </Page>
        </AuthenticatedLayout>
    );
}
