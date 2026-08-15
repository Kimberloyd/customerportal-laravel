import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CustomerForm from '@/Components/CustomerForm';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ channelOptions }) {
    const { data, setData, post, processing, errors } = useForm({
        company_name: '',
        channel: 'OTHERS',
        contact_person: '',
        email: '',
        phone: '',
        address: '',
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('customers.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Create Customer
                </h2>
            }
        >
            <Head title="Create Customer" />

            <div className="mx-auto max-w-2xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <CustomerForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        channelOptions={channelOptions}
                    />
                    <div className="flex justify-end">
                        <Button type="submit" variant="primary" disabled={processing}>
                            Create Customer
                        </Button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
