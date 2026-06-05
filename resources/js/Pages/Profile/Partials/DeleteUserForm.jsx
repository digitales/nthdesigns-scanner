import Modal from '@/Components/Modal';
import { Button, Field, FormError, Input } from '@/Components/ui';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    return (
        <section>
            <div className="card-title card-title-flush">Delete account</div>
            <p className="micro section-intro">
                Once your account is deleted, all of its resources and data will be permanently
                deleted. Before deleting your account, please download any data or information
                that you wish to retain.
            </p>

            <Button kind="destructive" type="button" onClick={confirmUserDeletion}>
                Delete account
            </Button>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6">
                    <div className="card-title card-title-flush">
                        Are you sure you want to delete your account?
                    </div>

                    <p className="micro section-intro">
                        Once your account is deleted, all of its resources and data will be
                        permanently deleted. Please enter your password to confirm you would like
                        to permanently delete your account.
                    </p>

                    <Field label="Password">
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            isFocused
                            placeholder="Password"
                        />
                        <FormError message={errors.password} />
                    </Field>

                    <div className="form-actions-end">
                        <Button kind="secondary" type="button" onClick={closeModal}>
                            Cancel
                        </Button>
                        <Button kind="destructive" type="submit" disabled={processing}>
                            Delete account
                        </Button>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
