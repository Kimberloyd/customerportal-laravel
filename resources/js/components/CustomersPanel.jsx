import { Table } from '@/components/motion/table';
import { Input } from '@/components/motion/input';
import { Pagination } from '@/components/interior/pagination';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const PAGE_SIZE = 10;
const TABLE_ROW_HEIGHT = 48;
const HORIZONTAL_SCROLLBAR_HEIGHT = 20;
const TABLE_VIEWPORT_HEIGHT =
    (PAGE_SIZE + 1) * TABLE_ROW_HEIGHT + HORIZONTAL_SCROLLBAR_HEIGHT;

export function CustomersPanel({ customers, filters, filterRouteName, filterExtraParams = {} }) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (overrides = {}) => {
        router.get(
            route(filterRouteName),
            { search, status: filters.status, ...filterExtraParams, ...overrides },
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
                    <div className="flex justify-start">
                        <AnimatedBadge
                            status={customer.is_active ? 'success' : 'neutral'}
                            size="sm"
                            className="border-0 bg-transparent px-0 shadow-none"
                        >
                            {customer.is_active ? 'Active' : 'Inactive'}
                        </AnimatedBadge>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <div className="space-y-6">
            <div className="flex justify-center bg-white">
                <Input
                    type="text"
                    value={search}
                    onChange={setSearch}
                    placeholder="Code, company, or channel"
                    aria-label="Search customers"
                    leftIcon={<Search className="h-4 w-4" />}
                    classNames={{
                        root: 'w-80',
                        field: 'h-9 w-80 rounded-full border-border bg-transparent shadow-none',
                        input: 'text-sm',
                    }}
                />
            </div>

            <Table
                data={customers.data}
                columns={columns}
                getRowId={(customer) => String(customer.id)}
                className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                rowHeight={TABLE_ROW_HEIGHT}
                height={TABLE_VIEWPORT_HEIGHT}
                resizable
                reorderable
                emptyState="No customers found. Try a different search."
                emptyStateHeight={PAGE_SIZE * TABLE_ROW_HEIGHT}
            />

            {customers.last_page > 1 && (
                <div className="flex justify-end">
                    <Pagination
                        count={customers.last_page}
                        page={customers.current_page}
                        onPageChange={(page) => applyFilters({ page })}
                        label="Customers pagination"
                    />
                </div>
            )}
        </div>
    );
}
