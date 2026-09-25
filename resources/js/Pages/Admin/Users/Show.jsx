import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AccessFields, AccountFields } from '@/components/UserForm';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Show({ account, customers, roleLabels, activity }) {
    const { data, setData, put, processing, errors, clearErrors } = useForm({
        full_name: account.full_name,
        email: account.email,
        phone: account.phone ?? '',
        role: account.role,
        customer_id: account.linked_customer_id ?? '',
        is_active: account.is_active,
    });

    const updateField = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const submit = (event) => {
        event.preventDefault();
        put(route('admin.users.update', account.public_id), {
            preserveScroll: true,
            transform: (values) => ({ ...values, is_active: values.is_active ? '1' : '0' }),
        });
    };

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
                    <p className="mt-1 text-sm text-muted-foreground">
                        {roleLabels[account.role] ?? account.role}
                        {account.linked_customer_name ? ` · ${account.linked_customer_name}` : ''}
                    </p>
                </nav>
            }
        >
            <Head title={account.full_name} />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-2xl space-y-6">
                    <form onSubmit={submit} className="space-y-5 rounded-xl border border-border bg-card p-6">
                        <div>
                            <h2 className="type-section-heading text-foreground">Profile</h2>
                            <p className="mt-1 text-sm text-muted-foreground">Account contact details.</p>
                        </div>
                        <AccountFields data={data} updateField={updateField} errors={errors} />

                        <div className="border-t border-border pt-5">
                            <h2 className="type-section-heading text-foreground">Access</h2>
                            <p className="mt-1 text-sm text-muted-foreground">Role, linked customer, and account status.</p>
                        </div>
                        <AccessFields
                            data={data}
                            updateField={updateField}
                            errors={errors}
                            allowCustomerRole
                            customers={customers}
                            isSelf={account.is_self}
                            editingUserId={account.id}
                            showActiveControl
                        />

                        <div className="flex justify-end gap-2 border-t border-border pt-5">
                            <Button type="submit" variant="primary" loading={processing}>
                                Save changes
                            </Button>
                        </div>
                    </form>

                    {activity.length > 0 && (
                        <div className="rounded-xl border border-border bg-card p-6">
                            <h2 className="type-section-heading text-foreground">Recent activity</h2>
                            <ul className="mt-4 space-y-3">
                                {activity.map((entry) => (
                                    <li key={entry.id} className="text-sm">
                                        <p className="text-foreground">
                                            <span className="font-medium">{entry.actor_name ?? 'System'}</span>{' '}
                                            {entry.action}
                                            {entry.details ? ` — ${entry.details}` : ''}
                                        </p>
                                        <p className="text-muted-foreground">{formatDateTime(entry.created_at)}</p>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
