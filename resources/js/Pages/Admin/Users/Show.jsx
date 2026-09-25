import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { formatDateTime, statusBadge } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';

const number = new Intl.NumberFormat('en-PH');
const ACTIVITY_ROW_HEIGHT = 48;

// Account-audit actions are stored as db-style strings (see
// App\Support\UserAudit / UserController) -- this is the plain-language
// label for each one this page shows.
const ACTIVITY_LABEL = {
    created: 'Account created',
    updated: 'Details updated',
    'password reset': 'Password reset',
    activated: 'Account activated',
    deactivated: 'Account deactivated',
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
        width: '200px',
        cell: (row) => (
            <span className="text-foreground">
                {row.actor_name ?? '—'}
                {row.actor_role && <span className="ml-1 text-muted-foreground capitalize">({row.actor_role})</span>}
            </span>
        ),
    },
    {
        key: 'action',
        header: 'Action',
        width: '200px',
        cell: (row) => <span className="font-medium text-foreground">{ACTIVITY_LABEL[row.action] ?? row.action}</span>,
    },
    {
        key: 'details',
        header: 'Details',
        cell: (row) => <span className="line-clamp-2 whitespace-pre-wrap text-muted-foreground">{row.details ?? '—'}</span>,
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

function memberSince(isoDate) {
    return isoDate
        ? new Date(isoDate).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
        : null;
}

export default function Show({ account, order_insights: orderInsights, activity }) {
    const since = memberSince(account.member_since);

    return (
        <AuthenticatedLayout
            header={
                <nav aria-label="Breadcrumb">
                    <h2 className="flex items-center gap-2 text-xl font-semibold leading-tight">
                        <Link
                            href={route('admin.dashboard', { tab: 'accounts' })}
                            className="text-muted-foreground transition-colors hover:text-primary"
                        >
                            Admin
                        </Link>
                        <span aria-hidden="true" className="text-muted-foreground">/</span>
                        <span aria-current="page" className="text-foreground">{account.full_name}</span>
                    </h2>
                </nav>
            }
        >
            <Head title={account.full_name} />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {/* Identity + the one number this page exists to answer, side by
                    side instead of as two separate cards of unequal weight. */}
                <section className="rounded-xl border border-border bg-card p-6 sm:flex sm:items-center sm:justify-between sm:gap-6">
                    <div className="flex flex-wrap items-center gap-4">
                        <span
                            aria-hidden="true"
                            className="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-primary/10 text-base font-semibold text-primary"
                        >
                            {initialsFor(account.full_name)}
                        </span>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="type-section-heading text-foreground">{account.full_name}</h1>
                                <AnimatedBadge status="neutral" size="sm" pulse={false} showIcon={false}>
                                    {account.role_label}
                                </AnimatedBadge>
                                <AnimatedBadge status={account.is_active ? 'success' : 'neutral'} size="sm" pulse={false} showIcon={false}>
                                    {account.is_active ? 'Active' : 'Inactive'}
                                </AnimatedBadge>
                            </div>
                            <dl className="mt-1.5 space-y-0.5 text-sm text-muted-foreground">
                                <div className="flex gap-2">
                                    <dt className="sr-only">Email</dt>
                                    <dd>{account.email}</dd>
                                </div>
                                {account.phone && (
                                    <div className="flex gap-2">
                                        <dt className="sr-only">Phone</dt>
                                        <dd>{account.phone}</dd>
                                    </div>
                                )}
                                <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-muted-foreground/80">
                                    {account.linked_customer_name && <span>{account.linked_customer_name}</span>}
                                    {since && <span>Member since {since}</span>}
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div className="mt-5 shrink-0 border-t border-border pt-4 text-left sm:mt-0 sm:border-t-0 sm:border-l sm:pl-6 sm:pt-0 sm:text-right">
                        <p className="text-sm text-muted-foreground">Orders placed</p>
                        <p className="text-4xl font-semibold tabular-nums text-foreground">{number.format(account.order_count)}</p>
                    </div>
                </section>

                {orderInsights && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div>
                            <div className="mb-3">
                                <h2 className="type-section-heading text-foreground">Order status breakdown</h2>
                                <p className="text-sm text-muted-foreground">
                                    Share of {number.format(orderInsights.total_orders)} order{orderInsights.total_orders === 1 ? '' : 's'} by status.
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
                                <p className="rounded-xl border border-border bg-card px-4 py-6 text-center text-sm text-muted-foreground">
                                    No orders placed yet.
                                </p>
                            )}
                        </div>

                        <div>
                            <div className="mb-3">
                                <h2 className="type-section-heading text-foreground">Most ordered products</h2>
                                <p className="text-sm text-muted-foreground">Ranked by total quantity ordered.</p>
                            </div>
                            {orderInsights.top_products.length > 0 ? (
                                <div className="divide-y divide-border rounded-xl border border-border bg-card">
                                    {orderInsights.top_products.map((row) => (
                                        <div key={row.product_name} className="flex items-center justify-between gap-3 px-4 py-3">
                                            <span className="truncate text-sm font-medium text-foreground">{row.product_name}</span>
                                            <span className="shrink-0 text-sm tabular-nums text-muted-foreground">
                                                {number.format(row.total_quantity)} unit{row.total_quantity === 1 ? '' : 's'} · {number.format(row.order_count)} order{row.order_count === 1 ? '' : 's'}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="rounded-xl border border-border bg-card px-4 py-6 text-center text-sm text-muted-foreground">
                                    No products ordered yet.
                                </p>
                            )}
                        </div>
                    </div>
                )}

                <div>
                    <div className="mb-3">
                        <h2 className="type-section-heading text-foreground">Recent account activity</h2>
                        <p className="text-sm text-muted-foreground">Changes and security events on this account.</p>
                    </div>
                    {activity.length > 0 ? (
                        <Table
                            data={activity}
                            columns={activityColumns}
                            getRowId={(row) => String(row.id)}
                            rowHeight={ACTIVITY_ROW_HEIGHT}
                            height={activity.length * ACTIVITY_ROW_HEIGHT + 60}
                            emptyState="No recent activity."
                        />
                    ) : (
                        <p className="rounded-xl border border-border bg-card px-4 py-6 text-center text-sm text-muted-foreground">
                            No recent activity.
                        </p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
