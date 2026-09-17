import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { SecondaryMetricsCard } from '@/components/dashboard/OverviewPanels';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { formatDateTime, statusBadge } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';

const number = new Intl.NumberFormat('en-PH');
const ACTIVITY_ROW_HEIGHT = 48;

// Account-audit actions are stored as db-style strings (see
// App\Support\UserAudit / UserController / TwoFactorAuthenticationController)
// -- this is the plain-language label for each one this page shows.
const ACTIVITY_LABEL = {
    created: 'Account created',
    updated: 'Details updated',
    'password reset': 'Password reset',
    activated: 'Account activated',
    deactivated: 'Account deactivated',
    two_factor_enabled: 'Two-factor authentication enabled',
    two_factor_disabled: 'Two-factor authentication disabled',
    two_factor_recovery_regenerated: 'Recovery codes regenerated',
    two_factor_recovery_used: 'Recovery code used at sign-in',
};

const activityColumns = [
    {
        key: 'created_at',
        header: 'Date',
        width: '180px',
        cell: (row) => <span className="text-muted-foreground">{formatDateTime(row.created_at)}</span>,
    },
    {
        key: 'actor_name',
        header: 'Actor',
        width: '180px',
        cell: (row) => <span className="text-foreground">{row.actor_name ?? '—'}</span>,
    },
    {
        key: 'actor_role',
        header: 'Role',
        width: '120px',
        cell: (row) => <span className="text-muted-foreground capitalize">{row.actor_role ?? '—'}</span>,
    },
    {
        key: 'action',
        header: 'Action',
        width: '220px',
        cell: (row) => <span className="font-medium text-foreground">{ACTIVITY_LABEL[row.action] ?? row.action}</span>,
    },
    {
        key: 'details',
        header: 'Details',
        cell: (row) => <span className="line-clamp-2 whitespace-pre-wrap text-foreground">{row.details ?? '—'}</span>,
    },
];

const topProductColumns = [
    {
        key: 'product_name',
        header: 'Product',
        cell: (row) => <span className="font-medium text-foreground">{row.product_name}</span>,
    },
    {
        key: 'total_quantity',
        header: 'Quantity',
        width: '110px',
        align: 'right',
        cell: (row) => <span className="text-foreground">{number.format(row.total_quantity)}</span>,
    },
    {
        key: 'order_count',
        header: 'Orders',
        width: '100px',
        align: 'right',
        cell: (row) => <span className="text-muted-foreground">{number.format(row.order_count)}</span>,
    },
];

function initialsFor(fullName) {
    const initials = fullName
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return initials || '?';
}

export default function Show({ user, stats, activity, order_insights: orderInsights }) {
    const metrics = [
        {
            label: user.role === 'customer' ? 'Orders placed' : 'Orders in your accounts',
            value: number.format(stats.order_count),
        },
        ...(stats.managed_customer_count !== null
            ? [{ label: 'Customers you manage', value: number.format(stats.managed_customer_count) }]
            : []),
        {
            label: 'Member since',
            value: user.member_since
                ? new Date(user.member_since).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
                : '—',
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="type-page-heading text-foreground">Profile</h1>
                    <Button asChild variant="tertiary">
                        <Link href={route('settings.edit')}>Edit in Settings</Link>
                    </Button>
                </div>
            }
        >
            <Head title="Profile" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <section className="rounded-xl border border-border bg-card p-6">
                    <div className="flex flex-wrap items-center gap-4">
                        <span
                            aria-hidden="true"
                            className="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-primary/10 text-lg font-semibold text-primary"
                        >
                            {initialsFor(user.full_name)}
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="type-section-heading text-foreground">{user.full_name}</h2>
                                <AnimatedBadge status="neutral" size="sm" pulse={false} showIcon={false}>
                                    {user.role_label}
                                </AnimatedBadge>
                            </div>
                            <dl className="mt-2 space-y-0.5 text-sm text-muted-foreground">
                                <div className="flex gap-2">
                                    <dt className="sr-only">Email</dt>
                                    <dd>{user.email}</dd>
                                </div>
                                {user.phone && (
                                    <div className="flex gap-2">
                                        <dt className="sr-only">Phone</dt>
                                        <dd>{user.phone}</dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                    </div>
                </section>

                <SecondaryMetricsCard metrics={metrics} reducedMotion={false} />

                {orderInsights && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div>
                            <div className="mb-3">
                                <h2 className="type-section-heading text-foreground">Order status breakdown</h2>
                                <p className="text-sm text-muted-foreground">
                                    Share of your {number.format(orderInsights.total_orders)} order{orderInsights.total_orders === 1 ? '' : 's'} by status.
                                </p>
                            </div>
                            {orderInsights.status_breakdown.length > 0 ? (
                                <div className="divide-y divide-border rounded-xl border border-border bg-card">
                                    {orderInsights.status_breakdown.map((row) => {
                                        const badge = statusBadge(row.status);
                                        return (
                                            <div key={row.status} className="flex items-center justify-between gap-3 px-4 py-3">
                                                <span className="text-sm text-foreground">{badge.label}</span>
                                                <span className="text-sm tabular-nums text-muted-foreground">
                                                    {row.percentage}% ({number.format(row.count)})
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="rounded-xl border border-border bg-card px-4 py-6 text-center text-sm text-muted-foreground">
                                    No orders placed yet.
                                </div>
                            )}
                        </div>

                        <div>
                            <div className="mb-3">
                                <h2 className="type-section-heading text-foreground">Most ordered products</h2>
                                <p className="text-sm text-muted-foreground">Ranked by total quantity ordered.</p>
                            </div>
                            <Table
                                data={orderInsights.top_products}
                                columns={topProductColumns}
                                getRowId={(row) => row.product_name}
                                rowHeight={ACTIVITY_ROW_HEIGHT}
                                height={Math.max(orderInsights.top_products.length, 1) * ACTIVITY_ROW_HEIGHT + 60}
                                emptyState="No products ordered yet."
                            />
                        </div>
                    </div>
                )}

                <div>
                    <div className="mb-3">
                        <h2 className="type-section-heading text-foreground">Recent account activity</h2>
                        <p className="text-sm text-muted-foreground">Changes and security events on your account.</p>
                    </div>
                    <Table
                        data={activity}
                        columns={activityColumns}
                        getRowId={(row) => String(row.id)}
                        rowHeight={ACTIVITY_ROW_HEIGHT}
                        height={Math.max(activity.length, 1) * ACTIVITY_ROW_HEIGHT + 60}
                        emptyState="No recent activity. Changes to your account and security events will appear here."
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
