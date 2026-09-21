import { usePushRegistration } from '@/hooks/usePushRegistration';
import { usePage } from '@inertiajs/react';

export default function PushRegistration() {
    const { push, auth } = usePage().props;
    usePushRegistration(Boolean(push?.enabled), auth?.user?.id);

    return null;
}
