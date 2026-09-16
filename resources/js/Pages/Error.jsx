import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Head, Link, usePage } from '@inertiajs/react';

const COPY = {
    403: {
        title: "You don't have access to this page",
        body: "Your account doesn't have permission to view this. If you think this is a mistake, contact your admin.",
    },
    404: {
        title: "This page doesn't exist",
        body: 'The link may be outdated, or the page may have been moved.',
    },
    419: {
        title: 'Your session expired',
        body: 'For your security, sessions expire after a period of inactivity. Please try again.',
    },
    429: {
        title: "You've made too many requests",
        body: 'Please wait a moment before trying again.',
    },
    500: {
        title: 'Something went wrong on our end',
        body: "We've been notified and are looking into it. Please try again shortly.",
    },
    503: {
        title: "We're down for maintenance",
        body: "We'll be back shortly. Thanks for your patience.",
    },
};

export default function Error({ status }) {
    const { auth } = usePage().props;
    const { title, body } = COPY[status] ?? {
        title: 'Something went wrong',
        body: 'Please try again, or head back to the dashboard.',
    };

    const content = (
        <>
            <Head title={title} />
            <div className="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center px-6 py-16 text-center">
                <p className="type-label text-muted-foreground">Error {status}</p>
                <h1 className="type-page-heading mt-2 text-foreground">{title}</h1>
                <p className="mt-3 type-body text-muted-foreground">{body}</p>
                <div className="mt-8 flex flex-wrap justify-center gap-3">
                    <Button asChild variant="primary">
                        <Link href={route('dashboard')}>Back to Dashboard</Link>
                    </Button>
                </div>
            </div>
        </>
    );

    if (!auth?.user) {
        return content;
    }

    return <AuthenticatedLayout>{content}</AuthenticatedLayout>;
}
