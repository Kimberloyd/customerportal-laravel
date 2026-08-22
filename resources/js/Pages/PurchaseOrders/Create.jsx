import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogFooter,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/motion/input';
import { Table } from '@/components/motion/table';
import { CommandPalette } from '@/components/motion/command-palette';
import { AttachmentUpload } from '@/components/motion/attachment-upload';
import { Head, useForm } from '@inertiajs/react';
import { Search, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export default function Create({ customers, products, lockedCustomerId }) {
    const [lines, setLines] = useState([]);
    const [selectedLineIds, setSelectedLineIds] = useState([]);
    const [attachmentItems, setAttachmentItems] = useState([]);
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [confirmBulkDeleteOpen, setConfirmBulkDeleteOpen] = useState(false);
    const [clientError, setClientError] = useState(null);
    const { data, setData, post, transform, processing, errors } = useForm({
        customer_id: lockedCustomerId ?? '',
        po_number: '',
        remarks: '',
        po_attachment: null,
    });

    const errorMessages = [
        clientError,
        ...Object.entries(errors)
            .filter(([key]) => key.startsWith('items'))
            .map(([, message]) => message),
        errors.customer_id,
        errors.po_attachment,
    ].filter(Boolean);

    const [errorBannerDismissed, setErrorBannerDismissed] = useState(false);
    useEffect(() => {
        setErrorBannerDismissed(false);
    }, [errorMessages.join('|')]);

    const addProduct = (product) => {
        setLines((prev) => {
            const existing = prev.find((l) => l.product_id === product.id);
            if (existing) {
                return prev.map((l) =>
                    l.product_id === product.id ? { ...l, quantity: Number(l.quantity) + 1 } : l,
                );
            }
            return [
                ...prev,
                {
                    key: crypto.randomUUID(),
                    product_id: product.id,
                    product_name: product.product_name,
                    generic_name: product.generic_name,
                    dosage: product.dosage,
                    sku: product.sku,
                    quantity: 1,
                },
            ];
        });
    };

    const commandItems = useMemo(
        () =>
            products.map((product) => ({
                id: String(product.id),
                label: product.generic_name
                    ? `${product.product_name} — ${product.generic_name}`
                    : product.product_name,
                group: product.category ?? 'Products',
                hint: product.unit ?? undefined,
                badge: product.dosage ? (
                    <kbd className="flex h-5 min-w-[3rem] items-center justify-center whitespace-nowrap rounded border border-border bg-background px-1.5 text-[10px] uppercase leading-none text-muted-foreground">
                        {product.dosage}
                    </kbd>
                ) : undefined,
                keywords: [product.generic_name, product.sku, product.unit].filter(Boolean),
                onSelect: () => addProduct(product),
            })),
        [products],
    );

    const updateQuantity = (key, quantity) => {
        setLines((prev) => prev.map((line) => (line.key === key ? { ...line, quantity } : line)));
    };

    const removeLine = (key) => setLines((prev) => prev.filter((l) => l.key !== key));

    const confirmRemoveSelectedLines = () => {
        setLines((prev) => prev.filter((l) => !selectedLineIds.includes(l.key)));
        setSelectedLineIds([]);
        setConfirmBulkDeleteOpen(false);
    };

    const submit = (e) => {
        e.preventDefault();

        if (!lockedCustomerId && data.customer_id === '') {
            setClientError('Select a customer.');
            return;
        }

        if (data.po_number.trim() === '') {
            setClientError('Enter a PO number.');
            return;
        }

        if (lines.length === 0) {
            setClientError('Please add at least one product line.');
            return;
        }

        const invalidQuantityLine = lines.find(
            (line) => !Number.isInteger(Number(line.quantity)) || Number(line.quantity) < 1,
        );
        if (invalidQuantityLine) {
            setClientError(`Enter a quantity of at least 1 for "${invalidQuantityLine.product_name}".`);
            return;
        }

        setClientError(null);

        transform((formData) => ({
            ...formData,
            po_number: data.po_number.trim(),
            product_id: lines.map((line) => line.product_id),
            product_search: lines.map((line) => line.product_name),
            quantity: lines.map((line) => line.quantity),
        }));

        post(route('purchase-orders.store'), {
            forceFormData: true,
        });
    };

    const productLineColumns = useMemo(
        () => [
            { key: 'product_name', header: 'Product Name', sortable: true, cell: (line) => line.product_name },
            { key: 'generic_name', header: 'Generic Name', sortable: true, cell: (line) => line.generic_name ?? '' },
            {
                key: 'dosage',
                header: 'Variant',
                sortable: true,
                cell: (line) => <span className="uppercase">{line.dosage ?? ''}</span>,
            },
            {
                key: 'quantity',
                header: 'Quantity',
                cell: (line) => (
                    <Input
                        type="number"
                        min={1}
                        value={line.quantity}
                        onChange={(value) => updateQuantity(line.key, value)}
                        classNames={{
                            field: 'h-8 w-auto rounded-none',
                            input: 'min-w-[2.75rem] [field-sizing:content]',
                        }}
                    />
                ),
            },
            {
                key: 'actions',
                header: '',
                width: '56px',
                reorderable: false,
                cell: (line) => (
                    <div className="flex justify-start">
                        <button
                            type="button"
                            onClick={() => removeLine(line.key)}
                            aria-label="Remove"
                            className="grid h-7 w-7 place-items-center rounded-full text-red-600 transition-colors hover:bg-red-50 hover:text-red-700"
                        >
                            <Trash2 className="h-5 w-5" />
                        </button>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <AuthenticatedLayout>
            <Head title="Create Order" />

            {errorMessages.length > 0 && !errorBannerDismissed && (
                <div className="sticky top-16 z-30 bg-red-50">
                    <div className="mx-auto flex max-w-7xl items-start justify-between gap-4 px-4 py-3 text-sm text-red-700 sm:px-6 lg:px-8">
                        <div className="space-y-1">
                            {errorMessages.map((message, index) => (
                                <p key={index}>{message}</p>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={() => setErrorBannerDismissed(true)}
                            className="mt-0.5 shrink-0 text-red-700 hover:text-red-900"
                            aria-label="Dismiss"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            )}

            <header className="border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Create Order
                    </h2>
                </div>
            </header>

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-6 bg-white">
                    {!lockedCustomerId && (
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Customer</label>
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
                        </div>
                    )}

                    <div>
                        <div className="mb-4 flex items-center justify-center gap-3">
                            <button
                                type="button"
                                onClick={() => setPaletteOpen(true)}
                                className="flex h-9 w-80 items-center gap-2 rounded-full border border-border bg-transparent pl-3.5 pr-4 text-sm text-muted-foreground hover:border-foreground/40"
                            >
                                <Search className="h-4 w-4" />
                                <span className="text-left">Search Product</span>
                                <kbd className="pointer-events-none ml-auto select-none rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 text-xs font-medium text-gray-500">
                                    Ctrl+K
                                </kbd>
                            </button>
                        </div>

                        <CommandPalette
                            items={commandItems}
                            open={paletteOpen}
                            onOpenChange={setPaletteOpen}
                            placeholder="Search product name, generic name, or SKU"
                            emptyMessage="No products match your search."
                            note={
                                <div className="flex items-center justify-between gap-3">
                                    <span>Left: Product Name. Right: Generic Name.</span>
                                    <span>Badges: Variant, then Unit.</span>
                                </div>
                            }
                        />

                        <div className="mb-1 flex items-center justify-between text-sm text-muted-foreground">
                            <span>{lines.length} {lines.length === 1 ? 'row' : 'rows'}</span>
                            <div className="flex items-center gap-2">
                                <span>{selectedLineIds.length} selected</span>
                                {selectedLineIds.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => setConfirmBulkDeleteOpen(true)}
                                        aria-label="Remove selected"
                                        className="grid h-7 w-7 place-items-center rounded-full text-red-600 transition-colors hover:bg-red-50 hover:text-red-700"
                                    >
                                        <Trash2 className="h-5 w-5" />
                                    </button>
                                )}
                            </div>
                        </div>

                        <Table
                            data={lines}
                            columns={productLineColumns}
                            getRowId={(line) => line.key}
                            className="rounded-[9px] [&>div]:overflow-hidden [&_td:not(:nth-last-child(-n+2))]:border-r [&_td:not(:nth-last-child(-n+2))]:border-border/60 [&_th:not(:nth-last-child(-n+2))]:border-r [&_th:not(:nth-last-child(-n+2))]:border-border/60"
                            height={lines.length === 0 ? 176 : lines.length * 48 + 48}
                            resizable
                            reorderable
                            selectable
                            selectedRowIds={selectedLineIds}
                            onSelectionChange={setSelectedLineIds}
                            emptyState="Search for a product above to add it to this order."
                        />

                        <Dialog open={confirmBulkDeleteOpen} onOpenChange={setConfirmBulkDeleteOpen}>
                            <DialogContent>
                                <DialogHeader className="mb-6 gap-2">
                                    <DialogTitle>Remove selected product lines?</DialogTitle>
                                    <DialogDescription className="text-sm text-gray-600">
                                        This will remove {selectedLineIds.length} selected product{' '}
                                        {selectedLineIds.length === 1 ? 'line' : 'lines'} from this order.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <button
                                        type="button"
                                        onClick={() => setConfirmBulkDeleteOpen(false)}
                                        className="inline-flex h-9 items-center justify-center rounded-lg bg-gray-100 px-4 text-[13px] font-medium text-gray-700 transition-colors hover:bg-gray-200"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        onClick={confirmRemoveSelectedLines}
                                        className="inline-flex h-9 items-center justify-center rounded-lg bg-red-600 px-4 text-[13px] font-medium text-white transition-colors hover:bg-red-700"
                                    >
                                        Remove
                                    </button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">PO Number</label>
                        <Input
                            type="text"
                            value={data.po_number}
                            onChange={(value) => setData('po_number', value)}
                            error={errors.po_number}
                            classNames={{ root: 'mt-1', field: 'rounded-md' }}
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Attachment (PDF, PNG, or JPG) <span className="font-normal text-gray-400">(optional)</span>
                        </label>
                        <AttachmentUpload
                            value={attachmentItems}
                            onValueChange={(items) => {
                                setAttachmentItems(items);
                                setData('po_attachment', items[0]?.file ?? null);
                            }}
                            onFilesRejected={(files, reason) => {
                                if (reason === 'too-large') {
                                    setClientError('Attachment must be 8 MB or smaller.');
                                }
                            }}
                            accept=".pdf,.png,.jpg,.jpeg"
                            multiple={false}
                            maxFiles={1}
                            maxFileSize={8 * 1024 * 1024}
                            title="Drag and drop or browse"
                            description="PDF, PNG, or JPG — up to 8 MB"
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Remarks</label>
                        <textarea
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            rows={3}
                            className="mt-1 block w-full rounded-md border-border text-sm outline-none focus:border-foreground/40 focus:ring-2 focus:ring-ring/40"
                        />
                    </div>

                    <div className="flex justify-center">
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={processing}
                            className="h-12 rounded-full px-8 text-base"
                        >
                            Submit Order
                        </Button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
