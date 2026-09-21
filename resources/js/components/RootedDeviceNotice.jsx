import { BottomSheet } from '@/components/motion/bottom-sheet';
import { Button } from '@/components/ui/button';
import { useRootedDevice } from '@/hooks/useRootedDevice';
import { Capacitor } from '@capacitor/core';

export default function RootedDeviceNotice() {
    const { open, acknowledge } = useRootedDevice();

    if (!Capacitor.isNativePlatform()) return null;

    return (
        <BottomSheet
            open={open}
            onOpenChange={(next) => {
                if (!next) acknowledge();
            }}
            snapPoints={['auto']}
            title="This phone looks rooted"
            description="Rooting removes some of Android's protections, so other apps on this phone may be able to read what you see in the portal."
            footer={
                <Button type="button" variant="primary" className="h-10 rounded-md px-5 text-sm" onClick={acknowledge}>
                    I understand
                </Button>
            }
        >
            <p className="text-sm text-muted-foreground">
                Only keep using the portal here if you trust this phone. You&rsquo;ll see this reminder again in a month.
            </p>
        </BottomSheet>
    );
}
