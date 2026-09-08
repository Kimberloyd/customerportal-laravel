import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const LAST_UPDATED = 'September 4, 2026';

function Section({ id, title, children }) {
    return (
        <section id={id} className="scroll-mt-8 border-t border-border pt-8">
            <h2 className="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                {title}
            </h2>
            <div className="mt-3 space-y-4 text-justify text-base leading-7 text-muted-foreground">
                {children}
            </div>
        </section>
    );
}

function LegalContent() {
    return (
        <>
            <Head title="Terms & Privacy" />

            <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
                <header className="border-b border-border pb-8">
                    <p className="text-sm font-semibold tracking-wide text-primary">THEOMEDS MARKETING INC.</p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                        Terms of Use &amp; Privacy Notice
                    </h1>
                    <p className="mt-3 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">
                        How this customer portal is intended to be used and how it currently handles portal information.
                    </p>
                    <p className="mt-4 text-sm text-muted-foreground">Last updated: {LAST_UPDATED}</p>
                </header>

                <nav aria-label="On this page" className="my-8 rounded-xl border border-border bg-card p-4">
                    <p className="text-sm font-medium text-foreground">On this page</p>
                    <div className="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm font-medium text-primary">
                        <a href="#terms" className="hover:underline">Terms of use</a>
                        <a href="#orders-returns" className="hover:underline">Orders &amp; returns</a>
                        <a href="#privacy" className="hover:underline">Privacy</a>
                        <a href="#retention" className="hover:underline">Retention &amp; deletion</a>
                    </div>
                </nav>

                <div className="space-y-8">
                    <Section id="terms" title="Terms of use">
                        <p>
                            This portal helps authorized Theomeds customers and staff submit, review, fulfill, and track
                            purchase orders. It is an operational tool and does not replace any signed supply agreement,
                            quotation, invoice, or other agreement between you and Theomeds Marketing Inc.
                        </p>
                        <p>
                            Use only an account assigned to you. Keep your sign-in details private, use accurate order and
                            contact information, and do not attempt to access another organization&apos;s orders, accounts, or
                            messages. Theomeds may suspend access that is unauthorized, unsafe, or inconsistent with these terms.
                        </p>
                    </Section>

                    <Section id="orders-returns" title="Orders, delivery, and returns">
                        <p>
                            A submitted purchase order is recorded for review and fulfillment. Order status, delivery quantities,
                            and updates shown in the portal reflect the current operational record and may change as the order is
                            reviewed or fulfilled.
                        </p>
                        <p>
                            After a completed order is confirmed as received, the linked customer may request a return within
                            seven days. The request must identify delivered products and quantities and include a reason. A
                            A Theomeds admin, office user, or agent reviews the request before collection or delivery is arranged.
                        </p>
                        <p>
                            Recording a return in the portal does not automatically issue a refund, credit, replacement, or
                            inventory adjustment. Those outcomes, if applicable, are handled separately by Theomeds.
                        </p>
                    </Section>

                    <Section id="privacy" title="Privacy notice">
                        <p>
                            To operate the portal, we process account details such as name, email address, phone number, role,
                            and optional profile image. We also process customer and order information, including company and
                            delivery contact details, purchase-order items, delivery updates, return requests, messages, and
                            account activity needed to keep the portal secure and usable.
                        </p>
                        <p>
                            This information is used to provide portal access, manage orders and returns, communicate updates,
                            support customers, maintain security, and keep operational audit records. Authorized Theomeds staff
                            can access information relevant to their role. Customers can access only their organization&apos;s records.
                        </p>
                        <p>
                            When enabled by Theomeds, order updates may be sent through Semaphore SMS. The portal can also
                            connect to Theomeds inventory and messaging services needed for its features. We do not present this
                            portal as a payment processor, and it does not automatically process refunds.
                        </p>
                    </Section>

                    <Section id="retention" title="Retention and account deletion">
                        <p>
                            An administrator can deactivate an account immediately. The account is retained for the configured
                            recovery period, currently 30 days by default, before final deletion. During that period, an
                            administrator may restore the account.
                        </p>
                        <p>
                            At final deletion, authentication history is removed and personal identifiers are detached from
                            retained operational records where possible. Orders, audit history, and return records may remain for
                            business and record-keeping purposes, while the deleted account&apos;s identifying link and return
                            explanation are removed from return history.
                        </p>
                    </Section>

                    <Section id="contact" title="Questions about these terms or your information">
                        <p>
                            If you are signed in, use the message icon in the portal to contact Theomeds. If you cannot access
                            your account, contact your usual Theomeds representative. Theomeds should have this page reviewed and
                            approved by its legal or privacy adviser before relying on it as a final legal notice.
                        </p>
                    </Section>
                </div>
            </div>
        </>
    );
}

export default function TermsAndPrivacy() {
    const user = usePage().props.auth?.user;

    if (user) {
        return <AuthenticatedLayout><LegalContent /></AuthenticatedLayout>;
    }

    return (
        <main className="min-h-screen bg-background">
            <header className="border-b border-border bg-card">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <Link href="/" aria-label="Theomeds Marketing home">
                        <img
                            src="/images/TM Horizontal Lockup_Transparent BG.png"
                            alt="Theomeds Marketing"
                            className="h-12 w-auto"
                        />
                    </Link>
                    <Link href={route('login')} className="text-sm font-medium text-primary hover:underline">
                        Sign in
                    </Link>
                </div>
            </header>
            <LegalContent />
        </main>
    );
}
