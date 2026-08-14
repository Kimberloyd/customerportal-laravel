import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ProductForm from '@/Components/ProductForm';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ categoryOptions }) {
    const { data, setData, post, processing, errors } = useForm({
        sku: '',
        product_name: '',
        category: 'OTHERS',
        generic_name: '',
        unit: '',
        unit_price: '',
        description: '',
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('products.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Create Product
                </h2>
            }
        >
            <Head title="Create Product" />

            <div className="mx-auto max-w-2xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <ProductForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        categoryOptions={categoryOptions}
                        showActiveToggle
                    />
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            Create Product
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
