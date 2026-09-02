import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Accordion } from '@/components/interior/accordion';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

function Term({ children }) {
    return <strong className="font-medium text-stone-800 dark:text-stone-100">{children}</strong>;
}

const FAQ_ITEMS = [
    {
        id: 'create-order',
        title: 'How do I create a purchase order?',
        meta: 'Orders',
        content: (
            <div className="space-y-2.5">
                <ol className="list-decimal space-y-1.5 pl-4">
                    <li>Open <Term>Orders</Term>.</li>
                    <li>Select <Term>Create Order</Term>.</li>
                    <li>Choose the products and quantities you need.</li>
                    <li>Submit your PO.</li>
                </ol>
                <p>Your order will immediately appear in the Orders list with a Submitted status.</p>
            </div>
        ),
    },
    {
        id: 'order-status',
        title: 'What do the order statuses mean?',
        meta: 'Orders',
        content: (
            <ul className="list-disc space-y-1.5 pl-4">
                <li><Term>Submitted</Term> — waiting for review.</li>
                <li><Term>Reviewing</Term> — the Theomeds team is checking it.</li>
                <li><Term>Partial</Term> — part of the order has been delivered.</li>
                <li><Term>Completed</Term> — all items have been delivered.</li>
                <li><Term>Cancelled</Term> — the order will not be fulfilled.</li>
            </ul>
        ),
    },
    {
        id: 'confirm-delivery',
        title: 'How do I confirm that a delivery was received?',
        meta: 'Delivery',
        content: 'When an order is marked Completed, open the order and select "Order Received." This lets Theomeds know that the delivery reached your facility.',
    },
    {
        id: 'order-notifications',
        title: 'Where can I see order updates?',
        meta: 'Updates',
        content: (
            <ul className="list-disc space-y-1.5 pl-4">
                <li>Select the bell in the top navigation for recent order updates.</li>
                <li>Open the order itself to see its current status and delivery information.</li>
            </ul>
        ),
    },
    {
        id: 'message-support',
        title: 'How do I contact Theomeds about an order?',
        meta: 'Support',
        content: 'Select the message icon in the header to start or continue a conversation. Include the PO number and the details you need help with so the team can respond quickly.',
    },
    {
        id: 'account-access',
        title: 'Who can change account details or add users?',
        meta: 'Accounts',
        content: (
            <ul className="list-disc space-y-1.5 pl-4">
                <li>You can update your own name and phone number from Settings.</li>
                <li>Administrators manage company accounts, customer links, account status, and permissions from the Admin area.</li>
            </ul>
        ),
    },
];

export default function Faq() {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Frequently Asked Questions
                </h2>
            }
        >
            <Head title="Frequently Asked Questions" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <section className="overflow-hidden rounded-2xl border border-border bg-card px-6 py-8 text-center sm:px-8 sm:py-10">
                    <p className="text-base font-semibold tracking-wide text-primary">HELP CENTER</p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                        Answers for managing your orders
                    </h1>
                    <p className="mx-auto mt-3 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">
                        Find quick guidance on submitting purchase orders, tracking deliveries,
                        receiving updates, and contacting the Theomeds team.
                    </p>
                    <div className="mt-6 flex flex-wrap justify-center gap-3">
                        <Button asChild variant="tertiary">
                            <Link href={route('purchase-orders.index')}>View Orders</Link>
                        </Button>
                        <Button asChild variant="primary">
                            <Link href={route('dashboard')}>Go to Dashboard</Link>
                        </Button>
                    </div>
                </section>

                <section className="mt-8" aria-labelledby="faq-list-heading">
                    <div className="mb-4">
                        <h2 id="faq-list-heading" className="text-xl font-semibold text-foreground">
                            Common questions
                        </h2>
                        <p className="mt-1 text-base text-muted-foreground">
                            Select a question to see the answer.
                        </p>
                    </div>

                    <Accordion items={FAQ_ITEMS} defaultOpen={['create-order']} maxPanelHeight={400} />
                </section>

                <section className="mt-8 rounded-xl border border-border bg-card p-5 sm:flex sm:items-center sm:justify-between sm:gap-6">
                    <div>
                        <h2 className="text-lg font-semibold text-foreground">Still need help?</h2>
                        <p className="mt-1 text-base text-muted-foreground">
                            Use the message icon in the header to contact the Theomeds team.
                        </p>
                    </div>
                    <Link
                        href={route('dashboard')}
                        className="mt-4 inline-flex items-center gap-1 text-base font-medium text-primary hover:underline sm:mt-0"
                    >
                        Return to dashboard <ArrowRight className="size-5" aria-hidden="true" />
                    </Link>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
