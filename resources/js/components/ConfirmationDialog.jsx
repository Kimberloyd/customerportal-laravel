import { Input } from '@/components/motion/input';
import { Button } from '@/components/ui/button';
import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { useEffect, useState } from 'react';

export default function ConfirmationDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel,
    onConfirm,
    destructive = false,
    processing = false,
    confirmationText,
}) {
    const [confirmationValue, setConfirmationValue] = useState('');
    const requiresConfirmation = Boolean(confirmationText);
    const confirmationMatches = !requiresConfirmation
        || confirmationValue.trim() === confirmationText;

    useEffect(() => {
        setConfirmationValue('');
    }, [open, confirmationText]);

    return (
        <Modal
            open={open}
            onClose={() => onOpenChange(false)}
            title={title}
            maxWidth={560}
            className="[&>div:first-child]:px-6 [&>div:first-child]:pb-5 [&>div:first-child]:pt-6 [&>div:first-child_h2]:!text-lg [&>div:last-child]:mt-auto [&>div:last-child]:px-6 [&>div:last-child]:py-5"
            closeOnBackdrop={!processing}
            closeOnEscape={!processing}
            children={
                <AutoHeightReveal>
                    <div className="space-y-3 px-2 pt-2">
                        <p className="text-sm text-stone-600 dark:text-stone-300">{description}</p>
                        {requiresConfirmation ? (
                            <div className="space-y-2">
                                <label
                                    htmlFor="confirmation-account-name"
                                    className="block text-sm text-stone-600"
                                >
                                    Type <strong className="font-semibold text-stone-900">{confirmationText}</strong> to confirm.
                                </label>
                                <Input
                                    id="confirmation-account-name"
                                    type="text"
                                    value={confirmationValue}
                                    onChange={setConfirmationValue}
                                    placeholder="Enter the name shown above"
                                    autoComplete="off"
                                    aria-label={`Type ${confirmationText} to confirm deletion`}
                                    classNames={{
                                        field: 'h-10 rounded-md',
                                        input: 'text-sm',
                                    }}
                                />
                            </div>
                        ) : null}
                    </div>
                </AutoHeightReveal>
            }
            footer={
                <>
                    <Button
                        type="button"
                        variant="tertiary"
                        className="h-10 rounded-md px-5 text-sm"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'primary'}
                        className="h-10 rounded-md px-5 text-sm"
                        onClick={onConfirm}
                        loading={processing}
                        disabled={!confirmationMatches}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        />
    );
}
