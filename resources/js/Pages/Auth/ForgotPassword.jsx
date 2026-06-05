import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Field, FormError, Input } from '@/Components/ui';
import { useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <AuthLayout title="Forgot Password">
            <h1 className="font-serif text-3xl font-normal tracking-tight text-ink mb-3">Reset password</h1>

            <p className="micro mb-6">
                Forgot your password? No problem. Just let us know your email
                address and we will email you a password reset link that will
                allow you to choose a new one.
            </p>

            {status && (
                <p className="micro mb-4 text-positive">
                    {status}
                </p>
            )}

            <form onSubmit={submit} className="stack">
                <Field label="Email">
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        isFocused
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <FormError message={errors.email} />
                </Field>

                <div className="flex items-center justify-end">
                    <Button kind="primary" type="submit" disabled={processing}>
                        Email Password Reset Link
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
