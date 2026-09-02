import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ customers = [] }) {
    const { data, setData, post, processing, errors } = useForm({ full_name: '', email: '', phone: '', password: '', password_confirmation: '', customer_id: '' });
    const submit = (event) => { event.preventDefault(); post(route('customer-accounts.store')); };
    return <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Create customer account</h2>}>
        <Head title="Create Customer Account" />
        <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8"><form onSubmit={submit} className="space-y-5 rounded-xl border border-gray-200 p-6">
            <p className="text-sm text-gray-600">The customer account will be assigned to you.</p>
            {[['full_name', 'Full name', 'text'], ['email', 'Email', 'email'], ['phone', 'Phone number', 'tel'], ['password', 'Password', 'password'], ['password_confirmation', 'Confirm password', 'password']].map(([field, label, type]) => <label key={field} className="block text-sm font-medium text-gray-700">{label}<input type={type} value={data[field]} onChange={(event) => setData(field, event.target.value)} className="mt-1 h-10 w-full rounded-md border border-gray-300 px-3" required={field !== 'phone'} />{errors[field] && <span className="mt-1 block text-sm text-red-600">{errors[field]}</span>}</label>)}
            <label className="block text-sm font-medium text-gray-700">Customer<select value={data.customer_id} onChange={(event) => setData('customer_id', event.target.value)} className="mt-1 h-10 w-full rounded-md border border-gray-300 px-3" required><option value="">Choose a customer</option>{customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.company_name}</option>)}</select>{errors.customer_id && <span className="mt-1 block text-sm text-red-600">{errors.customer_id}</span>}</label>
            <Button type="submit" loading={processing}>Create customer account</Button>
        </form></div>
    </AuthenticatedLayout>;
}
