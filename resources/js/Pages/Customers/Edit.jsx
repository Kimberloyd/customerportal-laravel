import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CustomerForm from '@/Components/CustomerForm';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ customer, channelOptions }) {
    const { data, setData, put, processing, errors } = useForm({
        company_name: customer.company_name ?? '',
        channel: customer.channel ?? 'OTHERS',
        contact_person: customer.contact_person ?? '',
        email: customer.email ?? '',
        phone: customer.phone ?? '',
        address: customer.address ?? '',
        is_active: customer.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('customers.update', customer.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit {customer.company_name}
                </h2>
            }
        >
            <Head title={`Edit ${customer.company_name}`} />

            <div className="mx-auto max-w-2xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <CustomerForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        channelOptions={channelOptions}
                        customerCode={customer.customer_code}
                    />
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
