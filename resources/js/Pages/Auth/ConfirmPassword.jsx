import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Field, FormError, Input } from '@/Components/ui';
import { useForm } from '@inertiajs/react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Confirm Password">
            <h1 className="font-serif text-3xl font-normal tracking-tight text-ink mb-3">Confirm password</h1>

            <p className="micro mb-6">
                This is a secure area of the application. Please confirm your
                password before continuing.
            </p>

            <form onSubmit={submit} className="stack">
                <Field label="Password">
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        isFocused
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <FormError message={errors.password} />
                </Field>

                <div className="flex items-center justify-end">
                    <Button kind="primary" type="submit" disabled={processing}>
                        Confirm
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
