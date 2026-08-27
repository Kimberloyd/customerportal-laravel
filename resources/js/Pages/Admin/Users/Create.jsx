import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import UserForm from '@/components/UserForm';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ allowAdminCreation, customers }) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        full_name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        role: 'employee',
        customer_id: '',
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.users.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Create Account
                </h2>
            }
        >
            <Head title="Create Account" />

            <div className="mx-auto max-w-2xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-6 shadow-sm">
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
                    <div className="flex justify-end">
                        <Button type="submit" variant="primary" disabled={processing}>
                            Create Account
                        </Button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
