import { Modal } from '@/components/interior/modal';
import UserForm from '@/components/UserForm';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';

const FORM_ID = 'create-user-modal-form';

export function CreateUserModal({ open, onOpenChange, allowAdminCreation = false, customers = [] }) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        clearErrors,
        reset,
        transform,
    } = useForm({
        full_name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'employee',
        customer_id: '',
        is_active: true,
    });

    const resetAndClose = () => {
        reset();
        clearErrors();
        onOpenChange(false);
    };

    const close = () => {
        if (!processing) resetAndClose();
    };

    const submit = (event) => {
        event.preventDefault();
        transform((values) => ({
            ...values,
            is_active: values.is_active ? '1' : '0',
        }));
        post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: resetAndClose,
        });
    };

    return (
        <Modal
            open={open}
            onClose={close}
            title="Add user"
            description="Create a portal account and choose what the user can access."
            maxWidth={640}
            maxHeight="90vh"
            closeOnBackdrop={!processing}
            closeOnEscape={!processing}
            className="[&>div:first-child]:px-6 [&>div:first-child]:pb-5 [&>div:first-child]:pt-6 [&>div:first-child_h2]:!text-lg [&>div:first-child_p]:!mt-3 [&>div:first-child_p]:!text-sm [&>div:last-child]:px-6 [&>div:last-child]:py-5"
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
                        type="submit"
                        form={FORM_ID}
                        variant="primary"
                        className="h-10 rounded-md px-5 text-sm"
                        loading={processing}
                    >
                        Create user
                    </Button>
                </>
            }
        >
            <form id={FORM_ID} onSubmit={submit} className="space-y-4 px-2 pt-1">
                <UserForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    clearErrors={clearErrors}
                    allowAdminCreation={allowAdminCreation}
                    customers={customers}
                    isEdit={false}
                    isSelf={false}
                />
            </form>
        </Modal>
    );
}
