import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AccountsPanel } from '@/components/AccountsPanel';
import { Head } from '@inertiajs/react';

export default function Index({ users, filters, roleLabels }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Users</h2>}
        >
            <Head title="Users" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <AccountsPanel
                    users={users}
                    filters={filters}
                    roleLabels={roleLabels}
                    filterRouteName="admin.users.index"
                />
            </div>
        </AuthenticatedLayout>
    );
}
