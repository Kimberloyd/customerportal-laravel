import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Input } from '@/components/motion/input';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/react';

const FIELD_CLASS_NAMES = { field: 'h-10 rounded-md', input: 'text-sm' };

export default function Edit({ user }) {
    const { data, setData, put, processing, errors, clearErrors } = useForm({
        full_name: user.full_name,
        phone: user.phone ?? '',
    });

    const updateField = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('settings.update'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Settings
                </h2>
            }
        >
            <Head title="Settings" />

            <div className="mx-auto max-w-2xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Email</label>
                        <p className="mt-1 text-sm text-gray-900">{user.email}</p>
                        <p className="mt-1 text-xs text-gray-500">
                            {user.role_label} account. Contact an administrator to change your
                            email or password.
                        </p>
                    </div>

                    <Input
                        label="Full Name"
                        type="text"
                        required
                        autoComplete="off"
                        value={data.full_name}
                        onChange={(value) => updateField('full_name', value)}
                        error={errors.full_name}
                        classNames={FIELD_CLASS_NAMES}
                    />

                    <Input
                        label="Phone Number"
                        type="tel"
                        autoComplete="off"
                        value={data.phone}
                        onChange={(value) => updateField('phone', value)}
                        error={errors.phone}
                        classNames={FIELD_CLASS_NAMES}
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
