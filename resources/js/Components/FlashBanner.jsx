import { usePage } from '@inertiajs/react';

export default function FlashBanner() {
    const { flash } = usePage().props;

    if (!flash?.success && !flash?.error && !flash?.link) {
        return null;
    }

    return (
        <div className="space-y-2 px-4 pt-4 sm:px-6 lg:px-8">
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
            {flash.link && (
                <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                    {flash.link}
                </div>
            )}
        </div>
    );
}
