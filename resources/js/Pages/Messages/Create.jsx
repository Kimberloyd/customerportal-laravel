import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ recipients, linkedCustomer, selectedCustomerId }) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
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
                    <div>
                        <label htmlFor="conversation-recipient" className="block text-sm font-medium text-gray-700">Recipient</label>
                        {linkedCustomer ? (
                            <p className="mt-1 text-sm text-gray-900">Order Portal Team</p>
                        ) : (
                            <select
                                id="conversation-recipient"
                                value={data.customer_id}
                                onChange={(e) => {
                                    setData('customer_id', e.target.value);
                                    clearErrors('customer_id');
                                }}
                                aria-invalid={Boolean(errors.customer_id) || undefined}
                                aria-describedby={errors.customer_id ? 'conversation-recipient-error' : undefined}
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
                        {errors.customer_id && (
                            <p id="conversation-recipient-error" role="alert" className="mt-1 text-xs text-red-600">
                                {errors.customer_id}
                            </p>
                        )}
                    </div>

                    <div>
                        <Input
                            label="Subject"
                            type="text"
                            required
                            value={data.subject}
                            onChange={(value) => {
                                setData('subject', value);
                                clearErrors('subject');
                            }}
                            error={errors.subject}
                        />
                    </div>

                    <div>
                        <label htmlFor="conversation-message" className="block text-sm font-medium text-gray-700">Message</label>
                        <textarea
                            id="conversation-message"
                            required
                            rows={5}
                            value={data.body}
                            onChange={(e) => {
                                setData('body', e.target.value);
                                clearErrors('body');
                            }}
                            aria-invalid={Boolean(errors.body) || undefined}
                            aria-describedby={errors.body ? 'conversation-message-error' : undefined}
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                        />
                        {errors.body && (
                            <p id="conversation-message-error" role="alert" className="mt-1 text-xs text-red-600">
                                {errors.body}
                            </p>
                        )}
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
