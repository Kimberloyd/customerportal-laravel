import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { SecurityFields } from '@/components/UserForm';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';

export function ResetPasswordModal({ open, onOpenChange, user }) {
    const { data, setData, post, processing, errors, clearErrors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const updateField = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const resetAndClose = () => {
        reset();
        clearErrors();
        onOpenChange(false);
    };

    const close = () => {
        if (!processing) resetAndClose();
    };

    const submit = () => {
        if (processing || !user) return;
        post(route('admin.users.reset-password', user.id), {
            preserveScroll: true,
            onSuccess: resetAndClose,
        });
    };

    if (!user) return null;

    return (
        <Modal
            open={open}
            onClose={close}
            title={`Reset ${user.full_name}'s password`}
            maxWidth={560}
            closeOnBackdrop={!processing}
            closeOnEscape={!processing}
            className="[&>div:first-child]:px-6 [&>div:first-child]:pb-5 [&>div:first-child]:pt-6 [&>div:first-child_h2]:!text-lg [&>div:last-child]:mt-auto [&>div:last-child]:px-6 [&>div:last-child]:py-5"
            footer={
                <>
                    <Button
                        type="button"
                        variant="tertiary"
                        className="h-10 rounded-md px-5 text-sm"
                        onClick={close}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="primary"
                        className="h-10 rounded-md px-5 text-sm"
                        onClick={submit}
                        loading={processing}
                    >
                        Reset password
                    </Button>
                </>
            }
        >
            <AutoHeightReveal>
                <div className="space-y-4 px-2 pt-2">
                    <p className="text-sm text-stone-600 dark:text-stone-300">
                        Set a new password for this account. They&rsquo;ll need to sign in again with it on other devices.
                    </p>
                    <SecurityFields data={data} updateField={updateField} errors={errors} isEdit optional={false} />
                </div>
            </AutoHeightReveal>
        </Modal>
    );
}
