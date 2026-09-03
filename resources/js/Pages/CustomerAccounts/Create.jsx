import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Modal } from '@/components/interior/modal';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

const FIELD_CLASS = 'mt-1 h-10 w-full rounded-md border border-gray-300 px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';

function Field({ label, name, type = 'text', data, setData, error, required = true }) {
    return <label className="block text-sm font-medium text-gray-700">
        {label}
        <input type={type} value={data[name]} onChange={(event) => setData(name, event.target.value)} className={FIELD_CLASS} required={required} autoComplete={type === 'password' ? 'new-password' : 'off'} />
        {error && <span className="mt-1 block text-sm text-red-600" role="alert">{error}</span>}
    </label>;
}

export default function Create({ customers = [], assignedCustomers = [] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ full_name: '', email: '', phone: '', password: '', password_confirmation: '', customer_id: '' });
    const close = () => {
        if (processing) return;
        reset();
        clearErrors();
        setOpen(false);
    };
    const submit = (event) => {
        event.preventDefault();
        post(route('customer-accounts.store'), { preserveScroll: true, onSuccess: close });
    };
    const columns = useMemo(() => [
        { key: 'company_name', header: 'Customer', sortable: true },
        { key: 'customer_code', header: 'Code', cell: (customer) => customer.customer_code ?? '-' },
        { key: 'channel', header: 'Channel', cell: (customer) => customer.channel ?? '-' },
        { key: 'user', header: 'Portal account', cell: (customer) => customer.user?.full_name ?? 'No account yet' },
        { key: 'email', header: 'Email', cell: (customer) => customer.user?.email ?? '-' },
    ], []);

    return <AuthenticatedLayout header={<div className="flex items-center justify-between"><h2 className="text-xl font-semibold text-gray-800">Customers</h2><Button type="button" onClick={() => setOpen(true)}><Plus aria-hidden="true" className="mr-2 h-4 w-4" />Add customer account</Button></div>}>
        <Head title="Customers" />
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div className="mb-6"><h1 className="type-page-heading text-foreground">My customers</h1><p className="mt-1 text-sm text-muted-foreground">Customer accounts you create are automatically assigned to you.</p></div>
            <Table data={assignedCustomers} columns={columns} getRowId={(customer) => String(customer.id)} height={480} emptyState="You have no assigned customers yet. Add a customer account to get started." emptyStateHeight={240} />
        </div>

        <Modal open={open} onClose={close} title="Add customer account" description="This new customer account will be assigned to you." maxWidth={560} closeOnBackdrop={!processing} closeOnEscape={!processing} footer={<><Button type="button" variant="tertiary" onClick={close} disabled={processing}>Cancel</Button><Button type="submit" form="customer-account-form" loading={processing}>Create account</Button></>}>
            <form id="customer-account-form" onSubmit={submit} className="space-y-4">
                <Field label="Full name" name="full_name" data={data} setData={setData} error={errors.full_name} />
                <Field label="Email" name="email" type="email" data={data} setData={setData} error={errors.email} />
                <Field label="Phone number" name="phone" type="tel" data={data} setData={setData} error={errors.phone} required={false} />
                <Field label="Password" name="password" type="password" data={data} setData={setData} error={errors.password} />
                <Field label="Confirm password" name="password_confirmation" type="password" data={data} setData={setData} error={errors.password_confirmation} />
                <label className="block text-sm font-medium text-gray-700">Customer
                    <select value={data.customer_id} onChange={(event) => setData('customer_id', event.target.value)} className={FIELD_CLASS} required>
                        <option value="">Choose a customer</option>
                        {customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.company_name}</option>)}
                    </select>
                    {errors.customer_id && <span className="mt-1 block text-sm text-red-600" role="alert">{errors.customer_id}</span>}
                </label>
            </form>
        </Modal>
    </AuthenticatedLayout>;
}
