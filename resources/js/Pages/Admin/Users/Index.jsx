import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AccountsPanel } from '@/components/AccountsPanel';
import { UserModal } from '@/components/CreateUserModal';
import { ResetPasswordModal } from '@/components/ResetPasswordModal';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ users, filters, roleLabels, accountForm }) {
    const [editingUser, setEditingUser] = useState(null);
    const [resettingUser, setResettingUser] = useState(null);

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Accounts</h2>}
        >
            <Head title="Accounts" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <AccountsPanel
                    users={users}
                    filters={filters}
                    roleLabels={roleLabels}
                    filterRouteName="admin.users.index"
                    onEdit={setEditingUser}
                    onResetPassword={setResettingUser}
                />
            </div>

            <UserModal
                key={editingUser?.id ?? 'edit'}
                open={editingUser !== null}
                onOpenChange={(open) => !open && setEditingUser(null)}
                user={editingUser}
                allowAdminCreation={accountForm?.allowAdminCreation}
                customers={accountForm?.customers}
            />

            <ResetPasswordModal
                key={resettingUser?.id ?? 'reset-password'}
                open={resettingUser !== null}
                onOpenChange={(open) => !open && setResettingUser(null)}
                user={resettingUser}
            />
        </AuthenticatedLayout>
    );
}
