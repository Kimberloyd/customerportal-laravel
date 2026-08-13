import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ order, customers, lockedCustomerId }) {
    const { data, setData, put, processing, errors } = useForm({
        customer_id: lockedCustomerId ?? order.customer_id,
        remarks: order.remarks ?? '',
        remove_attachment: false,
        po_attachment: null,
        quantities: Object.fromEntries(order.items.map((item) => [item.id, item.quantity])),
    });

    const submit = (e) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('customer_id', data.customer_id);
        formData.append('remarks', data.remarks ?? '');
        if (data.remove_attachment) {
            formData.append('remove_attachment', '1');
        }
        if (data.po_attachment) {
            formData.append('po_attachment', data.po_attachment);
        }
        order.items.forEach((item) => {
            formData.append(`quantity_${item.id}`, data.quantities[item.id]);
        });

        put(route('purchase-orders.update', order.id), {
            data: formData,
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit {order.po_number}
                </h2>
            }
        >
            <Head title={`Edit ${order.po_number}`} />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-6 rounded-lg bg-white p-6 shadow-sm">
                    {order.is_terminal && (
                        <div className="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                            This order is {order.status} — only remarks can be changed.
                        </div>
                    )}
                    {Object.entries(errors).map(([key, message]) => (
                        <div key={key} className="rounded-md bg-red-50 p-3 text-sm text-red-700">{message}</div>
                    ))}

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Customer</label>
                        {lockedCustomerId || order.is_terminal ? (
                            <p className="mt-1 text-sm text-gray-900">
                                {customers.find((c) => c.id === data.customer_id)?.company_name}
                            </p>
                        ) : (
                            <select
                                value={data.customer_id}
                                onChange={(e) => setData('customer_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                            >
                                {customers.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.company_name}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Remarks</label>
                        <textarea
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            rows={3}
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                        />
                    </div>

                    {!order.is_terminal && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Attachment</label>
                            {order.has_attachment && !data.remove_attachment && (
                                <label className="mt-1 flex items-center gap-2 text-sm text-gray-600">
                                    <input
                                        type="checkbox"
                                        checked={data.remove_attachment}
                                        onChange={(e) => setData('remove_attachment', e.target.checked)}
                                    />
                                    Remove existing attachment
                                </label>
                            )}
                            <input
                                type="file"
                                accept=".pdf,.png,.jpg,.jpeg"
                                onChange={(e) => setData('po_attachment', e.target.files[0] ?? null)}
                                className="mt-1 block w-full text-sm"
                            />
                        </div>
                    )}

                    <div>
                        <label className="mb-2 block text-sm font-medium text-gray-700">Product Lines</label>
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-gray-500">
                                    <th className="py-2 pr-4">Product</th>
                                    <th className="py-2 pr-4">Delivered</th>
                                    <th className="py-2 pr-4">Quantity</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {order.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-2 pr-4">{item.display_name}</td>
                                        <td className="py-2 pr-4">{item.delivered_quantity ?? 0}</td>
                                        <td className="py-2 pr-4">
                                            <input
                                                type="number"
                                                min={item.delivered_quantity ?? 1}
                                                disabled={order.is_terminal}
                                                value={data.quantities[item.id]}
                                                onChange={(e) =>
                                                    setData('quantities', {
                                                        ...data.quantities,
                                                        [item.id]: e.target.value,
                                                    })
                                                }
                                                className="w-24 rounded-md border-gray-300 text-sm disabled:bg-gray-100"
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

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
