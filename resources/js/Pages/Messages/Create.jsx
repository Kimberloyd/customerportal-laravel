import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ recipients, linkedCustomer, selectedCustomerId }) {
    const { data, setData, post, processing, errors } = useForm({
        customer_id: linkedCustomer ? linkedCustomer.id : (selectedCustomerId ?? ''),
        subject: '',
        body: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('messages.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    New Conversation
                </h2>
            }
        >
            <Head title="New Conversation" />

            <div className="mx-auto max-w-2xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    {Object.entries(errors).map(([key, message]) => (
                        <div key={key} className="rounded-md bg-red-50 p-3 text-sm text-red-700">{message}</div>
                    ))}

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Recipient</label>
                        {linkedCustomer ? (
                            <p className="mt-1 text-sm text-gray-900">Order Portal Team</p>
                        ) : (
                            <select
                                value={data.customer_id}
                                onChange={(e) => setData('customer_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                            >
                                <option value="">Select a customer</option>
                                {recipients.map((recipient) => (
                                    <option key={recipient.customer.id} value={recipient.customer.id}>
                                        {recipient.customer.company_name} ({recipient.user_full_name})
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Subject</label>
                        <input
                            type="text"
                            required
                            value={data.subject}
                            onChange={(e) => setData('subject', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Message</label>
                        <textarea
                            required
                            rows={5}
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                        />
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" variant="primary" disabled={processing}>
                            Start Conversation
                        </Button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
