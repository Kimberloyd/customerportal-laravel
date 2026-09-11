import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FlashBanner from '@/components/FlashBanner';
import { Pagination } from '@/components/interior/pagination';
import { Input } from '@/components/motion/input';
import { Table } from '@/components/motion/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/motion/tabs';
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
function Row({ label, description, children, first = false }) {
    return (
        <div className={`-mx-5 grid gap-3 px-5 py-5 sm:-mx-6 sm:px-6 md:grid-cols-3 md:gap-8 ${first ? '' : 'border-t border-border'}`}>
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
                width: '420px',
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
            <div className="-mx-5 border-t border-border px-5 py-5 sm:-mx-6 sm:px-6">
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
function ReminderSettings({ reminders, smsConfigured, onSaved }) {
    const [form, setForm] = useState(reminders);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const labels = {
        awaiting_fulfillment: 'Waiting for first delivery',
        stalled_partial: 'Partial delivery has stopped moving',
        awaiting_customer_close: 'Customer has not closed a fully delivered order',
        return_review: 'Return is waiting for staff review',
        return_receipt: 'Approved return has not been received',
    };

    const setThreshold = (kind, level, value) => setForm((current) => ({
        ...current,
        thresholds: {
            ...current.thresholds,
            [kind]: { ...current.thresholds[kind], [level]: Number(value) },
        },
    }));

    const save = async () => {
        if (saving) return;
        setSaving(true);
        setError('');
        try {
            const response = await axios.put(route('settings.reminders.update'), form, {
                headers: { Accept: 'application/json' },
            });
            setForm(response.data);
            onSaved(response.data.enabled ? 'Automatic reminders are now enabled.' : 'Automatic reminders are now paused.');
        } catch (requestError) {
            const errors = requestError.response?.data?.errors;
            setError(errors ? Object.values(errors).flat()[0] : 'Could not save reminder settings. Check your connection and try again.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="mt-8">
            <SectionHeading title="Reminders and escalation" description="Follow up automatically when an order or return is waiting too long." />
            <div className="rounded-xl border border-border bg-card px-5 pb-5 pt-0 sm:px-6 sm:pb-6">
                <Row first label="Automatic reminders" description="Pause this to stop both portal reminders and reminder texts. Existing history is kept.">
                    <Switch label={form.enabled ? 'Automatic reminders enabled' : 'Automatic reminders paused'} checked={form.enabled} onToggle={() => setForm({ ...form, enabled: !form.enabled })} disabled={saving} />
                </Row>
                <Row label="Customer reminder texts" description="Portal reminders continue even when reminder texts are off.">
                    <div className="space-y-2">
                        <Switch label={form.customer_sms_enabled ? 'Reminder texts enabled' : 'Reminder texts paused'} checked={form.customer_sms_enabled} onToggle={() => setForm({ ...form, customer_sms_enabled: !form.customer_sms_enabled })} disabled={saving || !smsConfigured} />
                        {!smsConfigured && <p className="text-sm text-muted-foreground">Configure Semaphore before enabling reminder texts.</p>}
                    </div>
                </Row>
                <Row label="SMS quiet hours" description={`Customer reminder texts use ${form.timezone}.`}>
                    <div className="flex flex-wrap items-center gap-3">
                        <label className="text-sm text-muted-foreground">From <input aria-label="Quiet hours start" type="number" min="0" max="23" value={form.quiet_hours_start} onChange={(event) => setForm({ ...form, quiet_hours_start: Number(event.target.value) })} className="ml-2 w-20 rounded-md border-border text-sm" /></label>
                        <label className="text-sm text-muted-foreground">until <input aria-label="Quiet hours end" type="number" min="0" max="23" value={form.quiet_hours_end} onChange={(event) => setForm({ ...form, quiet_hours_end: Number(event.target.value) })} className="ml-2 w-20 rounded-md border-border text-sm" /></label>
                    </div>
                </Row>
                <div className="-mx-5 border-t border-border px-5 py-5 sm:-mx-6 sm:px-6">
                <p className="text-sm font-medium text-foreground">Timing in hours</p>
                <p className="mt-1 text-sm text-muted-foreground">Escalation must occur after the earlier reminder.</p>
                <div className="mt-4 space-y-4">
                    {Object.entries(form.thresholds).map(([kind, levels]) => (
                        <div key={kind} className="grid gap-3 rounded-lg bg-muted/40 p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                            <p className="text-sm font-medium text-foreground">{labels[kind]}</p>
                            <div className="flex flex-wrap gap-3">
                                {Object.entries(levels).map(([level, hours]) => (
                                    <label key={level} className="text-xs capitalize text-muted-foreground">{level}<input aria-label={`${labels[kind]} ${level} hours`} type="number" min="1" max="720" value={hours} onChange={(event) => setThreshold(kind, level, event.target.value)} className="ml-2 w-20 rounded-md border-border text-sm" /></label>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
                <div className="-mx-5 border-t border-border px-5 py-5 text-sm text-muted-foreground sm:-mx-6 sm:px-6">
                    <p>Last scheduler run: {formatWhen(form.last_scheduler_run)}</p>
                    {form.last_failure && <p className="mt-1 text-amber-700">Most recent issue: {form.last_failure}</p>}
                </div>
                {error && <p role="alert" className="mb-4 text-sm text-red-600">{error}</p>}
                <div className="-mx-5 flex justify-end border-t border-border px-5 pt-5 sm:-mx-6 sm:px-6"><Button type="button" variant="primary" className="rounded-md" disabled={saving} onClick={save}>{saving ? 'Saving...' : 'Save reminder settings'}</Button></div>
            </div>
        </div>
    );
}

function NotificationsSection({ sms, semaphore, reminders, onSaved }) {
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
        <>
        <section className="mt-8">
            <SectionHeading
                title="Order SMS"
                description="Choose whether customers receive text updates about their orders."
            />

            <div className="rounded-xl border border-border bg-card px-5 pb-5 pt-0 sm:px-6 sm:pb-6">
                <Row
                    first
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
            </div>
        </section>
        <ReminderSettings reminders={reminders} smsConfigured={sms.configured} onSaved={onSaved} />
        </>
    );
}

function TwoFactorSection({ twoFactor }) {
    const confirm = useForm({ code: '' });
    const manage = useForm({ code: '' });

    const beginSetup = () => {
        router.post(route('settings.two-factor.setup'), {}, { preserveScroll: true });
    };

    const cancelSetup = () => {
        router.delete(route('settings.two-factor.cancel'), { preserveScroll: true });
    };

    const confirmSetup = (event) => {
        event.preventDefault();
        confirm.post(route('settings.two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirm.reset(),
        });
    };

    const regenerateRecoveryCodes = () => {
        manage.post(route('settings.two-factor.recovery-codes'), {
            preserveScroll: true,
            onSuccess: () => manage.reset(),
        });
    };

    const disable = () => {
        manage.delete(route('settings.two-factor.disable'), {
            preserveScroll: true,
            onSuccess: () => manage.reset(),
        });
    };

    return (
        <section className="mt-8">
            <SectionHeading
                title="Two-factor authentication"
                description="Require a code from your authenticator app after entering your password."
            />

            <div className="rounded-xl border border-border bg-card px-5 pb-5 pt-0 sm:px-6 sm:pb-6">
                {twoFactor.recovery_codes && (
                <div className="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950" role="status">
                    <p className="text-sm font-semibold">Save these recovery codes now</p>
                    <p className="mt-1 text-sm">Each code works once. They will not be shown again.</p>
                    <div className="mt-3 grid gap-2 font-mono text-sm sm:grid-cols-2">
                        {twoFactor.recovery_codes.map((code) => (
                            <code key={code} className="rounded border border-amber-200 bg-white px-3 py-2">{code}</code>
                        ))}
                    </div>
                </div>
                )}

            {!twoFactor.enabled && !twoFactor.setup_secret && (
                <Row
                    first={!twoFactor.recovery_codes}
                    label="Authenticator app"
                    description="Use Google Authenticator, Microsoft Authenticator, 1Password, or another TOTP app."
                >
                    <Button type="button" variant="primary" className="rounded-md" onClick={beginSetup}>Set up authenticator</Button>
                </Row>
            )}

            {!twoFactor.enabled && twoFactor.setup_secret && (
                <form onSubmit={confirmSetup}>
                    <Row
                        first={!twoFactor.recovery_codes}
                        label="Setup key"
                        description="Add an account manually in your authenticator app, then enter its current code."
                    >
                        <code className="block break-all rounded-md border border-border bg-muted px-3 py-2 font-mono text-sm text-foreground">
                            {twoFactor.setup_secret}
                        </code>
                        <p className="mt-2 text-sm text-muted-foreground">Type: time based · 6 digits · 30-second period</p>
                    </Row>
                    <Row label="Confirm setup" description="Enter the current six-digit code from the app.">
                        <Input
                            label="Authentication code"
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={confirm.data.code}
                            onChange={(value) => confirm.setData('code', value)}
                            error={confirm.errors.code}
                            classNames={FIELD_CLASS_NAMES}
                        />
                    </Row>
                    <div className="-mx-5 flex justify-end gap-3 border-t border-border px-5 pt-5 sm:-mx-6 sm:px-6">
                        <Button type="button" variant="tertiary" className="rounded-md" onClick={cancelSetup}>Cancel</Button>
                        <Button type="submit" variant="primary" className="rounded-md" disabled={confirm.processing}>Confirm and enable</Button>
                    </div>
                </form>
            )}

            {twoFactor.enabled && (
                <>
                    <Row
                        first={!twoFactor.recovery_codes}
                        label="Status"
                        description="Your password alone can no longer sign in to this account."
                    >
                        <p className="text-sm font-medium text-emerald-700">Enabled</p>
                    </Row>
                    <Row
                        label="Manage two-factor authentication"
                        description="Enter your current authenticator code or an unused recovery code before changing this protection."
                    >
                        <Input
                            label="Authentication or recovery code"
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={manage.data.code}
                            onChange={(value) => manage.setData('code', value)}
                            error={manage.errors.code}
                            classNames={FIELD_CLASS_NAMES}
                        />
                        <div className="mt-3 flex flex-wrap gap-3">
                            <Button type="button" variant="tertiary" className="rounded-md" disabled={manage.processing} onClick={regenerateRecoveryCodes}>
                                Generate new recovery codes
                            </Button>
                            <Button type="button" variant="destructive" className="rounded-md" disabled={manage.processing} onClick={disable}>
                                Disable two-factor authentication
                            </Button>
                        </div>
                    </Row>
                </>
                )}
            </div>
        </section>
    );
}

export default function Edit({ user, sms, semaphore, reminders, two_factor: twoFactor }) {
    const { data, setData, put, processing, errors, clearErrors } = useForm({
        full_name: user.full_name,
        phone: user.phone ?? '',
    });

    // Only admins get a second tab, so everyone else sees the sections bare
    // rather than a lone tab that looks like a broken nav.
    const tabs = [
        { key: 'details', label: 'My details' },
        { key: 'security', label: 'Security' },
        ...(sms ? [{ key: 'notifications', label: 'Notifications' }] : []),
    ];
    const [active, setActive] = useState('details');
    const [smsFlash, setSmsFlash] = useState(null);

    const showSmsSaved = (enabledOrMessage) => {
        setSmsFlash({
            id: Date.now(),
            message: typeof enabledOrMessage === 'string' ? enabledOrMessage : enabledOrMessage ? 'Order texts are now enabled.' : 'Order texts are now paused.',
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
            header={
                <div>
                    <h1 className="text-xl font-semibold leading-tight text-gray-800">
                        Settings
                    </h1>
                </div>
            }
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
                {tabs.length > 1 && (
                    <Tabs
                        value={active}
                        onValueChange={setActive}
                        variant="underline"
                    >
                        <TabsList className="flex w-full flex-wrap justify-start">
                            {tabs.map((tab) => (
                                <TabsTrigger key={tab.key} value={tab.key}>
                                    {tab.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                )}

                {active === 'details' && (
                    <div className="mt-8">
                        <SectionHeading
                            title="My details"
                            description="Update your name and the number we reach you on."
                        />

                        <form onSubmit={submit} className="rounded-xl border border-border bg-card px-5 pb-5 pt-0 sm:px-6 sm:pb-6">
                            <Row
                                first
                                label="Email"
                                description={`${user.role_label} account. Contact an administrator to change your email or password.`}
                            >
                                <Input
                                    label="Email"
                                    type="email"
                                    value={user.email}
                                    disabled
                                    classNames={FIELD_CLASS_NAMES}
                                />
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

                            <div className="-mx-5 flex justify-end border-t border-border px-5 pt-5 sm:-mx-6 sm:px-6">
                                <Button type="submit" variant="primary" className="rounded-md" disabled={processing}>
                                    Save Changes
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {active === 'notifications' && sms && (
                    <NotificationsSection sms={sms} semaphore={semaphore} reminders={reminders} onSaved={showSmsSaved} />
                )}

                {active === 'security' && <TwoFactorSection twoFactor={twoFactor} />}
            </div>
        </AuthenticatedLayout>
    );
}
