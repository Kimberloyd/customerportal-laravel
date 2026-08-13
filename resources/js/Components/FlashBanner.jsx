import { usePage } from '@inertiajs/react';

export default function FlashBanner() {
    const { flash } = usePage().props;

    if (!flash?.success && !flash?.error) {
        return null;
    }

    return (
        <div className="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            {flash.success && (
                <div className="rounded-md bg-green-50 p-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    {flash.error}
                </div>
            )}
        </div>
    );
}
