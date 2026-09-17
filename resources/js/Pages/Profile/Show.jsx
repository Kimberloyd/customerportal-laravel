import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { SecondaryMetricsCard } from '@/components/dashboard/OverviewPanels';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Timeline } from '@/components/timelines-activity-feed';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import {
    KeyRound,
    Pencil,
    PencilLineIcon,
    Shield,
    ShieldCheck,
    User,
    UserCheck,
    UserRoundX,
} from 'lucide-react';

const number = new Intl.NumberFormat('en-PH');

const ACTIVITY_STYLE = {
    created: { Icon: User, tone: 'bg-primary/10 text-primary', label: 'Account created' },
    updated: { Icon: PencilLineIcon, tone: 'bg-primary/10 text-primary', label: 'Details updated' },
    'password reset': { Icon: KeyRound, tone: 'bg-info/10 text-info', label: 'Password reset' },
    activated: { Icon: UserCheck, tone: 'bg-success/10 text-success', label: 'Account activated' },
    deactivated: { Icon: UserRoundX, tone: 'bg-destructive/10 text-destructive', label: 'Account deactivated' },
    two_factor_enabled: { Icon: ShieldCheck, tone: 'bg-success/10 text-success', label: 'Two-factor authentication enabled' },
    two_factor_disabled: { Icon: Shield, tone: 'bg-muted text-muted-foreground', label: 'Two-factor authentication disabled' },
    two_factor_recovery_regenerated: { Icon: KeyRound, tone: 'bg-info/10 text-info', label: 'Recovery codes regenerated' },
    two_factor_recovery_used: { Icon: KeyRound, tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', label: 'Recovery code used at sign-in' },
};
const DEFAULT_ACTIVITY_STYLE = { Icon: Pencil, tone: 'bg-primary/10 text-primary', label: null };

// Mirrors the shape components/timelines-activity-feed.tsx expects (see
// OrderMessageLogModal's toTimelineEntry) -- account-audit actions get
// their own icon/tone map since they're a different vocabulary than
// order-activity actions, but reuse the same Timeline shell.
function toTimelineEntry(entry, index) {
    const style = ACTIVITY_STYLE[entry.action] ?? DEFAULT_ACTIVITY_STYLE;

    return {
        key: `${entry.created_at ?? 'unknown'}-${index}`,
        createdAt: entry.created_at,
        icon: style.Icon,
        tone: style.tone,
        render: () => (
            <div>
                <p className="text-sm font-medium text-foreground">{style.label ?? entry.action}</p>
                {entry.details && <p className="mt-0.5 text-sm text-muted-foreground">{entry.details}</p>}
            </div>
        ),
    };
}

function initialsFor(fullName) {
    const initials = fullName
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return initials || '?';
}

export default function Show({ user, stats, activity }) {
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

                <section className="rounded-xl border border-border bg-card">
                    <div className="border-b border-border px-5 py-4 sm:px-6">
                        <h2 className="type-section-heading text-foreground">Recent account activity</h2>
                        <p className="mt-1 text-sm text-muted-foreground">Changes and security events on your account.</p>
                    </div>
                    <div className="p-5 sm:p-6">
                        <Timeline
                            entries={activity.map(toTimelineEntry)}
                            emptyTitle="No recent activity"
                            emptyDescription="Changes to your account and security events will appear here."
                        />
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
