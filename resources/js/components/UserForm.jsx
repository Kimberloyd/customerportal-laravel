import { Dropdown } from '@/components/interior/dropdown';
import { Input } from '@/components/motion/input';
import { useFieldValidation } from '@/hooks/useFieldValidation';
import { ChevronDown } from 'lucide-react';

const ROLE_LABELS = { admin: 'Admin', office: 'Office', agent: 'Agent', customer: 'Customer' };
const FIELD_CLASS_NAMES = { field: 'h-10 rounded-md', input: 'text-sm' };

const EMAIL_FORMAT = /\S+@\S+\.\S+/;

const accountRules = {
    full_name: (value) => ((value ?? '').trim() ? null : 'Enter a full name.'),
    email: (value) => {
        const trimmed = (value ?? '').trim();
        if (!trimmed) return 'Enter an email address.';
        return EMAIL_FORMAT.test(trimmed) ? null : 'Enter a valid email address.';
    },
};

export function AccountFields({ data, updateField, errors }) {
    const validation = useFieldValidation(accountRules);
    const onFieldChange = (field, value) => {
        updateField(field, value);
        validation.onChange(field, value, data);
    };

    return (
        <>
            <div>
                <Input
                    label="Full Name"
                    type="text"
                    required
                    autoComplete="off"
                    value={data.full_name}
                    onChange={(value) => onFieldChange('full_name', value)}
                    onBlur={() => validation.onBlur('full_name', data.full_name, data)}
                    error={errors.full_name || validation.clientErrors.full_name}
                    success={validation.validFields.full_name && !errors.full_name}
                    classNames={FIELD_CLASS_NAMES}
                />
            </div>

            <div>
                <Input
                    label="Email"
                    type="email"
                    required
                    autoComplete="off"
                    value={data.email}
                    onChange={(value) => onFieldChange('email', value)}
                    onBlur={() => validation.onBlur('email', data.email, data)}
                    error={errors.email || validation.clientErrors.email}
                    success={validation.validFields.email && !errors.email}
                    classNames={FIELD_CLASS_NAMES}
                />
            </div>

            <div>
                <Input
                    label="Phone Number"
                    type="tel"
                    autoComplete="off"
                    value={data.phone}
                    onChange={(value) => updateField('phone', value)}
                    error={errors.phone}
                    classNames={FIELD_CLASS_NAMES}
                />
            </div>
        </>
    );
}

export function SecurityFields({ data, updateField, errors, isEdit, optional = isEdit }) {
    const securityRules = {
        password: (value) => {
            const trimmed = value ?? '';
            if (!trimmed) return optional ? null : 'Enter a password for this account.';
            return trimmed.length >= 8 ? null : 'Use at least 8 characters.';
        },
        password_confirmation: (value, fieldData) => {
            const password = fieldData?.password ?? '';
            const confirmation = value ?? '';
            if (!password && !confirmation) return optional ? null : 'Enter the same password again.';
            return confirmation === password ? null : 'Enter the same password again.';
        },
    };
    const validation = useFieldValidation(securityRules);
    const onFieldChange = (field, value) => {
        updateField(field, value);
        const nextData = { ...data, [field]: value };
        validation.onChange(field, value, nextData);
        // Confirmation depends on password -- if it already showed a
        // mismatch, re-check it live as password changes too, not just
        // when the user types back into the confirmation field itself.
        if (field === 'password') validation.onChange('password_confirmation', data.password_confirmation, nextData);
    };

    return (
        <>
            <div>
                {optional && (
                    <p className="text-sm text-muted-foreground">Leave blank to keep the current password.</p>
                )}
                <Input
                    label={isEdit ? 'New Password' : 'Password'}
                    type="password"
                    autoComplete="new-password"
                    value={data.password}
                    onChange={(value) => onFieldChange('password', value)}
                    onBlur={() => validation.onBlur('password', data.password, data)}
                    error={errors.password || validation.clientErrors.password}
                    success={validation.validFields.password && !errors.password && Boolean(data.password)}
                    classNames={FIELD_CLASS_NAMES}
                />
                <p className="mt-1 text-sm text-muted-foreground">At least 8 characters.</p>
            </div>

            <div>
                <Input
                    label="Confirm Password"
                    type="password"
                    autoComplete="new-password"
                    value={data.password_confirmation}
                    onChange={(value) => onFieldChange('password_confirmation', value)}
                    onBlur={() => validation.onBlur('password_confirmation', data.password_confirmation, data)}
                    success={validation.validFields.password_confirmation && !errors.password_confirmation && Boolean(data.password_confirmation)}
                    error={errors.password_confirmation || validation.clientErrors.password_confirmation}
                    classNames={FIELD_CLASS_NAMES}
                />
            </div>
        </>
    );
}

