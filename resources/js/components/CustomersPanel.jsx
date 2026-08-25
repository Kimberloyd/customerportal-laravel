import { Table } from '@/components/motion/table';
import { Input } from '@/components/motion/input';
import { Pagination } from '@/components/interior/pagination';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'all', label: 'All' },
];

export function CustomersPanel({ customers, filters, filterRouteName, filterExtraParams = {} }) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    const applyFilters = (overrides = {}) => {
        router.get(
            route(filterRouteName),
            { search, status, ...filterExtraParams, ...overrides },
            { preserveState: true, preserveScroll: true },
        );
    };

    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timeout = setTimeout(() => applyFilters({ search }), 400);
        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const columns = useMemo(
        () => [
            { key: 'customer_code', header: 'Code', sortable: true },
            { key: 'company_name', header: 'Company', sortable: true },
            { key: 'channel', header: 'Channel', sortable: true },
            {
                key: 'is_active',
                header: 'Status',
                cell: (customer) => (
                    <span
                        className={`rounded-full px-2 py-0.5 text-xs ${
                            customer.is_active
                                ? 'bg-green-100 text-green-800'
                                : 'bg-gray-200 text-gray-700'
                        }`}
                    >
                        {customer.is_active ? 'Active' : 'Inactive'}
                    </span>
                ),
            },
        ],
        [],
    );

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-end gap-3 bg-white">
                <Input
                    label="Search"
                    type="text"
                    value={search}
                    onChange={setSearch}
                    leftIcon={<Search className="h-4 w-4" />}
                    classNames={{ field: 'h-9 w-80 rounded-none' }}
                />
                <label className="flex flex-col text-sm text-gray-600">
                    Status
                    <select
                        value={status}
                        onChange={(e) => {
                            setStatus(e.target.value);
                            applyFilters({ status: e.target.value });
                        }}
                        className="mt-1 rounded-md border-gray-300 text-sm"
                    >
                        {STATUS_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            <Table
                data={customers.data}
                columns={columns}
                getRowId={(customer) => String(customer.id)}
                height={(Math.max(customers.data.length, 1) + 1) * 49}
                resizable
                reorderable
                emptyState="No customers match these filters."
            />

            {customers.last_page > 1 && (
                <Pagination
                    count={customers.last_page}
                    page={customers.current_page}
                    onPageChange={(page) => applyFilters({ page })}
                    label="Customers pagination"
                />
            )}
        </div>
    );
}
