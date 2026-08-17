import { usePage } from '@inertiajs/react';

export default function FlashBanner() {
    const { flash } = usePage().props;

    if (!flash?.success && !flash?.error && !flash?.link) {
        return null;
    }

    return (
        <div>
            {flash.success && (
                <div className="bg-green-50">
                    <div className="mx-auto max-w-7xl px-4 py-3 text-sm text-green-700 sm:px-6 lg:px-8">
                        {flash.success}
                    </div>
                </div>
            )}
            {flash.error && (
                <div className="bg-red-50">
                    <div className="mx-auto max-w-7xl px-4 py-3 text-sm text-red-700 sm:px-6 lg:px-8">
                        {flash.error}
                    </div>
                </div>
            )}
            {flash.link && (
                <div className="border-y border-amber-300 bg-amber-50">
                    <div className="mx-auto max-w-7xl px-4 py-3 text-sm text-amber-800 sm:px-6 lg:px-8">
                        {flash.link}
                    </div>
                </div>
            )}
        </div>
    );
}
