import ConfirmationDialog from '@/components/ConfirmationDialog';
import Stepper, { Step } from '@/components/Stepper';
import { SuggestionMenu, suggestFieldKeyDown, useSuggestField } from '@/components/SuggestField';
import { Modal } from '@/components/interior/modal';
import { AttachmentUpload } from '@/components/motion/attachment-upload';
import { BottomSheet } from '@/components/motion/bottom-sheet';
import { Input } from '@/components/motion/input';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { useContainerBreakpoint } from '@/lib/hooks/use-container-breakpoint';
import { useForm } from '@inertiajs/react';
import { Minus, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

// Matches the sm breakpoint used across this app's layouts (AuthenticatedLayout
// switches its own nav at the same width) -- below it the modal becomes a
// bottom sheet instead, which suits a one-handed mobile flow better than a
// centered dialog. This one genuinely tracks the viewport rather than a
// nested wrapper -- there's no pre-existing element to observe before the
// modal/sheet choice is made -- so it's scoped to `document.body`, which is
// always mounted; routing it through the same container-query hook still
// dedupes the mechanism previously copy-pasted from PurchaseOrders/Index.jsx.
function useIsCompactViewport() {
    const bodyRef = useRef(typeof document !== 'undefined' ? document.body : null);
    return useContainerBreakpoint(bodyRef, 639);
}

function isSameProduct(line, product) {
    if (line.product_id && String(line.product_id) === String(product.id)) return true;

    const lineSku = String(line.sku ?? '').trim().toLowerCase();
    const productSku = String(product.sku ?? '').trim().toLowerCase();
    if (lineSku && productSku) return lineSku === productSku;

    return ['product_name', 'generic_name', 'dosage', 'unit'].every(
        (field) => String(line[field] ?? '').trim().toLowerCase()
            === String(product[field] ?? '').trim().toLowerCase(),
    );
}

export default function CreateOrderModal({
    open,
    onOpenChange,
    customers = [],
    products = [],
    productsLoading = false,
    productsError = false,
    onRetryProducts,
    lockedCustomerId = null,
    initialOrder = null,
}) {
    const isEditing = initialOrder !== null;
    const canEditItems = !isEditing || Boolean(initialOrder?.can_edit_items);
    const editDetailsLocked = isEditing && !canEditItems;
    // A locked customer (portal accounts scoped to one customer) skips the
    // Customer step entirely instead of showing it pre-filled and disabled --
    // and editing an existing order does too, since the customer an order
    // was placed under isn't something fulfillment should be able to change.
    const skipCustomerStep = Boolean(lockedCustomerId) || isEditing;
    const stepLabels = skipCustomerStep
        ? ['Products', 'Order details', 'Review']
        : ['Customer', 'Products', 'Order details', 'Review'];
    const totalSteps = stepLabels.length;
    const productsStepIndex = stepLabels.indexOf('Products') + 1;
    const detailsStepIndex = stepLabels.indexOf('Order details') + 1;

    const isCompactViewport = useIsCompactViewport();
    const stepperRef = useRef(null);
    const [stepperKey, setStepperKey] = useState(0);
    const [currentStep, setCurrentStep] = useState(1);
    const [lines, setLines] = useState([]);
    const [selectedLineIds, setSelectedLineIds] = useState([]);
    const [attachmentItems, setAttachmentItems] = useState([]);
    const [confirmBulkDeleteOpen, setConfirmBulkDeleteOpen] = useState(false);
    const [clientErrors, setClientErrors] = useState({});
    const customerField = useSuggestField();
    const productField = useSuggestField();
    const {
        data,
        setData,
        post,
        transform,
        processing,
        errors,
        clearErrors,
        reset,
    } = useForm({
        customer_id: lockedCustomerId ?? initialOrder?.customer_id ?? '',
        po_number: initialOrder?.po_number ?? '',
        remarks: initialOrder?.remarks ?? '',
        po_attachment: null,
        remove_attachment: false,
    });

    useEffect(() => {
        if (!open) return;

        setData({
            customer_id: lockedCustomerId ?? initialOrder?.customer_id ?? '',
            po_number: initialOrder?.po_number ?? '',
            remarks: initialOrder?.remarks ?? '',
            po_attachment: null,
            remove_attachment: false,
        });
        setLines((initialOrder?.items ?? []).map((item) => ({
            key: String(item.id),
            item_id: item.id,
            product_id: null,
            product_name: item.product_name ?? item.display_name,
            generic_name: item.generic_name ?? '',
            dosage: item.dosage ?? '',
            sku: item.sku ?? '',
            unit: item.unit ?? '',
            quantity: item.quantity,
            delivered_quantity: item.delivered_quantity ?? 0,
        })));
        setSelectedLineIds([]);
        setAttachmentItems([]);
        setClientErrors({});
        clearErrors();
        setCurrentStep(1);
        setStepperKey((key) => key + 1);
        // Reinitialize only when this modal opens for a different order.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, initialOrder?.id]);

    const clearFieldError = (field) => {
        clearErrors(field);
        setClientErrors((current) => {
            if (!current[field]) return current;
            const next = { ...current };
            delete next[field];
            return next;
        });
    };

    const resetAndClose = () => {
        reset();
        clearErrors();
        setClientErrors({});
        setLines([]);
        setSelectedLineIds([]);
        setAttachmentItems([]);
        customerField.setQuery('');
        customerField.setOpen(false);
        productField.setQuery('');
        productField.setOpen(false);
        setConfirmBulkDeleteOpen(false);
        setCurrentStep(1);
        setStepperKey((key) => key + 1);
        onOpenChange(false);
    };

    const close = () => {
        if (!processing) resetAndClose();
    };

    const selectedCustomer = customers.find(
        (customer) => String(customer.id) === String(data.customer_id),
    );

    const customerMatches = useMemo(() => {
        const query = customerField.query.trim().toLowerCase();
        if (!query) return [];
        return customers
            .filter((customer) => String(customer.company_name ?? '').toLowerCase().includes(query))
            .slice(0, 8)
            .map((customer) => ({ id: String(customer.id), label: customer.company_name, customer }));
    }, [customers, customerField.query]);

    const selectCustomer = (item) => {
        setData('customer_id', item.customer.id);
        clearFieldError('customer_id');
        customerField.setQuery(item.label);
        customerField.setOpen(false);
    };

    // Keeps the field's text in sync with the confirmed selection: reasserts
    // the picked name after a pick, and reverts to it (or clears back to
    // empty) if the user closes the list without picking a new match.
    useEffect(() => {
        if (!customerField.open) customerField.setQuery(selectedCustomer?.company_name ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [customerField.open, selectedCustomer]);

    const addProduct = (product) => {
        clearFieldError('items');
        const itemErrorKeys = Object.keys(errors).filter((key) => key.startsWith('items.'));
        if (itemErrorKeys.length) clearErrors(...itemErrorKeys);

        setLines((current) => {
            const existing = current.find((line) => isSameProduct(line, product));
            if (existing) {
                return current.map((line) =>
                    line.key === existing.key
                        ? { ...line, quantity: Number(line.quantity) + 1 }
                        : line,
                );
            }

            return [
                ...current,
                {
                    key: crypto.randomUUID(),
                    product_id: product.id,
                    product_name: product.product_name,
                    generic_name: product.generic_name,
                    dosage: product.dosage,
                    sku: product.sku,
                    unit: product.unit,
                    quantity: 1,
                },
            ];
        });
    };

    const productItems = useMemo(
        () => products.map((product) => ({
            id: String(product.id),
            label: product.generic_name
                ? `${product.product_name} — ${product.generic_name}`
                : product.product_name,
            hint: product.unit ?? undefined,
            badge: product.dosage ? (
                <kbd className="flex h-5 min-w-[3rem] items-center justify-center whitespace-nowrap rounded border border-border bg-background px-1.5 text-[10px] uppercase leading-none text-muted-foreground">
                    {product.dosage}
                </kbd>
            ) : undefined,
            keywords: [product.generic_name, product.sku, product.unit].filter(Boolean),
            product,
        })),
        [products],
    );

    const productMatches = useMemo(() => {
        const query = productField.query.trim().toLowerCase();
        if (!query) return [];
        return productItems
            .filter((item) => [item.label, ...(item.keywords ?? [])]
                .some((value) => String(value ?? '').toLowerCase().includes(query)))
            .slice(0, 8);
    }, [productItems, productField.query]);

    const selectProduct = (item) => {
        addProduct(item.product);
        productField.setQuery('');
        productField.setOpen(false);
    };

    const productSuggestionsVisible = productField.visible && !productsLoading;

    const updateQuantity = (key, quantity) => {
        clearFieldError('items');
        setLines((current) => current.map((line) =>
            line.key === key ? { ...line, quantity } : line,
        ));
    };

    const productLineColumns = useMemo(
        () => [
            {
                key: 'product_name',
                header: 'Product Name',
                sortable: true,
                cell: (line) => {
                    const product = [line.product_name, line.generic_name, line.dosage ? line.dosage.toUpperCase() : null]
                        .filter(Boolean)
                        .join(' · ');

                    return (
                        <span className="truncate" title={product}>
                            {product}
                        </span>
                    );
                },
            },
            {
                key: 'quantity',
                header: 'Quantity',
                cell: (line) => {
                    const minQuantity = isEditing ? Math.max(1, line.delivered_quantity) : 1;
                    const quantity = Number(line.quantity) || 0;
                    const alreadyDelivered = isEditing && line.delivered_quantity > 0;
                    const locked = editDetailsLocked || alreadyDelivered;

                    if (!isCompactViewport) {
                        return (
                            <div className="flex items-center gap-2">
                                <Input
                                    type="number"
                                    min={minQuantity}
                                    disabled={locked}
                                    value={line.quantity}
                                    onChange={(value) => updateQuantity(line.key, value)}
                                    leftIcon={
                                        <button
                                            type="button"
                                            disabled={locked || quantity <= minQuantity}
                                            onClick={() => updateQuantity(line.key, String(Math.max(minQuantity, quantity - 1)))}
                                            aria-label={`Decrease quantity for ${line.product_name}`}
                                            className="pointer-events-auto grid h-6 w-6 place-items-center rounded text-muted-foreground hover:bg-gray-100 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                        >
                                            <Minus className="h-3.5 w-3.5" />
                                        </button>
                                    }
                                    rightIcon={
                                        <button
                                            type="button"
                                            disabled={locked}
                                            onClick={() => updateQuantity(line.key, String(quantity + 1))}
                                            aria-label={`Increase quantity for ${line.product_name}`}
                                            className="grid h-6 w-6 place-items-center rounded text-muted-foreground hover:bg-gray-100 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                        >
                                            <Plus className="h-3.5 w-3.5" />
                                        </button>
                                    }
                                    classNames={{
                                        root: 'w-fit',
                                        field: 'h-8 w-auto rounded-none',
                                        input: 'w-20 min-w-[5rem] !pl-6 !pr-6 text-center [appearance:textfield] [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                                        leftIcon: 'pointer-events-auto left-0.5',
                                        rightIcon: 'right-0.5 [&_button]:size-6',
                                    }}
                                />
                                {alreadyDelivered && (
                                    <span className="text-xs text-muted-foreground" title="Delivered products cannot be edited.">
                                        Delivered
                                    </span>
                                )}
                            </div>
                        );
                    }
                    return (
                        <div className="flex items-center gap-1.5">
                            <button
                                type="button"
                                disabled={locked || quantity <= minQuantity}
                                onClick={() => updateQuantity(line.key, String(Math.max(minQuantity, quantity - 1)))}
                                aria-label={`Decrease quantity for ${line.product_name}`}
                                className="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-border text-gray-600 transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                            >
                                <Minus className="h-3.5 w-3.5" />
                            </button>
                            <Input
                                type="number"
                                min={minQuantity}
                                disabled={locked}
                                value={line.quantity}
                                onChange={(value) => updateQuantity(line.key, value)}
                                classNames={{
                                    field: 'h-8 w-auto rounded-full border-0 text-center',
                                    input: 'w-auto min-w-[2rem] text-center [field-sizing:content]',
                                }}
                            />
                            <button
                                type="button"
                                disabled={locked}
                                onClick={() => updateQuantity(line.key, String(quantity + 1))}
                                aria-label={`Increase quantity for ${line.product_name}`}
                                className="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-border text-gray-600 transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                            >
                                <Plus className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    );
                },
            },
            ...(canEditItems
                ? [{
                      key: 'actions',
                      header: '',
                      width: '48px',
                      cell: (line) => (
                          <button
                              type="button"
                              disabled={isEditing && line.item_id && line.delivered_quantity > 0}
                              onClick={() => setLines((current) => current.filter((item) => item.key !== line.key))}
                              aria-label={isEditing && line.item_id && line.delivered_quantity > 0
                                  ? `${line.product_name} cannot be removed because it has delivered units`
                                  : `Remove ${line.product_name}`}
                              title={isEditing && line.item_id && line.delivered_quantity > 0
                                  ? 'Delivered products cannot be removed.'
                                  : undefined}
                              className="grid h-7 w-7 place-items-center rounded-full text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:bg-transparent"
                          >
                              <Trash2 className="h-4 w-4" />
                          </button>
                      ),
                  }]
                : []),
        ],
        [canEditItems, editDetailsLocked, isEditing, isCompactViewport],
    );

    const validateCustomer = () => {
        if (!skipCustomerStep && data.customer_id === '') {
            setClientErrors((current) => ({ ...current, customer_id: 'Select a customer.' }));
            return false;
        }
        return true;
    };

    const validateProducts = () => {
        if (lines.length === 0) {
            setClientErrors((current) => ({ ...current, items: 'Add at least one product.' }));
            return false;
        }

        const invalidLine = lines.find(
            (line) => !Number.isInteger(Number(line.quantity))
                || Number(line.quantity) < (isEditing ? Math.max(1, line.delivered_quantity) : 1),
        );
        if (invalidLine) {
            const minimum = isEditing ? Math.max(1, invalidLine.delivered_quantity) : 1;
            setClientErrors((current) => ({
                ...current,
                items: `Enter a quantity of at least ${minimum} for ${invalidLine.product_name}.`,
            }));
            return false;
        }
        return true;
    };

    const validateDetails = () => {
        if (data.po_number.trim() === '') {
            setClientErrors((current) => ({ ...current, po_number: 'Enter a PO number.' }));
            return false;
        }
        return true;
    };

    // Each step validates itself on the way forward; the Review step (and,
    // when skipped, the Customer step) has nothing to check here.
    const stepValidators = {
        ...(skipCustomerStep ? {} : { 1: validateCustomer }),
        [productsStepIndex]: validateProducts,
        [detailsStepIndex]: validateDetails,
    };

    const goForward = () => {
        const validator = stepValidators[currentStep];
        if (validator && !validator()) return;
        if (currentStep === totalSteps) stepperRef.current?.complete();
        else stepperRef.current?.next();
    };

    const submit = () => {
        if (processing || !validateCustomer() || !validateProducts() || !validateDetails()) return;

        transform((values) => {
            if (isEditing) {
                return {
                    _method: 'put',
                    customer_id: values.customer_id,
                    remarks: values.remarks,
                    po_attachment: values.po_attachment,
                    remove_attachment: values.remove_attachment,
                    items: lines.map((line) => ({
                        id: line.item_id,
                        product_id: line.product_id,
                        quantity: line.quantity,
                    })),
                };
            }

            return {
                ...values,
                po_number: values.po_number.trim(),
                product_id: lines.map((line) => line.product_id),
                product_search: lines.map((line) => line.product_name),
                quantity: lines.map((line) => line.quantity),
            };
        });

        const endpoint = isEditing
            ? route('purchase-orders.update', initialOrder.public_id)
            : route('purchase-orders.store');

        post(endpoint, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                if (isEditing && page.props.flash?.error) return;
                resetAndClose();
            },
            onError: (serverErrors) => {
                let targetStep = detailsStepIndex;
                if (!skipCustomerStep && serverErrors.customer_id) targetStep = 1;
                else if (Object.keys(serverErrors).some((key) => key.startsWith('items'))) targetStep = productsStepIndex;
                setCurrentStep(targetStep);
                setStepperKey((key) => key + 1);
            },
        });
    };

    const itemErrors = [
        clientErrors.items,
        errors.items,
        ...Object.entries(errors)
            .filter(([key]) => key.startsWith('items.'))
            .map(([, message]) => message),
    ].filter(Boolean);
    const totalQuantity = lines.reduce((sum, line) => sum + Number(line.quantity || 0), 0);

    const modalTitle = isEditing ? `Edit ${initialOrder.po_number}` : 'Create order';
    const modalDescription = isEditing
        ? 'Review the existing order details and save your changes.'
        : lockedCustomerId
        ? 'Add products and enter the order details.'
        : 'Choose a customer, add products, and enter the order details.';

    const footerButtons = (
        <>
            <Button
                type="button"
                variant="tertiary"
                className="h-10 rounded-md px-5 text-sm"
                onClick={currentStep > 1 ? () => stepperRef.current?.back() : close}
                disabled={processing}
            >
                {currentStep > 1 ? 'Back' : 'Cancel'}
            </Button>
            <Button
                type="button"
                variant="primary"
                className="h-10 rounded-md px-5 text-sm"
                onClick={goForward}
                loading={processing}
                disabled={currentStep === productsStepIndex && productsLoading}
            >
                {currentStep === totalSteps
                    ? (isEditing ? 'Save changes' : 'Submit order')
                    : 'Continue'}
            </Button>
        </>
    );

    const stepper = (
            <Stepper
                ref={stepperRef}
                key={stepperKey}
                initialStep={currentStep}
                onStepChange={setCurrentStep}
                onFinalStepCompleted={submit}
                stepLabels={stepLabels}
                hideDefaultFooter
                stepContainerClassName="sticky top-0 z-10 bg-white pt-4 dark:bg-[#1D1D1A]"
            >
                {!skipCustomerStep && (
                <Step>
                    <div className="space-y-2 pt-2">
                        <label className="block text-sm font-medium text-gray-700">Customer</label>
                        <div ref={customerField.fieldRef} className="relative w-full">
                            <Input
                                value={customerField.query}
                                disabled={editDetailsLocked}
                                onChange={(value) => {
                                    customerField.setQuery(value);
                                    customerField.setActiveIndex(0);
                                    customerField.setOpen(true);
                                    if (data.customer_id !== '') setData('customer_id', '');
                                    clearFieldError('customer_id');
                                }}
                                onFocus={() => customerField.setOpen(true)}
                                onKeyDown={suggestFieldKeyDown(customerField, customerMatches, selectCustomer)}
                                type="text"
                                placeholder="Search customers"
                                leftIcon={<Search className="h-4 w-4" />}
                                error={Boolean(errors.customer_id || clientErrors.customer_id)}
                                classNames={{
                                    field: 'h-9 rounded-md bg-transparent shadow-none',
                                    input: 'text-sm',
                                }}
                            />
                            {customerField.visible && customerField.position && (
                                <SuggestionMenu
                                    menuRef={customerField.menuRef}
                                    position={customerField.position}
                                    items={customerMatches}
                                    activeIndex={customerField.activeIndex}
                                    onHover={customerField.setActiveIndex}
                                    onSelect={selectCustomer}
                                    emptyMessage="No customers found. Try a different search."
                                />
                            )}
                        </div>
                        {(errors.customer_id || clientErrors.customer_id) && (
                            <p id="create-order-customer-error" role="alert" className="text-sm text-destructive">
                                {errors.customer_id ?? clientErrors.customer_id}
                            </p>
                        )}
                    </div>
                </Step>
                )}

                <Step>
                    <div className="space-y-3 pt-2">
                        {canEditItems && (productsError ? (
                            <div className="flex justify-center">
                                <button
                                    type="button"
                                    onClick={onRetryProducts}
                                    className="flex h-9 w-80 items-center justify-center gap-2 rounded-full border border-red-200 bg-transparent px-4 text-sm text-red-600 hover:border-red-300"
                                >
                                    <span>Couldn't load products. Retry</span>
                                </button>
                            </div>
                        ) : (
                            <div ref={productField.fieldRef} className="relative w-full">
                                <Input
                                    value={productField.query}
                                    disabled={productsLoading}
                                    onChange={(value) => {
                                        productField.setQuery(value);
                                        productField.setActiveIndex(0);
                                        productField.setOpen(true);
                                    }}
                                    onFocus={() => productField.setOpen(true)}
                                    onKeyDown={suggestFieldKeyDown(productField, productMatches, selectProduct)}
                                    type="text"
                                    placeholder="Search products"
                                    leftIcon={<Search className="h-4 w-4" />}
                                    classNames={{
                                        field: 'h-9 rounded-md bg-transparent shadow-none',
                                        input: 'text-sm',
                                    }}
                                />
                                {productSuggestionsVisible && productField.position && (
                                    <SuggestionMenu
                                        menuRef={productField.menuRef}
                                        position={productField.position}
                                        items={productMatches}
                                        activeIndex={productField.activeIndex}
                                        onHover={productField.setActiveIndex}
                                        onSelect={selectProduct}
                                        emptyMessage="No products found. Try a different search."
                                    />
                                )}
                            </div>
                        ))}
                        {isEditing && canEditItems && (
                            <p className="text-xs text-muted-foreground">
                                Search to add products. Products with delivered units cannot be removed or reduced below the delivered quantity.
                            </p>
                        )}
                        {isCompactViewport && !isEditing && lines.length === 0 && !productsError && (
                            <p className="text-xs text-muted-foreground">
                                {productsLoading ? 'Loading products…' : 'Search for a product to add it to this order.'}
                            </p>
                        )}
                        {(!isCompactViewport || lines.length > 0) && (
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <span>{lines.length} {lines.length === 1 ? 'product' : 'products'}</span>
                                {canEditItems && <div className="flex items-center gap-2">
                                    <span>{selectedLineIds.length} selected</span>
                                    {selectedLineIds.length > 0 && (
                                        <button
                                            type="button"
                                            onClick={() => setConfirmBulkDeleteOpen(true)}
                                            aria-label="Remove selected products"
                                            className="grid h-7 w-7 place-items-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>}
                            </div>
                        )}
                        {(!isCompactViewport || lines.length > 0) && (
                            <Table
                                data={lines}
                                columns={productLineColumns}
                                getRowId={(line) => line.key}
                                height={lines.length === 0 ? 160 : Math.min(lines.length * 48 + 52, 304)}
                                className={isCompactViewport ? 'border-0 rounded-none [&_th]:!bg-transparent' : undefined}
                                resizable
                                selectable={canEditItems}
                                selectedRowIds={selectedLineIds}
                                onSelectionChange={setSelectedLineIds}
                                emptyState={
                                    isEditing
                                        ? 'No products have been added to this order.'
                                        : productsError
                                        ? "Couldn't load products."
                                        : productsLoading
                                            ? 'Loading products…'
                                            : 'Search for a product to add it to this order.'
                                }
                            />
                        )}
                        {itemErrors.length > 0 && (
                            <div className="space-y-1 text-sm text-destructive" role="alert">
                                {itemErrors.map((message, index) => <p key={`${message}-${index}`}>{message}</p>)}
                            </div>
                        )}
                    </div>
                </Step>

                <Step>
                    <div className="space-y-5 pt-2">
                        <div>
                            <label htmlFor="create-order-po-number" className="mb-1 block text-sm font-medium text-gray-700">
                                PO Number
                            </label>
                            <Input
                                id="create-order-po-number"
                                type="text"
                                disabled={isEditing}
                                value={data.po_number}
                                onChange={(value) => {
                                    setData('po_number', value);
                                    clearFieldError('po_number');
                                }}
                                error={errors.po_number ?? clientErrors.po_number}
                                classNames={{ field: 'rounded-md' }}
                            />
                        </div>
                        {canEditItems && <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Attachment <span className="font-normal text-gray-400">(optional)</span>
                            </label>
                            {isEditing && initialOrder.has_attachment && (
                                <label className="mb-3 flex items-center gap-2 text-sm text-gray-600">
                                    <input
                                        type="checkbox"
                                        checked={data.remove_attachment}
                                        onChange={(event) => setData('remove_attachment', event.target.checked)}
                                        className="rounded border-gray-300 text-primary focus:ring-0 focus-visible:ring-2 focus-visible:ring-primary"
                                    />
                                    Remove existing attachment
                                </label>
                            )}
                            <AttachmentUpload
                                value={attachmentItems}
                                onValueChange={(items) => {
                                    setAttachmentItems(items);
                                    setData('po_attachment', items[0]?.file ?? null);
                                    if (items[0]?.file) setData('remove_attachment', false);
                                    clearFieldError('po_attachment');
                                }}
                                onFilesRejected={(files, reason) => {
                                    if (reason === 'too-large') {
                                        setClientErrors((current) => ({
                                            ...current,
                                            po_attachment: 'Choose a PDF, PNG, or JPG smaller than 8 MB.',
                                        }));
                                    }
                                }}
                                accept=".pdf,.png,.jpg,.jpeg"
                                multiple={false}
                                maxFiles={1}
                                maxFileSize={8 * 1024 * 1024}
                                title="Drag and drop or browse"
                                description="PDF, PNG, or JPG — up to 8 MB"
                            />
                            {(errors.po_attachment || clientErrors.po_attachment) && (
                                <p role="alert" className="mt-1 text-sm text-destructive">
                                    {errors.po_attachment ?? clientErrors.po_attachment}
                                </p>
                            )}
                        </div>}
                        <div>
                            <label htmlFor="create-order-remarks" className="mb-1 block text-sm font-medium text-gray-700">
                                Remarks <span className="font-normal text-gray-400">(optional)</span>
                            </label>
                            <textarea
                                id="create-order-remarks"
                                value={data.remarks}
                                onChange={(event) => setData('remarks', event.target.value)}
                                rows={3}
                                className="block w-full rounded-md border-border text-sm outline-none focus-visible:border-foreground/40 focus-visible:ring-2 focus-visible:ring-ring/40"
                            />
                        </div>
                    </div>
                </Step>

                <Step>
                    <div className="space-y-5 pt-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Products</label>
                            <div className="divide-y divide-border rounded-md border border-border text-sm">
                                <div className="flex items-center justify-between gap-4 px-3 py-2">
                                    <span className="text-gray-500">Customer</span>
                                    <span className="truncate font-medium text-gray-900">
                                        {selectedCustomer?.company_name ?? '—'}
                                    </span>
                                </div>
                                <div className="max-h-40 overflow-y-auto">
                                    {lines.map((line) => (
                                        <div key={line.key} className="flex items-center justify-between gap-4 px-3 py-2">
                                            <span className="min-w-0 truncate text-gray-700">
                                                {line.product_name}
                                                {line.dosage ? ` (${line.dosage})` : ''}
                                            </span>
                                            <span className="shrink-0 text-gray-500">Qty {line.quantity}</span>
                                        </div>
                                    ))}
                                </div>
                                <div className="flex items-center justify-between gap-4 px-3 py-2">
                                    <span className="text-gray-500">Total</span>
                                    <span className="font-medium text-gray-900">
                                        {lines.length} {lines.length === 1 ? 'product' : 'products'} · {totalQuantity} {totalQuantity === 1 ? 'unit' : 'units'}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Order details</label>
                            <div className="divide-y divide-border rounded-md border border-border text-sm">
                                <div className="flex items-center justify-between gap-4 px-3 py-2">
                                    <span className="text-gray-500">PO Number</span>
                                    <span className="truncate font-medium text-gray-900">{data.po_number || '—'}</span>
                                </div>
                                <div className="flex items-center justify-between gap-4 px-3 py-2">
                                    <span className="text-gray-500">Attachment</span>
                                    <span className="truncate font-medium text-gray-900">
                                        {attachmentItems[0]?.name
                                            ?? (isEditing && initialOrder.has_attachment && !data.remove_attachment
                                                ? 'Current attachment'
                                                : '—')}
                                    </span>
                                </div>
                                <div className="flex items-start justify-between gap-4 px-3 py-2">
                                    <span className="shrink-0 text-gray-500">Remarks</span>
                                    <span className="truncate text-right font-medium text-gray-900">
                                        {data.remarks || '—'}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {isEditing
                                ? 'Review the details above, then save your changes.'
                                : 'Review the details above, then submit to place this order.'}
                        </p>
                    </div>
                </Step>
            </Stepper>
    );

    return (
        <>
            {isCompactViewport ? (
                <BottomSheet
                    open={open}
                    onOpenChange={(next) => {
                        if (!next && !processing) close();
                    }}
                    title={modalTitle}
                    description={modalDescription}
                    snapPoints={[0.92]}
                    defaultSnap={0}
                    footer={footerButtons}
                >
                    {stepper}
                </BottomSheet>
            ) : (
                <Modal
                    open={open}
                    onClose={close}
                    title={modalTitle}
                    description={modalDescription}
                    maxWidth={900}
                    maxHeight="92vh"
                    closeOnBackdrop={!processing}
                    closeOnEscape={!processing}
                    footer={footerButtons}
                >
                    {stepper}
                </Modal>
            )}

            <ConfirmationDialog
                open={confirmBulkDeleteOpen}
                onOpenChange={setConfirmBulkDeleteOpen}
                title="Remove selected products?"
                description={`Remove ${selectedLineIds.length} selected ${selectedLineIds.length === 1 ? 'product' : 'products'} from this order?`}
                confirmLabel="Remove"
                cancelLabel="Cancel"
                onConfirm={() => {
                    setLines((current) => current.filter((line) => !selectedLineIds.includes(line.key)));
                    setSelectedLineIds([]);
                    setConfirmBulkDeleteOpen(false);
                }}
            />
        </>
    );
}