export function AccessFields({ data, updateField, errors, allowCustomerRole = false, customers, isSelf, editingUserId, showActiveControl = true }) {
    const roleOptions = allowCustomerRole
        ? ['admin', 'office', 'agent', 'customer']
        : ['admin', 'office', 'agent'];
    const roleItems = roleOptions.map((role) => ({ value: role, label: ROLE_LABELS[role] }));
    const selectedRoleLabel = ROLE_LABELS[data.role] ?? 'Choose an account type';
    const customerItems = customers.map((customer) => {
        const linkedToAnotherAccount = customer.user_id && customer.user_id !== editingUserId;

        return {
            value: String(customer.id),
            label: customer.company_name,
            hint: linkedToAnotherAccount ? 'Already linked' : undefined,
            disabled: Boolean(linkedToAnotherAccount),
        };
    });
    const selectedCustomer = customerItems.find((customer) => customer.value === String(data.customer_id ?? ''));
    const dropdownTriggerClassName = 'mt-1 flex h-10 w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 text-left text-sm text-gray-700 outline-none transition-colors hover:border-gray-400 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20 disabled:bg-gray-100 disabled:text-gray-500';

    return (
        <>
            <div>
                <label className="block text-sm font-medium text-gray-700">Account Type</label>
                <Dropdown
                    items={roleItems}
                    value={data.role}
                    onChange={(value) => updateField('role', value)}
                    disabled={isSelf}
                    ariaInvalid={Boolean(errors.role)}
                    ariaDescribedBy={errors.role ? 'account-type-error' : undefined}
                    label="Account type"
                    placeholder="Choose an account type"
                    emptyLabel="No account types available"
                    className="block w-full"
                    triggerClassName={dropdownTriggerClassName}
                    trigger={(
                        <>
                            <span className="truncate">{selectedRoleLabel}</span>
                            <ChevronDown aria-hidden="true" className="h-4 w-4 shrink-0 text-gray-500" />
                        </>
                    )}
                    matchTriggerWidth
                    portal
                />
                {errors.role && (
                    <p id="account-type-error" role="alert" className="mt-1 text-sm text-destructive">
                        {errors.role}
                    </p>
                )}
                {isSelf && <p className="mt-1 text-sm text-muted-foreground">You cannot change your own account type.</p>}
            </div>

            {data.role === 'customer' && (
                <div>
                    <label className="block text-sm font-medium text-gray-700">Linked Customer</label>
                    <Dropdown
                        items={customerItems}
                        value={String(data.customer_id ?? '')}
                        onChange={(value) => updateField('customer_id', value)}
                        ariaInvalid={Boolean(errors.customer_id)}
                        ariaDescribedBy={errors.customer_id ? 'linked-customer-error' : undefined}
                        label="Linked customer"
                        placeholder="Choose a customer"
                        emptyLabel="No customers available"
                        className="block w-full"
                        triggerClassName={dropdownTriggerClassName}
                        trigger={(
                            <>
                                <span className="truncate">{selectedCustomer?.label ?? 'Choose a customer'}</span>
                                <ChevronDown aria-hidden="true" className="h-4 w-4 shrink-0 text-gray-500" />
                            </>
                        )}
                        matchTriggerWidth
                        searchable
                        searchPlaceholder="Search customers"
                        portal
                    />
                    {errors.customer_id && (
                        <p id="linked-customer-error" role="alert" className="mt-1 text-sm text-destructive">
                            {errors.customer_id}
                        </p>
                    )}
                </div>
            )}

            {showActiveControl && (
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        disabled={isSelf}
                        onChange={(e) => updateField('is_active', e.target.checked)}
                        className="disabled:opacity-50"
                    />
                    Active
                    {isSelf && <span className="text-sm text-muted-foreground">(you cannot deactivate your own account)</span>}
                </label>
            )}
        </>
    );
}

export default function UserForm({ data, setData, errors, clearErrors, customers, isEdit, isSelf, editingUserId }) {
    const updateField = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    return (
        <>
            <AccountFields data={data} updateField={updateField} errors={errors} />
            <SecurityFields data={data} updateField={updateField} errors={errors} isEdit={isEdit} />
            <AccessFields
                data={data}
                updateField={updateField}
                errors={errors}
                customers={customers}
                isSelf={isSelf}
                editingUserId={editingUserId}
                showActiveControl={isEdit}
            />
        </>
    );
}
