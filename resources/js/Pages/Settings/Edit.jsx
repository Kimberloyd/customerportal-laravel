import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FlashBanner from '@/components/FlashBanner';
import { Pagination } from '@/components/interior/pagination';
import { Input } from '@/components/motion/input';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import axios from 'axios';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const FIELD_CLASS_NAMES = { field: 'h-10 rounded-md', input: 'text-sm' };

// One page fits exactly, so the table paginates rather than scrolling. Must
// match SettingsController::SMS_PAGE_SIZE.
const MESSAGE_PAGE_SIZE = 6;
const MESSAGE_ROW_HEIGHT = 52;
const MESSAGE_TABLE_HEIGHT = MESSAGE_PAGE_SIZE * MESSAGE_ROW_HEIGHT + 20;

/**
 * Section heading: the bold title with a muted line under it that opens each
 * block of settings.
 */
function SectionHeading({ title, description }) {
    return (
        <div className="pb-5">
            <h2 className="type-section-heading text-foreground">{title}</h2>
            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

/**
 * One settings row: label and its explanation on the left, the control(s) on
 * the right, divided from the row above by a hairline. Collapses to a single
 * stacked column below md.
 */
function Row({ label, description, children }) {
    return (
        <div className="grid gap-3 border-t border-border py-5 md:grid-cols-3 md:gap-8">
            <div>
                <p className="text-sm font-medium text-foreground">{label}</p>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                )}
            </div>
            <div className="md:col-span-2 md:max-w-xl">{children}</div>
        </div>
    );
}

