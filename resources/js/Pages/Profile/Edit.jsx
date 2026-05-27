import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, PageHeader } from '@/Components/ui';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout>
            <Head title="Profile" />

            <main className="page" style={{ maxWidth: 720 }}>
                <PageHeader eyebrow="Account" title="Profile & security." />

                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
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
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
