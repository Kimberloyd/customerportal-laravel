import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

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
                              <div className="space-x-2 whitespace-nowrap">
                                  <Link
                                      href={route('products.edit', product.id)}
                                      className="text-indigo-600 hover:underline"
                                  >
                                      Edit
                                  </Link>
                                  <button
                                      onClick={() => toggleActive(product)}
                                      className="text-gray-600 hover:underline"
                                  >
                                      {product.is_active ? 'Deactivate' : 'Reactivate'}
                                  </button>
                                  <button
                                      onClick={() => deleteProduct(product)}
                                      className="text-red-600 hover:underline"
                                  >
                                      Delete
                                  </button>
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
                        <Link
                            href={route('products.create')}
                            className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            Add Product
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Products" />

            <div className="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters();
                    }}
                    className="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm"
                >
                    <label className="flex flex-col text-sm text-gray-600">
                        Search
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        />
                    </label>
                    <label className="flex flex-col text-sm text-gray-600">
                        Source
                        <select
                            value={source}
                            onChange={(e) => setSource(e.target.value)}
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
                                onChange={(e) => setStatus(e.target.value)}
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
                    <button
                        type="submit"
                        className="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        Apply
                    </button>
                </form>

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
