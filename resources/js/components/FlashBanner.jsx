import { usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Link2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const AUTO_DISMISS_MS = 5000;

export default function FlashBanner() {
    const { flash } = usePage().props;
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        setDismissed(false);
    }, [flash?.success, flash?.error, flash?.link]);

    useEffect(() => {
        if (!flash?.success && !flash?.link) {
            return;
        }

        const timer = setTimeout(() => setDismissed(true), AUTO_DISMISS_MS);

        return () => clearTimeout(timer);
    }, [flash?.success, flash?.link]);

    if (dismissed || (!flash?.success && !flash?.error && !flash?.link)) {
        return null;
    }

    return (
        <div className="sticky top-16 z-30">
            {flash.success && (
                <div className="border-y border-green-200 bg-green-50" role="status" aria-live="polite">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 text-sm text-green-700 sm:px-6 lg:px-8">
                        <span className="flex items-center gap-2">
                            <CircleCheck className="h-4 w-4 shrink-0" aria-hidden="true" />
                            {flash.success}
                        </span>
                        <button
                            type="button"
                            onClick={() => setDismissed(true)}
                            className="ml-4 shrink-0 text-green-700 hover:text-green-900"
                            aria-label="Dismiss"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            )}
            {flash.error && (
                <div className="border-y border-red-200 bg-red-50" role="alert">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 text-sm text-red-700 sm:px-6 lg:px-8">
                        <span className="flex items-center gap-2">
                            <CircleAlert className="h-4 w-4 shrink-0" aria-hidden="true" />
                            {flash.error}
                        </span>
                        <button
                            type="button"
                            onClick={() => setDismissed(true)}
                            className="ml-4 shrink-0 text-red-700 hover:text-red-900"
                            aria-label="Dismiss"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            )}
            {flash.link && (
                <div className="border-y border-amber-300 bg-amber-50" role="status" aria-live="polite">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 text-sm text-amber-800 sm:px-6 lg:px-8">
                        <span className="flex items-center gap-2">
                            <Link2 className="h-4 w-4 shrink-0" aria-hidden="true" />
                            {flash.link}
                        </span>
                        <button
                            type="button"
                            onClick={() => setDismissed(true)}
                            className="ml-4 shrink-0 text-amber-800 hover:text-amber-950"
                            aria-label="Dismiss"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
