import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AccountsPanel } from '@/components/AccountsPanel';
import { CreateUserModal } from '@/components/CreateUserModal';
import { CustomersPanel } from '@/components/CustomersPanel';
import { ProductsPanel } from '@/components/ProductsPanel';
import { Button } from '@/components/ui/button';
import { Deferred, Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Dashboard({ activeTab, products, customers, users, filters, roleLabels, accountForm }) {
    const [createUserOpen, setCreateUserOpen] = useState(false);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Admin</h2>
                    {activeTab === 'accounts' && (
                        <Button
                            type="button"
                            variant="primary"
                            className="h-10 rounded-md px-5 text-sm"
                            onClick={() => setCreateUserOpen(true)}
                        >
                            Add User
                        </Button>
                    )}
                </div>
            }
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
                        href={route('admin.dashboard', { tab: 'accounts' })}
                        className={`block text-sm ${
                            activeTab === 'accounts'
                                ? 'font-semibold text-gray-900'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        Accounts
                    </Link>
                </nav>

                <div className="lg:col-span-10">
                    {activeTab === 'products' && (
                        <Deferred
                            data="products"
                            fallback={<ProductsPanel filters={filters} loading />}
                        >
                            <ProductsPanel products={products} filters={filters} />
                        </Deferred>
                    )}
                    {activeTab === 'customers' && (
                        <CustomersPanel
                            customers={customers}
                            filters={filters}
                            filterRouteName="admin.dashboard"
                            filterExtraParams={{ tab: 'customers' }}
                        />
                    )}
                    {activeTab === 'accounts' && (
                        <AccountsPanel
                            users={users}
                            filters={filters}
                            roleLabels={roleLabels}
                            filterRouteName="admin.dashboard"
                            filterExtraParams={{ tab: 'accounts' }}
                        />
                    )}
                </div>
            </div>

            {activeTab === 'accounts' && (
                <CreateUserModal
                    open={createUserOpen}
                    onOpenChange={setCreateUserOpen}
                    allowAdminCreation={accountForm?.allowAdminCreation}
                    customers={accountForm?.customers}
                />
            )}
        </AuthenticatedLayout>
    );
}
