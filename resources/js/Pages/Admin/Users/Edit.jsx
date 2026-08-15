import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import UserForm from '@/Components/UserForm';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ user, allowAdminCreation, customers, selectedCustomerId, isSelf }) {
    const { data, setData, put, processing, errors } = useForm({
        full_name: user.full_name ?? '',
        email: user.email ?? '',
        password: '',
        password_confirmation: '',
        role: user.role,
        customer_id: selectedCustomerId ?? '',
        is_active: user.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.users.update', user.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit {user.full_name}
                </h2>
            }
        >
            <Head title={`Edit ${user.full_name}`} />

            <div className="mx-auto max-w-2xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <UserForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        allowAdminCreation={allowAdminCreation}
                        customers={customers}
                        isEdit
                        isSelf={isSelf}
                        editingUserId={user.id}
                    />
                    <div className="flex justify-end">
                        <Button type="submit" variant="primary" disabled={processing}>
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
