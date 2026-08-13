export function statusBadge(status) {
    if (status === 'partial' || status === 'processing') {
        return { label: 'Partial', className: 'bg-amber-100 text-amber-800' };
    }
    if (status === 'submitted') {
        return { label: 'Submitted', className: 'bg-blue-100 text-blue-800' };
    }
    if (status === 'completed') {
        return { label: 'Completed', className: 'bg-green-100 text-green-800' };
    }
    if (status === 'cancelled') {
        return { label: 'Cancelled', className: 'bg-gray-200 text-gray-700' };
    }

    return { label: status, className: 'bg-gray-100 text-gray-700' };
}

export function formatDateTime(iso) {
    if (!iso) return '-';
    return new Date(iso).toISOString().slice(0, 16).replace('T', ' ');
}
