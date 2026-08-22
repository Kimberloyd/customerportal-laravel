import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CustomersPanel } from '@/components/CustomersPanel';
import { ProductsPanel } from '@/components/ProductsPanel';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ activeTab, products, customers, filters }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Admin</h2>}
        >
            <Head title="Admin" />

            <div className="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 py-8 sm:px-6 lg:grid-cols-12 lg:px-8">
                <nav className="space-y-4 lg:col-span-2">
                    <Link
                        href={route('admin.dashboard', { tab: 'products' })}
                        className={`block text-sm ${
                            activeTab === 'products'
                                ? 'font-semibold text-gray-900'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        Products
                    </Link>
                    <Link
                        href={route('admin.dashboard', { tab: 'customers' })}
                        className={`block text-sm ${
                            activeTab === 'customers'
                                ? 'font-semibold text-gray-900'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        Customers
                    </Link>
                    <Link
                        href={route('admin.users.index')}
                        className="block text-sm text-gray-500 hover:text-gray-700"
                    >
                        Accounts
                    </Link>
                </nav>

                <div className="lg:col-span-10">
                    {activeTab === 'products' && (
                        <ProductsPanel
                            products={products}
                            filters={filters}
                            filterRouteName="admin.dashboard"
                            filterExtraParams={{ tab: 'products' }}
                        />
                    )}
                    {activeTab === 'customers' && (
                        <CustomersPanel
                            customers={customers}
                            filters={filters}
                            filterRouteName="admin.dashboard"
                            filterExtraParams={{ tab: 'customers' }}
                        />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
