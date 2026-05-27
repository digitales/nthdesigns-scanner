import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui';
import { Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <AuthLayout title="Email Verification">
            <h1 className="font-serif text-3xl font-normal tracking-tight text-ink mb-3">Verify email</h1>

            <p className="micro mb-6">
                Thanks for signing up! Before getting started, could you verify
                your email address by clicking on the link we just emailed to
                you? If you didn't receive the email, we will gladly send you
                another.
            </p>

            {status === 'verification-link-sent' && (
                <p className="micro mb-4" style={{ color: 'var(--color-positive)' }}>
                    A new verification link has been sent to the email address
                    you provided during registration.
                </p>
            )}

            <form onSubmit={submit}>
                <div className="flex items-center justify-between gap-3">
                    <Button kind="primary" type="submit" disabled={processing}>
                        Resend Verification Email
                    </Button>

                    <Link href={route('logout')} method="post" as="button" className="micro">
                        Log Out
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}
