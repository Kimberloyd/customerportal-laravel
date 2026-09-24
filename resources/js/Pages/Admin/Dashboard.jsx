import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AccountsPanel } from '@/components/AccountsPanel';
import { UserModal } from '@/components/CreateUserModal';
import { CustomersPanel } from '@/components/CustomersPanel';
import { SecondaryMetricsCard } from '@/components/dashboard/OverviewPanels';
import { ProductsPanel } from '@/components/ProductsPanel';
import { ResetPasswordModal } from '@/components/ResetPasswordModal';
import { TeamsPanel } from '@/components/TeamsPanel';
import { Button } from '@/components/ui/button';
import { Deferred, Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const number = new Intl.NumberFormat('en-PH');

export default function Dashboard({ activeTab, products, customers, customerUsers, staffUsers, filters, roleLabels, accountForm, teams, agents, summary }) {
    const [userModal, setUserModal] = useState({ open: false, user: null });
    const [resettingUser, setResettingUser] = useState(null);
    const summaryMetrics = [
        { label: 'Active accounts', value: number.format(summary.activeAccounts), href: route('admin.dashboard', { tab: 'accounts' }) },
        { label: 'Active customers', value: number.format(summary.activeCustomers), href: route('admin.dashboard', { tab: 'customers' }) },
        { label: 'Teams', value: number.format(summary.teams), href: route('admin.dashboard', { tab: 'teams' }) },
    ];

    const setUserModalOpen = (open) => {
        setUserModal((current) => (open ? { ...current, open: true } : { open: false, user: null }));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="type-page-heading text-foreground">Admin</h2>
                    {activeTab === 'accounts' && (
                        <Button
                            type="button"
                            variant="primary"
                            onClick={() => setUserModal({ open: true, user: null })}
                        >
                            Add Account
                        </Button>
                    )}
                </div>
            }
        >
            <Head title="Admin" />

            <div className="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 py-8 sm:px-6 lg:grid-cols-12 lg:px-8">
                <nav className="space-y-1" aria-label="Admin sections">
                    <Link
                        href={route('admin.dashboard', { tab: 'products' })}
                        aria-current={activeTab === 'products' ? 'page' : undefined}
                        className={`block rounded-md px-2 py-1.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[color:var(--focus-ring)] ${
                            activeTab === 'products'
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground hover:bg-hover hover:text-foreground'
                        }`}
                    >
                        Products
                    </Link>
                    <Link
                        href={route('admin.dashboard', { tab: 'customers' })}
                        aria-current={activeTab === 'customers' ? 'page' : undefined}
                        className={`block rounded-md px-2 py-1.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[color:var(--focus-ring)] ${
                            activeTab === 'customers'
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground hover:bg-hover hover:text-foreground'
                        }`}
                    >
                        Customers
                    </Link>
                    <Link
                        href={route('admin.dashboard', { tab: 'accounts' })}
                        aria-current={activeTab === 'accounts' ? 'page' : undefined}
                        className={`block rounded-md px-2 py-1.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[color:var(--focus-ring)] ${
                            activeTab === 'accounts'
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground hover:bg-hover hover:text-foreground'
                        }`}
                    >
                        Accounts
                    </Link>
                    <Link
                        href={route('admin.dashboard', { tab: 'teams' })}
                        aria-current={activeTab === 'teams' ? 'page' : undefined}
                        className={`block rounded-md px-2 py-1.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[color:var(--focus-ring)] ${activeTab === 'teams' ? 'font-semibold text-foreground' : 'text-muted-foreground hover:bg-hover hover:text-foreground'}`}
                    >
                        Teams
                    </Link>
                </nav>

                <div className="lg:col-span-10 space-y-6">
                    <div className="max-w-xl">
                        <SecondaryMetricsCard metrics={summaryMetrics} reducedMotion={false} orientation="horizontal" />
                    </div>
                    {activeTab === 'products' && (
                        <Deferred
                            data="products"
                            fallback={<ProductsPanel filters={filters} loading />}
                        >
                            <ProductsPanel products={products} filters={filters} />
                        </Deferred>
                    )}
                    {activeTab === 'customers' && (
                        <Deferred
                            data="customers"
                            fallback={(
                                <CustomersPanel
                                    filters={filters}
                                    filterRouteName="admin.dashboard"
                                    filterExtraParams={{ tab: 'customers' }}
                                    loading
                                />
                            )}
                        >
                            <CustomersPanel
                                customers={customers}
                                filters={filters}
                                filterRouteName="admin.dashboard"
                                filterExtraParams={{ tab: 'customers' }}
                            />
                        </Deferred>
                    )}
                    {activeTab === 'accounts' && (
                        <Deferred
                            data={['customerUsers', 'staffUsers']}
                            fallback={(
                                <AccountsPanel
                                    filters={filters}
                                    filterRouteName="admin.dashboard"
                                    filterExtraParams={{ tab: 'accounts' }}
                                    loading
                                />
                            )}
                        >
                            <AccountsPanel
                                customerUsers={customerUsers}
                                staffUsers={staffUsers}
                                filters={filters}
                                roleLabels={roleLabels}
                                filterRouteName="admin.dashboard"
                                filterExtraParams={{ tab: 'accounts' }}
                                onEdit={(user) => setUserModal({ open: true, user })}
                                onResetPassword={setResettingUser}
                            />
                        </Deferred>
                    )}
                    {activeTab === 'teams' && <TeamsPanel teams={teams} agents={agents} />}
                </div>
            </div>

            {activeTab === 'accounts' && (
                <UserModal
                    key={userModal.user?.id ?? 'create'}
                    open={userModal.open}
                    onOpenChange={setUserModalOpen}
                    user={userModal.user}
                    customers={accountForm?.customers}
                />
            )}

            {activeTab === 'accounts' && (
                <ResetPasswordModal
                    key={resettingUser?.id ?? 'reset-password'}
                    open={resettingUser !== null}
                    onOpenChange={(open) => !open && setResettingUser(null)}
                    user={resettingUser}
                />
            )}
        </AuthenticatedLayout>
    );
}
