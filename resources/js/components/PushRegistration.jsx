import { usePushRegistration } from '@/hooks/usePushRegistration';
import { usePage } from '@inertiajs/react';

export default function PushRegistration() {
    usePushRegistration(Boolean(usePage().props.push?.enabled));

    return null;
}
