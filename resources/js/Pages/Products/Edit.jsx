import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ProductForm from '@/Components/ProductForm';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ product, categoryOptions }) {
    const { data, setData, put, processing, errors } = useForm({
        sku: product.sku ?? '',
        product_name: product.product_name ?? '',
        category: product.category ?? 'OTHERS',
        generic_name: product.generic_name ?? '',
        unit: product.unit ?? '',
        unit_price: product.unit_price ?? '',
        description: product.description ?? '',
        is_active: product.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('products.update', product.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit {product.product_name}
                </h2>
            }
        >
            <Head title={`Edit ${product.product_name}`} />

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
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
