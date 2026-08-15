import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const SOURCE_OPTIONS = [
    { value: 'all', label: 'All Sources' },
    { value: 'generic', label: 'Generic Alias' },
    { value: 'standard', label: 'Standard' },
];

const STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
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
            { key: 'product_name', header: 'Brand Name', sortable: true },
            {
                key: 'generic_name',
                header: 'Generic Name',
                sortable: true,
                cell: (product) => product.generic_name ?? '-',
            },
            { key: 'category', header: 'Category', sortable: true },
            { key: 'unit', header: 'Unit', sortable: true, cell: (product) => product.unit ?? '-' },
            {
                key: 'description',
                header: 'Description',
                sortable: true,
                cell: (product) => product.description ?? '-',
            },
            { key: 'sku', header: 'SKU', cell: (product) => product.sku ?? '-' },
            {
                key: 'unit_price',
                header: 'Price',
                sortable: true,
                align: 'right',
                cell: (product) => Number(product.unit_price ?? 0).toFixed(2),
            },
            ...(canManage
                ? [
                      {
                          key: 'is_active',
                          header: 'Status',
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
                        classNames={{ field: 'h-9 rounded-none' }}
                    />
                    <label className="flex flex-col text-sm text-gray-600">
                        Source
                        <select
                            value={source}
                            onChange={(e) => {
                                setSource(e.target.value);
                                applyFilters({ source: e.target.value });
                            }}
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        >
                            {SOURCE_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    {canManage && (
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
                    )}
                </div>

                <>
                    <Table
                        data={products.data}
                        columns={columns}
                        getRowId={(product) => String(product.id)}
                        resizable
                        reorderable
                        emptyState="No products match these filters."
                    />

                    {products.last_page > 1 && (
                        <nav className="mt-4 flex flex-wrap items-center gap-1 text-sm">
                            {products.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`rounded px-3 py-1 ${
                                        link.active
                                            ? 'bg-gray-800 text-white'
                                            : link.url
                                              ? 'text-gray-600 hover:bg-gray-100'
                                              : 'cursor-not-allowed text-gray-300'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </nav>
                    )}
                </>
            </div>
        </AuthenticatedLayout>
    );
}
