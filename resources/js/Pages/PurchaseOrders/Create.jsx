import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function emptyLine() {
    return { key: crypto.randomUUID(), product_id: '', product_search: '', quantity: 1 };
}

function matchProducts(products, term) {
    const needle = term.trim().toLowerCase();
    if (!needle) return [];

    return products
        .filter((p) =>
            [p.product_name, p.generic_name, p.sku, p.unit].some(
                (field) => field && field.toLowerCase().includes(needle),
            ),
        )
        .slice(0, 8);
}

export default function Create({ customers, products, lockedCustomerId }) {
    const [lines, setLines] = useState([emptyLine()]);
    const [clientError, setClientError] = useState(null);
    const { data, setData, post, processing, errors } = useForm({
        customer_id: lockedCustomerId ?? '',
        remarks: '',
        po_attachment: null,
    });

    const updateLine = (key, patch) => {
        setLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...patch } : line)));
    };

    const selectProduct = (key, product) => {
        updateLine(key, { product_id: product.id, product_search: product.product_name });
    };

    const clearProduct = (key) => {
        updateLine(key, { product_id: '', product_search: '' });
    };

    const addLine = () => setLines((prev) => [...prev, emptyLine()]);
    const removeLine = (key) => setLines((prev) => (prev.length > 1 ? prev.filter((l) => l.key !== key) : prev));

    const unresolvedLines = useMemo(
        () => lines.filter((l) => l.product_search.trim() !== '' && !l.product_id),
        [lines],
    );

    const submit = (e) => {
        e.preventDefault();

        if (unresolvedLines.length > 0) {
            setClientError('One or more product lines have a search typed but no product selected. Pick a suggestion from the list, or clear the field.');
            return;
        }
        setClientError(null);

        const resolvedLines = lines.filter((l) => l.product_id || l.product_search.trim() !== '');
        if (resolvedLines.length === 0) {
            setClientError('Please add at least one product line.');
            return;
        }

        const formData = new FormData();
        formData.append('customer_id', data.customer_id);
        formData.append('remarks', data.remarks ?? '');
        if (data.po_attachment) {
            formData.append('po_attachment', data.po_attachment);
        }
        resolvedLines.forEach((line) => {
            formData.append('product_id[]', line.product_id);
            formData.append('product_search[]', line.product_search);
            formData.append('quantity[]', line.quantity);
        });

        post(route('purchase-orders.store'), {
            data: formData,
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Create Order
                </h2>
            }
        >
            <Head title="Create Order" />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-6 rounded-lg bg-white p-6 shadow-sm">
                    {clientError && (
                        <div className="rounded-md bg-red-50 p-3 text-sm text-red-700">{clientError}</div>
                    )}
                    {Object.entries(errors).filter(([key]) => key.startsWith('items')).map(([key, message]) => (
                        <div key={key} className="rounded-md bg-red-50 p-3 text-sm text-red-700">{message}</div>
                    ))}
                    {errors.customer_id && (
                        <div className="rounded-md bg-red-50 p-3 text-sm text-red-700">{errors.customer_id}</div>
                    )}
                    {errors.po_attachment && (
                        <div className="rounded-md bg-red-50 p-3 text-sm text-red-700">{errors.po_attachment}</div>
                    )}

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Customer</label>
                        {lockedCustomerId ? (
                            <p className="mt-1 text-sm text-gray-900">
                                {customers.find((c) => c.id === lockedCustomerId)?.company_name}
                            </p>
                        ) : (
                            <select
                                value={data.customer_id}
                                onChange={(e) => setData('customer_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                            >
                                <option value="">Select a customer</option>
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

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Attachment (PDF, PNG, or JPG)</label>
                        <input
                            type="file"
                            accept=".pdf,.png,.jpg,.jpeg"
                            onChange={(e) => setData('po_attachment', e.target.files[0] ?? null)}
                            className="mt-1 block w-full text-sm"
                        />
                    </div>

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <label className="block text-sm font-medium text-gray-700">Product Lines</label>
                            <Button type="button" variant="tertiary" size="compact" onClick={addLine}>
                                + Add Line
                            </Button>
                        </div>

                        <div className="space-y-3">
                            {lines.map((line) => {
                                const suggestions = line.product_id ? [] : matchProducts(products, line.product_search);

                                return (
                                    <div key={line.key} className="flex items-start gap-2 rounded-md border border-gray-200 p-3">
                                        <div className="relative flex-1">
                                            <Input
                                                type="text"
                                                value={line.product_search}
                                                onChange={(value) => updateLine(line.key, { product_search: value, product_id: '' })}
                                                placeholder="Search product name, generic name, or SKU"
                                            />
                                            {line.product_id && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="compact"
                                                    className="mt-1"
                                                    onClick={() => clearProduct(line.key)}
                                                >
                                                    Clear selection
                                                </Button>
                                            )}
                                            {suggestions.length > 0 && (
                                                <ul className="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white text-sm shadow-lg">
                                                    {suggestions.map((product) => (
                                                        <li key={product.id}>
                                                            <button
                                                                type="button"
                                                                onClick={() => selectProduct(line.key, product)}
                                                                className="block w-full px-3 py-2 text-left hover:bg-gray-50"
                                                            >
                                                                <span className="font-medium">{product.product_name}</span>
                                                                {product.generic_name && (
                                                                    <span className="ml-1 text-xs text-gray-500">
                                                                        {product.generic_name}
                                                                    </span>
                                                                )}
                                                            </button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={line.quantity}
                                            onChange={(value) => updateLine(line.key, { quantity: value })}
                                            className="w-24"
                                        />
                                        <Button
                                            type="button"
                                            variant="tertiary"
                                            size="compact"
                                            className="text-red-600 hover:text-red-700"
                                            onClick={() => removeLine(line.key)}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" variant="primary" disabled={processing}>
                            Create Order
                        </Button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