function Tabs({ tabs, active, onChange }) {
    return (
        <div className="mt-6 flex gap-1 overflow-x-auto border-b border-border">
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    onClick={() => onChange(tab.key)}
                    aria-current={active === tab.key ? 'page' : undefined}
                    className={`-mb-px shrink-0 border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                        active === tab.key
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    }`}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}

const DATE_TIME = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatWhen(value) {
    if (!value) return '—';

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? value : DATE_TIME.format(parsed);
}

function formatNumber(value) {
    return typeof value === 'number' ? value.toLocaleString() : value ?? '—';
}

function SkeletonLines({ rows = 3 }) {
    return (
        <div className="animate-pulse space-y-2" aria-hidden="true">
            {Array.from({ length: rows }, (_, index) => (
                <div key={index} className="h-4 rounded bg-muted" />
            ))}
        </div>
    );
}

function Unavailable({ children }) {
    return <p className="text-sm text-muted-foreground">{children}</p>;
}

/**
 * Live account usage read from Semaphore. Every block is independently
 * nullable, so one endpoint being down (or rate limited) greys out just that
 * block instead of the whole panel.
 */
function SemaphoreUsage({ semaphore }) {
    // Absent until the deferred request lands.
    const loading = semaphore === undefined;
    const account = semaphore?.account ?? null;
    const page = semaphore?.page ?? 1;
    const hasMore = semaphore?.has_more ?? false;
    // Semaphore has no total-page field. The server requests one additional
    // row, so this shows exactly one next page only when one is available.
    const pageCount = Math.max(1, page + (hasMore ? 1 : 0));

    // Only the deferred prop is re-requested, so the form above keeps its state
    // and the page does not jump back to the top on a page change.
    const goToPage = (next) => {
        if (next < 1) return;

        router.get(
            route('settings.edit'),
            { sms_page: next },
            {
                only: ['semaphore'],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    // Semaphore has no guaranteed unique key across every response shape, so a
    // stable row id is derived once here rather than in getRowId, which the
    // table calls on every render.
    const messages = useMemo(() => {
        if (!semaphore?.messages) return semaphore?.messages ?? null;

        return semaphore.messages.map((entry, index) => ({
            ...entry,
            rowId: String(entry.message_id ?? `${entry.recipient ?? ''}-${entry.created_at ?? index}`),
        }));
    }, [semaphore]);

    const messageColumns = useMemo(
        () => [
            {
                key: 'recipient',
                header: 'Recipient',
                cell: (entry) => (
                    <span className="tabular-nums font-medium text-foreground">
                        {entry.recipient ?? '—'}
                    </span>
                ),
            },
            {
                key: 'message',
                header: 'Message',
                cell: (entry) => (
                    <span className="line-clamp-2 text-muted-foreground">{entry.message ?? '—'}</span>
                ),
            },
            { key: 'status', header: 'Status', cell: (entry) => entry.status ?? '—' },
            {
                key: 'created_at',
                header: 'Sent',
                cell: (entry) => (
                    <span className="tabular-nums">{formatWhen(entry.created_at)}</span>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Row
                label="Credits remaining"
                description="Each text costs one credit per 160 characters."
            >
                {loading ? (
                    <SkeletonLines rows={2} />
                ) : account ? (
                    <div>
                        <p className="text-3xl font-semibold text-foreground">
                            {formatNumber(account.credit_balance)}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {account.account_name ?? 'Semaphore account'}
                            {account.status ? ` · ${account.status}` : ''}
                        </p>
                    </div>
                ) : (
                    <Unavailable>
                        Semaphore did not return a balance. It may be rate limited — this
                        panel refreshes about once a minute.
                    </Unavailable>
                )}
            </Row>

            {/* Full width rather than inside a Row -- four columns do not fit
                the Row grid's max-w-xl content column. */}
            <div className="border-t border-border py-5">
                <p className="text-sm font-medium text-foreground">Recent messages</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    The latest texts sent through this account.
                </p>
                <div className="mt-4">
                    {messages === null && !loading ? (
                        <Unavailable>Message history is unavailable right now.</Unavailable>
                    ) : (
                        <>
                            <Table
                                data={messages ?? []}
                                columns={messageColumns}
                                getRowId={(entry) => entry.rowId}
                                className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                                loading={loading}
                                rowHeight={MESSAGE_ROW_HEIGHT}
                                height={MESSAGE_TABLE_HEIGHT}
                                emptyState="No texts have been sent from this account yet."
                            />

                            <div className="mt-4 flex justify-end">
                                {pageCount > 1 && (
                                    <Pagination
                                        count={pageCount}
                                        page={page}
                                        onPageChange={goToPage}
                                        label="Recent messages pagination"
                                    />
                                )}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

/**
 * Runtime kill switch for outbound order SMS. Admin-only -- the server gates
 * this too (SettingsController::updateSms), the `sms` prop is simply absent for
 * everyone else.
 */
function NotificationsSection({ sms, semaphore, onSaved }) {
    // Held locally and flipped before the request goes out, so the switch
    // animates under the cursor instead of waiting on the round-trip. The save
    // then happens in the background; only a failure moves it back.
    const [enabled, setEnabled] = useState(sms.enabled);
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState('');
    const canSend = enabled && sms.configured;

    const toggle = async () => {
        if (saving) return;

        const next = !enabled;

        setEnabled(next);
        setSaveError('');
        setSaving(true);

        try {
            const response = await axios.put(
                route('settings.sms.update'),
                { enabled: next },
                { headers: { Accept: 'application/json' } },
            );
            setEnabled(response.data.enabled === true);
            onSaved(response.data.enabled === true);
        } catch {
            setEnabled(! next);
            setSaveError('Could not update order texts. Check your connection and try again.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <section className="mt-8 rounded-xl border border-border bg-card p-5 sm:p-6">
            <SectionHeading
                title="Order SMS"
                description="Choose whether customers receive text updates about their orders."
            />

            <Row
                label="Send order texts"
                description="Send a text when an order is submitted, updated, completed, cancelled, or received."
            >
                <div className="flex flex-col gap-2">
                    <Switch
                        label={enabled ? 'Order texts enabled' : 'Order texts paused'}
                        checked={enabled}
                        onToggle={toggle}
                        disabled={saving}
                    />
                    <p className="text-sm text-muted-foreground">
                        {canSend
                            ? 'Customers will receive text updates for their orders.'
                            : enabled
                                ? 'Order texts are enabled, but setup is still required.'
                                : 'No order texts will be sent while this setting is paused.'}
                    </p>
                    {saveError && (
                        <p className="text-sm text-red-600" role="alert">
                            {saveError}
                        </p>
                    )}
                </div>
            </Row>

            {sms.configured ? (
                <SemaphoreUsage semaphore={semaphore} />
            ) : (
                <div className="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                    <p className="font-medium">SMS is not configured</p>
                    <p className="mt-1">
                        Add <code className="font-medium">SEMAPHORE_API_KEY</code> to this environment and restart the app. Until then, customers will not receive order texts.
                    </p>
                </div>
            )}
        </section>
    );
}

export default function Edit({ user, sms, semaphore }) {
    const { data, setData, put, processing, errors, clearErrors } = useForm({
        full_name: user.full_name,
        phone: user.phone ?? '',
    });

    // Only admins get a second tab, so everyone else sees the sections bare
    // rather than a lone tab that looks like a broken nav.
    const tabs = [
        { key: 'details', label: 'My details' },
        ...(sms ? [{ key: 'notifications', label: 'Semaphore SMS Integration' }] : []),
    ];
    const [active, setActive] = useState('details');
    const [smsFlash, setSmsFlash] = useState(null);

    const showSmsSaved = (enabled) => {
        setSmsFlash({
            id: Date.now(),
            message: enabled ? 'Order texts are now enabled.' : 'Order texts are now paused.',
        });
    };

    const updateField = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('settings.update'));
    };

    return (
        <AuthenticatedLayout
            banner={smsFlash && (
                <FlashBanner
                    key={smsFlash.id}
                    message={smsFlash.message}
                    variant="success"
                    autoDismiss
                />
            )}
        >
            <Head title="Settings" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        Settings
                    </h1>
                    <p className="mt-1 text-base text-muted-foreground">
                        Manage your account settings and preferences.
                    </p>
                </header>

                {tabs.length > 1 && (
                    <Tabs tabs={tabs} active={active} onChange={setActive} />
                )}

                {active === 'details' && (
                    <form onSubmit={submit} className="mt-8 rounded-xl border border-border bg-card p-5 sm:p-6">
                        <SectionHeading
                            title="My details"
                            description="Update your name and the number we reach you on."
                        />

                        <Row
                            label="Email"
                            description={`${user.role_label} account. Contact an administrator to change your email or password.`}
                        >
                            <p className="text-sm text-foreground">{user.email}</p>
                        </Row>

                        <Row label="Full name" description="The name shown across the portal.">
                            <Input
                                label="Full Name"
                                type="text"
                                required
                                autoComplete="off"
                                value={data.full_name}
                                onChange={(value) => updateField('full_name', value)}
                                error={errors.full_name}
                                classNames={FIELD_CLASS_NAMES}
                            />
                        </Row>

                        <Row
                            label="Phone number"
                            description="Used for order text messages when they're turned on."
                        >
                            <Input
                                label="Phone Number"
                                type="tel"
                                autoComplete="off"
                                value={data.phone}
                                onChange={(value) => updateField('phone', value)}
                                error={errors.phone}
                                classNames={FIELD_CLASS_NAMES}
                            />
                        </Row>

                        <div className="flex justify-end border-t border-border pt-5">
                            <Button type="submit" variant="primary" disabled={processing}>
                                Save Changes
                            </Button>
                        </div>
                    </form>
                )}

                {active === 'notifications' && sms && (
                    <NotificationsSection sms={sms} semaphore={semaphore} onSaved={showSmsSaved} />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
