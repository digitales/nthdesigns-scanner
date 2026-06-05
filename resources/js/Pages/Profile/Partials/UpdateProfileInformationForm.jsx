import { Button, Field, FormError, Input, Stack } from '@/Components/ui';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformationForm({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section>
            <div className="card-title card-title-flush">Profile information</div>
            <p className="micro section-intro">
                Update your account&apos;s profile information and email address.
            </p>

            <form onSubmit={submit}>
                <Stack gap={16}>
                    <Field label="Name">
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            isFocused
                            autoComplete="name"
                        />
                        <FormError message={errors.name} />
                    </Field>

                    <Field label="Email">
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="username"
                        />
                        <FormError message={errors.email} />
                    </Field>

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <div>
                            <p className="micro">
                                Your email address is unverified.{' '}
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="micro btn-link-inline"
                                >
                                    Click here to re-send the verification email.
                                </Link>
                            </p>

                            {status === 'verification-link-sent' && (
                                <p className="micro text-positive mt-8">
                                    A new verification link has been sent to your email address.
                                </p>
                            )}
                        </div>
                    )}

                    <div className="form-actions">
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
                            <p className="micro text-positive">
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Stack>
            </form>
        </section>
    );
}
