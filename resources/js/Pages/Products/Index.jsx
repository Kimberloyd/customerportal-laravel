import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Dropdown } from '@/Components/interior/dropdown';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const SOURCE_OPTIONS = [
    { value: 'all', label: 'All Sources', hint: 'default' },
    { value: 'generic', label: 'Generic Alias' },
    { value: 'standard', label: 'Standard' },
];

const STATUS_OPTIONS = [
    { value: 'active', label: 'Active', hint: 'default' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'all', label: 'All' },
];

export default function Index({ products, filters, canManage }) {
    const [search, setSearch] = useState(filters.search);
    const [source, setSource] = useState(filters.source);
    const [status, setStatus] = useState(filters.status);

    const applyFilters = (overrides = {}) => {
        router.get(
            route('products.index'),
            {
                search,
                source,
                status,
                sort_by: filters.sort_by,
                sort_dir: filters.sort_dir,
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

    const deleteProduct = (product) => {
        if (!confirm(`Delete ${product.product_name}?`)) return;
        router.delete(route('products.destroy', product.id));
    };

    const toggleActive = (product) => {
        router.post(route('products.toggle-active', product.id));
    };

    const columns = useMemo(
        () => [
            {
                key: 'product_name',
                header: 'Brand Name',
                sortable: true,
                width: '300px',
            },
            {
                key: 'generic_name',
                header: 'Generic Name',
                sortable: true,
                width: '300px',
                cell: (product) => product.generic_name ?? '-',
            },
            { key: 'category', header: 'Category', sortable: true, width: '160px' },
            {
                key: 'unit',
                header: 'Unit',
                sortable: true,
                width: '120px',
                cell: (product) => product.unit?.toUpperCase() ?? '-',
            },
            {
                key: 'description',
                header: 'Description',
                sortable: true,
                width: '400px',
                cell: (product) => product.description ?? '-',
            },
            {
                key: 'sku',
                header: 'SKU',
                width: '160px',
                cell: (product) => product.sku ?? '-',
            },
            {
                key: 'unit_price',
                header: 'Price',
                sortable: true,
                align: 'right',
                width: '130px',
                cell: (product) => Number(product.unit_price ?? 0).toFixed(2),
            },
            ...(canManage
                ? [
                      {
                          key: 'is_active',
                          header: 'Status',
                          width: '120px',
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
                      {
                          key: 'actions',
                          header: 'Actions',
                          width: '280px',
                          cell: (product) => (
                              <div className="flex items-center gap-1 whitespace-nowrap">
                                  <Button asChild variant="ghost" size="compact">
                                      <Link href={route('products.edit', product.id)}>Edit</Link>
                                  </Button>
                                  <Button
                                      variant="ghost"
                                      size="compact"
                                      onClick={() => toggleActive(product)}
                                  >
                                      {product.is_active ? 'Deactivate' : 'Reactivate'}
                                  </Button>
                                  <Button
                                      variant="ghost"
                                      size="compact"
                                      className="text-red-600 hover:text-red-700"
                                      onClick={() => deleteProduct(product)}
                                  >
                                      Delete
                                  </Button>
                              </div>
                          ),
                      },
                  ]
                : []),
        ],
        [canManage],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Products
                    </h2>
                    {canManage && (
                        <Button asChild variant="primary">
                            <Link href={route('products.create')}>Add Product</Link>
                        </Button>
                    )}
                </div>
            }
        >
            <Head title="Products" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-end gap-3 bg-white">
                    <Input
                        label="Search"
                        type="text"
                        value={search}
                        onChange={setSearch}
                        leftIcon={<Search className="h-4 w-4" />}
                        classNames={{
                            field: 'h-9 rounded-[9px] border-border ring-0 shadow-none',
                        }}
                    />
                    <div className="flex flex-col text-sm text-gray-600">
                        <span>Source</span>
                        <Dropdown
                            items={SOURCE_OPTIONS}
                            value={source}
                            onChange={(value) => {
                                setSource(value);
                                applyFilters({ source: value });
                            }}
                            label={
                                SOURCE_OPTIONS.find((option) => option.value === source)?.label ??
                                'Select source'
                            }
                            className="mt-1"
                        />
                    </div>
                    {canManage && (
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
                    )}
                </div>

                <Table
                    data={products}
                    columns={columns}
                    getRowId={(product) => String(product.id)}
                    className="rounded-[9px] [&_td:not(:last-child)]:border-r [&_td:not(:last-child)]:border-border/60 [&_th:not(:last-child)]:border-r [&_th:not(:last-child)]:border-border/60"
                    resizable
                    reorderable
                    emptyState="No products match these filters."
                />
            </div>
        </AuthenticatedLayout>
    );
}
