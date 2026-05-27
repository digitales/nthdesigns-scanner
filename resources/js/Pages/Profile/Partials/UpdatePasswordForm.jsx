import { Button, Field, FormError, Input } from '@/Components/ui';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

export default function UpdatePasswordForm() {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
                }
            },
        });
    };

    return (
        <section>
            <div className="card-title" style={{ marginTop: 0 }}>Update password</div>
            <p className="micro" style={{ marginTop: 8, marginBottom: 20 }}>
                Ensure your account is using a long, random password to stay secure.
            </p>

            <form onSubmit={updatePassword}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Field label="Current password">
                        <Input
                            id="current_password"
                            ref={currentPasswordInput}
                            value={data.current_password}
                            onChange={(e) => setData('current_password', e.target.value)}
                            type="password"
                            autoComplete="current-password"
                        />
                        <FormError message={errors.current_password} />
                    </Field>

                    <Field label="New password">
                        <Input
                            id="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            type="password"
                            autoComplete="new-password"
                        />
                        <FormError message={errors.password} />
                    </Field>

                    <Field label="Confirm password">
                        <Input
                            id="password_confirmation"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            type="password"
                            autoComplete="new-password"
                        />
                        <FormError message={errors.password_confirmation} />
                    </Field>

                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <Button kind="primary" type="submit" disabled={processing}>
                            Save
                        </Button>

                        <Transition
                            show={recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="micro" style={{ color: 'var(--color-positive)' }}>
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </div>
            </form>
        </section>
    );
}
