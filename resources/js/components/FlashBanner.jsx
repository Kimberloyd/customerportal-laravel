import { usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Link2, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const AUTO_DISMISS_MS = 5000;

const BANNER_STYLES = {
    success: {
        icon: CircleCheck,
        container: 'border-green-300 bg-green-50',
        content: 'text-green-800',
        dismiss: 'text-green-800 hover:text-green-950',
        role: 'status',
    },
    error: {
        icon: CircleAlert,
        container: 'border-red-300 bg-red-50',
        content: 'text-red-800',
        dismiss: 'text-red-800 hover:text-red-950',
        role: 'alert',
    },
    link: {
        icon: Link2,
        container: 'border-amber-300 bg-amber-50',
        content: 'text-amber-800',
        dismiss: 'text-amber-800 hover:text-amber-950',
        role: 'status',
    },
    warning: {
        icon: TriangleAlert,
        container: 'border-amber-300 bg-amber-50',
        content: 'text-amber-800',
        dismiss: 'text-amber-800 hover:text-amber-950',
        role: 'status',
    },
};

function BannerRow({ message, variant, onDismiss }) {
    const style = BANNER_STYLES[variant];
    const Icon = style.icon;

    return (
        <div className={`border-y ${style.container}`} role={style.role} aria-live={style.role === 'status' ? 'polite' : undefined}>
            <div className={`mx-auto flex max-w-7xl items-center justify-between px-4 py-3 text-base sm:px-6 lg:px-8 ${style.content}`}>
                <span className="flex items-center gap-2">
                    <Icon className="h-5 w-5 shrink-0" aria-hidden="true" />
                    {message}
                </span>
                <button
                    type="button"
                    onClick={onDismiss}
                    className={`ml-4 shrink-0 ${style.dismiss}`}
                    aria-label="Dismiss notification"
                >
                    <X className="h-5 w-5" />
                </button>
            </div>
        </div>
    );
}

export default function FlashBanner({ message = null, variant = 'warning', sticky = true, autoDismiss }) {
    const { flash } = usePage().props;
    const [dismissed, setDismissed] = useState(false);
    const entries = message
        ? [{ variant, message }]
        : [
            { variant: 'success', message: flash?.success },
            { variant: 'error', message: flash?.error },
            { variant: 'link', message: flash?.link },
        ].filter((entry) => entry.message);
    const shouldAutoDismiss = autoDismiss ?? (!message && Boolean(flash?.success || flash?.link));

    useEffect(() => {
        setDismissed(false);
    }, [message, variant, flash?.success, flash?.error, flash?.link]);

    useEffect(() => {
        if (!shouldAutoDismiss || entries.length === 0) {
            return;
        }

        const timer = setTimeout(() => setDismissed(true), AUTO_DISMISS_MS);

        return () => clearTimeout(timer);
    }, [shouldAutoDismiss, message, variant, flash?.success, flash?.error, flash?.link]);

    if (dismissed || entries.length === 0) {
        return null;
    }

    return (
        <div className={sticky ? 'sticky top-[calc(4rem+1px)] z-30' : undefined}>
            {entries.map((entry) => (
                <BannerRow
                    key={entry.variant}
                    message={entry.message}
                    variant={entry.variant}
                    onDismiss={() => setDismissed(true)}
                />
            ))}
        </div>
    );
}
