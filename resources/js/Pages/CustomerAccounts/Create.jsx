import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Stepper, { Step } from '@/components/Stepper';
import { SuggestionMenu, suggestFieldKeyDown, useSuggestField } from '@/components/SuggestField';
import { Modal } from '@/components/interior/modal';
import { Input } from '@/components/motion/input';
import { Table } from '@/components/motion/table';
import { AccountFields, SecurityFields } from '@/components/UserForm';
import { Button } from '@/components/ui/button';
import { useFieldValidation } from '@/hooks/useFieldValidation';
import { Head, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const customerRules = {
    customer_id: (value) => (value ? null : 'Choose a customer.'),
};

// Which fields live on which step -- used to send the user back to the
// right step if the server rejects a field that isn't on the final one.
const STEP_FIELDS = [
    ['full_name', 'email', 'phone'],
    ['password', 'password_confirmation'],
    ['customer_id'],
];
const STEP_LABELS = ['Account', 'Security', 'Customer'];

export default function Create({ customers = [], assignedCustomers = [] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ full_name: '', email: '', phone: '', password: '', password_confirmation: '', customer_id: '' });
    const validation = useFieldValidation(customerRules);
    const stepperRef = useRef(null);
    // Bumped to force the Stepper to remount (and re-read initialStep) after
    // a failed submit, since it only manages its own step position.
    const [stepperKey, setStepperKey] = useState(0);
    // Mirrors the Stepper's own currentStep so the buttons living in the
    // Modal's real footer (outside the Stepper) know what to show/do.
    const [currentStep, setCurrentStep] = useState(1);
    const totalSteps = STEP_FIELDS.length;

    const updateField = (field, value) => {
        setData(field, value);
        clearErrors(field);
        validation.onChange(field, value, { ...data, [field]: value });
    };
    const close = () => {
        if (processing) return;
        reset();
        clearErrors();
        validation.reset();
        setCurrentStep(1);
        setStepperKey((key) => key + 1);
        setOpen(false);
    };
    const submit = () => {
        if (processing) return;
        post(route('customer-accounts.store'), {
            preserveScroll: true,
            onSuccess: close,
            onError: (serverErrors) => {
                let step = 1;
                for (let i = 0; i < STEP_FIELDS.length; i++) {
                    if (STEP_FIELDS[i].some((field) => serverErrors[field])) {
                        step = i + 1;
                        break;
                    }
                }
                setCurrentStep(step);
                setStepperKey((key) => key + 1);
            },
        });
    };
    const customerField = useSuggestField();
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
        updateField('customer_id', item.customer.id);
        validation.onBlur('customer_id', item.customer.id, { ...data, customer_id: item.customer.id });
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
    const columns = useMemo(() => [
        { key: 'company_name', header: 'Customer', sortable: true },
        { key: 'customer_code', header: 'Code', cell: (customer) => customer.customer_code ?? '-' },
        { key: 'channel', header: 'Channel', cell: (customer) => customer.channel ?? '-' },
        { key: 'user', header: 'Portal account', cell: (customer) => customer.user?.full_name ?? 'No account yet' },
        { key: 'email', header: 'Email', cell: (customer) => customer.user?.email ?? '-' },
    ], []);

    return <AuthenticatedLayout header={<div className="flex items-center justify-between"><h2 className="text-xl font-semibold text-gray-800">Customers</h2><Button type="button" onClick={() => setOpen(true)}>Add customer account</Button></div>}>
        <Head title="Customers" />
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div className="mb-6"><h1 className="type-page-heading text-foreground">My customers</h1><p className="mt-1 text-sm text-muted-foreground">Customer accounts you create are automatically assigned to you.</p></div>
            <Table data={assignedCustomers} columns={columns} getRowId={(customer) => String(customer.id)} height={480} emptyState="You have no assigned customers yet. Add a customer account to get started." emptyStateHeight={240} />
        </div>

        <Modal
            open={open}
            onClose={close}
            title="Add customer account"
            description="This new customer account will be assigned to you."
            maxWidth={560}
            closeOnBackdrop={!processing}
            closeOnEscape={!processing}
            footer={
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
                        onClick={() => (currentStep === totalSteps ? stepperRef.current?.complete() : stepperRef.current?.next())}
                        loading={processing}
                    >
                        {currentStep === totalSteps ? 'Create account' : 'Continue'}
                    </Button>
                </>
            }
        >
            <Stepper
                ref={stepperRef}
                key={stepperKey}
                initialStep={currentStep}
                onStepChange={setCurrentStep}
                onFinalStepCompleted={submit}
                stepLabels={STEP_LABELS}
                hideDefaultFooter
            >
                <Step>
                    <AccountFields data={data} updateField={updateField} errors={errors} />
                </Step>
                <Step>
                    <SecurityFields data={data} updateField={updateField} errors={errors} optional={false} />
                </Step>
                <Step>
                    <div className="space-y-2">
                        <label className="block text-sm font-medium text-gray-700">Customer</label>
                        <div ref={customerField.fieldRef} className="relative w-full">
                            <Input
                                value={customerField.query}
                                onChange={(value) => {
                                    customerField.setQuery(value);
                                    customerField.setActiveIndex(0);
                                    customerField.setOpen(true);
                                    if (data.customer_id !== '') updateField('customer_id', '');
                                }}
                                onFocus={() => customerField.setOpen(true)}
                                onKeyDown={suggestFieldKeyDown(customerField, customerMatches, selectCustomer)}
                                type="text"
                                placeholder="Search customers"
                                leftIcon={<Search className="h-4 w-4" />}
                                error={Boolean(errors.customer_id || validation.clientErrors.customer_id)}
                                classNames={{ field: 'h-10 rounded-md', input: 'text-sm' }}
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
                        {(errors.customer_id || validation.clientErrors.customer_id) && (
                            <p role="alert" className="text-sm text-red-600">{errors.customer_id || validation.clientErrors.customer_id}</p>
                        )}
                    </div>
                </Step>
            </Stepper>
        </Modal>
    </AuthenticatedLayout>;
}
