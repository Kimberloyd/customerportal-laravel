import { Input } from '@/components/motion/input';

const ROLE_LABELS = { employee: 'Employee', customer: 'Customer', admin: 'Admin' };

export default function UserForm({ data, setData, errors, allowAdminCreation, customers, isEdit, isSelf, editingUserId }) {
    const roleOptions = allowAdminCreation ? ['employee', 'customer', 'admin'] : ['employee', 'customer'];

    return (
        <>
            {Object.entries(errors).map(([key, message]) => (
                <div key={key} className="rounded-md bg-red-50 p-3 text-sm text-red-700">{message}</div>
            ))}

            <div>
                <Input
                    label="Full Name"
                    type="text"
                    required
                    value={data.full_name}
                    onChange={(value) => setData('full_name', value)}
                />
            </div>

            <div>
                <Input
                    label="Email"
                    type="email"
                    required
                    value={data.email}
                    onChange={(value) => setData('email', value)}
                />
            </div>

            <div>
                {isEdit && (
                    <p className="text-xs text-gray-500">Leave blank to keep the current password.</p>
                )}
                <Input
                    label={isEdit ? 'New Password' : 'Password'}
                    type="password"
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                />
                <p className="mt-1 text-xs text-gray-500">At least 12 characters.</p>
            </div>

            <div>
                <Input
                    label="Confirm Password"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(value) => setData('password_confirmation', value)}
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Account Type</label>
                <select
                    value={data.role}
                    onChange={(e) => setData('role', e.target.value)}
                    disabled={isSelf}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100"
                >
                    {roleOptions.map((role) => (
                        <option key={role} value={role}>
                            {ROLE_LABELS[role]}
                        </option>
                    ))}
                </select>
                {isSelf && <p className="mt-1 text-xs text-gray-500">You cannot change your own account type.</p>}
            </div>

            {data.role === 'customer' && (
                <div>
                    <label className="block text-sm font-medium text-gray-700">Linked Customer</label>
                    <select
                        value={data.customer_id}
                        onChange={(e) => setData('customer_id', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                    >
                        <option value="">Select a customer</option>
                        {customers.map((customer) => (
                            <option key={customer.id} value={customer.id}>
                                {customer.company_name}
                                {customer.user_id && customer.user_id !== editingUserId ? ' (already linked)' : ''}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    disabled={isSelf}
                    onChange={(e) => setData('is_active', e.target.checked)}
                    className="disabled:opacity-50"
                />
                Active
                {isSelf && <span className="text-xs text-gray-500">(you cannot deactivate your own account)</span>}
            </label>
        </>
    );
}
