import { Pagination } from '@/components/interior/pagination';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Input } from '@/components/motion/input';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const PAGE_SIZE = 10;
const TABLE_ROW_HEIGHT = 48;
const HORIZONTAL_SCROLLBAR_HEIGHT = 20;
const TABLE_VIEWPORT_HEIGHT =
    (PAGE_SIZE + 1) * TABLE_ROW_HEIGHT + HORIZONTAL_SCROLLBAR_HEIGHT;

export function ProductsPanel({ products = [], filters, loading = false }) {
    const [search, setSearch] = useState(filters.search);
    const [page, setPage] = useState(1);

    const filteredProducts = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        if (!query) {
            return products;
        }

        return products.filter((product) =>
            [
                product.product_name,
                product.generic_name,
                product.dosage,
                product.category,
                product.unit,
                product.sku,
            ].some((value) => String(value ?? '').toLocaleLowerCase().includes(query)),
        );
    }, [products, search]);

    const pageCount = Math.max(1, Math.ceil(filteredProducts.length / PAGE_SIZE));
    const visibleProducts = useMemo(
        () => filteredProducts.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE),
        [filteredProducts, page],
    );

    useEffect(() => {
        setPage(1);
    }, [products, search]);

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
                cell: (product) => (
                    <div className="flex justify-start">
                        <AnimatedBadge
                            status={product.is_active ? 'success' : 'neutral'}
                            size="sm"
                            className="border-0 bg-transparent px-0 shadow-none"
                        >
                            {product.is_active ? 'Active' : 'Inactive'}
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
                    placeholder={
                        loading
                            ? 'Loading products…'
                            : 'Name, generic name, SKU, or category'
                    }
                    aria-label="Search products"
                    disabled={loading}
                    leftIcon={<Search className="h-4 w-4" />}
                    classNames={{
                        root: 'w-80',
                        field: 'h-9 w-80 rounded-full border-border bg-transparent shadow-none',
                        input: 'text-sm',
                    }}
                />
            </div>

            <Table
                data={visibleProducts}
                columns={columns}
                getRowId={(product) => String(product.id)}
                className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                rowHeight={TABLE_ROW_HEIGHT}
                height={TABLE_VIEWPORT_HEIGHT}
                loading={loading}
                skeletonRows={PAGE_SIZE}
                resizable
                reorderable
                emptyState="No products found. Try a different search."
                emptyStateHeight={PAGE_SIZE * TABLE_ROW_HEIGHT}
            />

            {pageCount > 1 && (
                <div className="flex justify-end">
                    <Pagination
                        count={pageCount}
                        page={page}
                        onPageChange={setPage}
                        label="Products pagination"
                    />
                </div>
            )}
        </div>
    );
}
