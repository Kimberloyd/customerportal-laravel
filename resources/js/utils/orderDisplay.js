import { CircleDashed, CircleDot, CircleDotDashed } from 'lucide-react';

export function statusBadge(status) {
    if (status === 'received') {
        return { label: 'Order Received', status: 'success' };
    }
    if (status === 'partial') {
        return { label: 'Partial', status: 'warning', pulse: true, icon: CircleDotDashed };
    }
    if (status === 'processed') {
        return { label: 'Processed', status: 'info', icon: CircleDot };
    }
    if (status === 'pending') {
        return { label: 'Pending', status: 'neutral', icon: CircleDashed };
    }
    if (status === 'returned') {
        return { label: 'Needs redelivery', status: 'warning', pulse: true };
    }
    if (status === 'completed') {
        return { label: 'Closed', status: 'success' };
    }
    if (status === 'cancelled') {
        return { label: 'Cancelled', status: 'neutral' };
    }

    return { label: status, status: 'neutral' };
}

export function formatDateTime(iso) {
    if (!iso) return '-';
    return new Date(iso).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
