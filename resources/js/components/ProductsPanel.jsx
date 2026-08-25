import { Dropdown } from '@/components/interior/dropdown';
import { Table } from '@/components/motion/table';
import { Input } from '@/components/motion/input';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const STATUS_OPTIONS = [
    { value: 'active', label: 'Active', hint: 'default' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'all', label: 'All' },
];

export function ProductsPanel({ products, filters, filterRouteName, filterExtraParams = {} }) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    const applyFilters = (overrides = {}) => {
        router.get(
            route(filterRouteName),
            {
                search,
                status,
                sort_by: filters.sort_by,
                sort_dir: filters.sort_dir,
                ...filterExtraParams,
                ...overrides,
            },
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
            {
                key: 'product_name',
                header: 'Product Name',
                sortable: true,
                cell: (product) => product.product_name,
            },
            {
                key: 'generic_name',
                header: 'Generic Name',
                sortable: true,
                cell: (product) => product.generic_name ?? '-',
            },
            {
                key: 'dosage',
                header: 'Variant',
                sortable: true,
                cell: (product) => product.dosage ?? '-',
            },
            { key: 'category', header: 'Category', sortable: true },
            {
                key: 'unit',
                header: 'Unit',
                sortable: true,
                cell: (product) => product.unit?.toUpperCase() ?? '-',
            },
            {
                key: 'description',
                header: 'Description',
                sortable: true,
                width: '320px',
                cell: (product) => product.description ?? '-',
            },
            {
                key: 'sku',
                header: 'SKU',
                cell: (product) => product.sku ?? '-',
            },
            {
                key: 'unit_price',
                header: 'Price',
                sortable: true,
                align: 'right',
                cell: (product) =>
                    product.unit_price == null ? '-' : Number(product.unit_price).toFixed(2),
            },
            {
                key: 'is_active',
                header: 'Status',
                align: 'center',
                cell: (product) => (
                    <span
                        className={`rounded-full px-2 py-0.5 text-xs ${
                            product.is_active
                                ? 'bg-green-100 text-green-800'
                                : 'bg-gray-200 text-gray-700'
                        }`}
                    >
                        {product.is_active ? 'Active' : 'Inactive'}
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
                    classNames={{
                        field: 'h-9 w-80 rounded-[9px] border-border ring-0 shadow-none',
                    }}
                />
                <div className="flex flex-col text-sm text-gray-600">
                    <span>Status</span>
                    <Dropdown
                        items={STATUS_OPTIONS}
                        value={status}
                        onChange={(value) => {
                            setStatus(value);
                            applyFilters({ status: value });
                        }}
                        label={
                            STATUS_OPTIONS.find((option) => option.value === status)?.label ??
                            'Select status'
                        }
                        className="mt-1"
                    />
                </div>
            </div>

            <Table
                data={products}
                columns={columns}
                getRowId={(product) => String(product.id)}
                className="rounded-[9px] [&>div]:overflow-hidden [&_td:not(:nth-last-child(-n+2))]:border-r [&_td:not(:nth-last-child(-n+2))]:border-border/60 [&_th:not(:nth-last-child(-n+2))]:border-r [&_th:not(:nth-last-child(-n+2))]:border-border/60"
                resizable
                reorderable
                emptyState="No products match these filters."
            />
        </div>
    );
}
