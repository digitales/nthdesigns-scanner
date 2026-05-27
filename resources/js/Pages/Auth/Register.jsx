import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Field, FormError, Input } from '@/Components/ui';
import { Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Register">
            <h1 className="font-serif text-3xl font-normal tracking-tight text-ink mb-6">Create account</h1>

            <form onSubmit={submit} className="stack">
                <Field label="Name">
                    <Input
                        id="name"
                        name="name"
                        value={data.name}
                        autoComplete="name"
                        isFocused
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <FormError message={errors.name} />
                </Field>

                <Field label="Email">
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                        required
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
                        onChange={(e) => setData('password', e.target.value)}
                        required
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
                        required
                    />
                    <FormError message={errors.password_confirmation} />
                </Field>

                <div className="flex items-center justify-end gap-3">
                    <Link href={route('login')} className="micro">
                        Already registered?
                    </Link>
                    <Button kind="primary" type="submit" disabled={processing}>
                        Register
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
