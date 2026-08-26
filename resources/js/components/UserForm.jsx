import { Input } from '@/components/motion/input';

const ROLE_LABELS = { employee: 'Employee', customer: 'Customer', admin: 'Admin' };

export default function UserForm({ data, setData, errors, clearErrors, allowAdminCreation, customers, isEdit, isSelf, editingUserId }) {
    const roleOptions = allowAdminCreation ? ['employee', 'customer', 'admin'] : ['employee', 'customer'];
    const updateField = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    return (
        <>
            <div>
                <Input
                    label="Full Name"
                    type="text"
                    required
                    value={data.full_name}
                    onChange={(value) => updateField('full_name', value)}
                    error={errors.full_name}
                />
            </div>

            <div>
                <Input
                    label="Email"
                    type="email"
                    required
                    value={data.email}
                    onChange={(value) => updateField('email', value)}
                    error={errors.email}
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
                    onChange={(value) => updateField('password', value)}
                    error={errors.password}
                />
                <p className="mt-1 text-xs text-gray-500">At least 12 characters.</p>
            </div>

            <div>
                <Input
                    label="Confirm Password"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(value) => updateField('password_confirmation', value)}
                    error={errors.password_confirmation}
                />
            </div>

            <div>
                <label htmlFor="account-type" className="block text-sm font-medium text-gray-700">Account Type</label>
                <select
                    id="account-type"
                    value={data.role}
                    onChange={(e) => updateField('role', e.target.value)}
                    disabled={isSelf}
                    aria-invalid={Boolean(errors.role) || undefined}
                    aria-describedby={errors.role ? 'account-type-error' : undefined}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100"
                >
                    {roleOptions.map((role) => (
                        <option key={role} value={role}>
                            {ROLE_LABELS[role]}
                        </option>
                    ))}
                </select>
                {errors.role && (
                    <p id="account-type-error" role="alert" className="mt-1 text-xs text-red-600">
                        {errors.role}
                    </p>
                )}
                {isSelf && <p className="mt-1 text-xs text-gray-500">You cannot change your own account type.</p>}
            </div>

            {data.role === 'customer' && (
                <div>
                    <label htmlFor="linked-customer" className="block text-sm font-medium text-gray-700">Linked Customer</label>
                    <select
                        id="linked-customer"
                        value={data.customer_id}
                        onChange={(e) => updateField('customer_id', e.target.value)}
                        aria-invalid={Boolean(errors.customer_id) || undefined}
                        aria-describedby={errors.customer_id ? 'linked-customer-error' : undefined}
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
                    {errors.customer_id && (
                        <p id="linked-customer-error" role="alert" className="mt-1 text-xs text-red-600">
                            {errors.customer_id}
                        </p>
                    )}
                </div>
            )}

            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    disabled={isSelf}
                    onChange={(e) => updateField('is_active', e.target.checked)}
                    className="disabled:opacity-50"
                />
                Active
                {isSelf && <span className="text-xs text-gray-500">(you cannot deactivate your own account)</span>}
            </label>
        </>
    );
}
