import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

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

        put(route('purchase-orders.update', order.public_id), {
            data: formData,
            forceFormData: true,
        });
    };

    const itemColumns = useMemo(
        () => [
            { key: 'display_name', header: 'Product' },
            {
                key: 'delivered_quantity',
                header: 'Delivered',
                cell: (item) => item.delivered_quantity ?? 0,
            },
            {
                key: 'quantity',
                header: 'Quantity',
                cell: (item) => (
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
                        aria-label={`Quantity for ${item.display_name}`}
                        className="w-24 rounded-md border-border bg-transparent text-sm text-foreground focus-visible:ring-[color:var(--focus-ring)] disabled:bg-muted"
                    />
                ),
            },
        ],
        [order.is_terminal, data.quantities],
    );

    return (
        <AuthenticatedLayout
            header={
                <h2 className="type-page-heading text-foreground">
                    Edit {order.po_number}
                </h2>
            }
        >
            <Head title={`Edit ${order.po_number}`} />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-6 rounded-lg border border-border bg-card p-6">
                    {order.is_terminal && (
                        <div className="rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300" role="status">
                            This order is {order.status} — only remarks can be changed.
                        </div>
                    )}
                    {Object.entries(errors).map(([key, message]) => (
                        <div key={key} role="alert" className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">{message}</div>
                    ))}

                    <div>
                        <label htmlFor="po-customer" className="type-label text-foreground">Customer</label>
                        {lockedCustomerId || order.is_terminal ? (
                            <p id="po-customer" className="mt-1 text-sm text-foreground">
                                {customers.find((c) => c.id === data.customer_id)?.company_name}
                            </p>
                        ) : (
                            <select
                                id="po-customer"
                                value={data.customer_id}
                                onChange={(e) => setData('customer_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-border bg-transparent text-sm text-foreground focus-visible:ring-[color:var(--focus-ring)]"
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
                        <label htmlFor="po-remarks" className="type-label text-foreground">Remarks</label>
                        <textarea
                            id="po-remarks"
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            rows={3}
                            className="mt-1 block w-full rounded-md border-border bg-transparent text-sm text-foreground focus-visible:ring-[color:var(--focus-ring)]"
                        />
                    </div>

                    {!order.is_terminal && (
                        <div>
                            <label htmlFor="po-attachment" className="type-label text-foreground">Attachment</label>
                            {order.has_attachment && !data.remove_attachment && (
                                <label className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        checked={data.remove_attachment}
                                        onChange={(e) => setData('remove_attachment', e.target.checked)}
                                        className="rounded border-border text-primary focus-visible:ring-[color:var(--focus-ring)]"
                                    />
                                    Remove existing attachment
                                </label>
                            )}
                            <input
                                id="po-attachment"
                                type="file"
                                accept=".pdf,.png,.jpg,.jpeg"
                                onChange={(e) => setData('po_attachment', e.target.files[0] ?? null)}
                                className="mt-1 block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground hover:file:bg-hover"
                            />
                        </div>
                    )}

                    <div>
                        <label className="mb-2 block type-label text-foreground">Product Lines</label>
                        <Table
                            data={order.items}
                            columns={itemColumns}
                            getRowId={(item) => String(item.id)}
                            resizable
                            emptyState="No products have been added to this order."
                        />
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" variant="primary" disabled={processing}>
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
