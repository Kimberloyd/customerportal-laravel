import { useOnlineStatus } from '@/hooks/useOnlineStatus';
import { AnimatePresence, motion } from 'motion/react';

export default function OfflineBanner() {
    const online = useOnlineStatus();

    return (
        <AnimatePresence>
            {!online && (
                <motion.div
                    role="status"
                    initial={{ opacity: 0, y: -8 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -8 }}
                    transition={{ duration: 0.2 }}
                    className="fixed inset-x-0 top-0 z-[60] border-b border-amber-200 bg-amber-50 px-4 pb-2 pt-[max(env(safe-area-inset-top),0.5rem)] text-center text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                >
                    You&rsquo;re offline. Pages and changes won&rsquo;t load until your connection is back.
                </motion.div>
            )}
        </AnimatePresence>
    );
}
