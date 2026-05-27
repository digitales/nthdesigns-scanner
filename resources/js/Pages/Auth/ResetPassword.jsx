import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Field, FormError, Input } from '@/Components/ui';
import { useForm } from '@inertiajs/react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Reset Password">
            <h1 className="font-serif text-3xl font-normal tracking-tight text-ink mb-6">Reset password</h1>

            <form onSubmit={submit} className="stack">
                <Field label="Email">
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <FormError message={errors.email} />
                </Field>

                <Field label="Password">
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        isFocused
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <FormError message={errors.password} />
                </Field>

                <Field label="Confirm Password">
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                    <FormError message={errors.password_confirmation} />
                </Field>

                <div className="flex items-center justify-end">
                    <Button kind="primary" type="submit" disabled={processing}>
                        Reset Password
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
