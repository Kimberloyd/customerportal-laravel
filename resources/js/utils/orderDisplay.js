export function statusBadge(status) {
    if (status === 'partial' || status === 'processing') {
        return { label: 'Partial', status: 'warning', pulse: true };
    }
    if (status === 'submitted') {
        return { label: 'Submitted', status: 'info' };
    }
    if (status === 'completed') {
        return { label: 'Completed', status: 'success' };
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
