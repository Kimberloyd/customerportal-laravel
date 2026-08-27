import { Modal } from '@/components/interior/modal';
import Stepper, { Step } from '@/components/Stepper';
import { AccessFields, AccountFields, SecurityFields } from '@/components/UserForm';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

// Which fields live on which step -- used to figure out where to send the
// user back to if the server rejects a field that isn't on the final step.
const CREATE_STEP_FIELDS = [
    ['full_name', 'email', 'phone'],
    ['password', 'password_confirmation'],
    ['role', 'customer_id'],
];
const EDIT_STEP_FIELDS = [
    ['full_name', 'email', 'phone'],
    ['role', 'customer_id'],
];

const userFormValues = (user) => ({
    full_name: user?.full_name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
    password: '',
    password_confirmation: '',
    role: user?.role ?? 'employee',
    customer_id: user?.linked_customer_id ?? '',
    is_active: user?.is_active ?? true,
});

export function UserModal({ open, onOpenChange, user = null, allowAdminCreation = false, customers = [] }) {
    const isEdit = user !== null;
    const stepFields = isEdit ? EDIT_STEP_FIELDS : CREATE_STEP_FIELDS;
    const totalSteps = stepFields.length;
    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        clearErrors,
        reset,
        transform,
    } = useForm(userFormValues(user));
    const stepperRef = useRef(null);
    // Bumped to force the Stepper to remount (and re-read initialStep) after
    // a failed submit, since it only manages its own step position -- there's
    // no other way to drop the user back onto the step with the error.
    const [stepperKey, setStepperKey] = useState(0);
    // Mirrors the Stepper's own currentStep so the buttons living in the
    // Modal's real footer (outside the Stepper) know what to show/do.
    const [currentStep, setCurrentStep] = useState(1);

    const updateField = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const resetAndClose = () => {
        reset();
        clearErrors();
        setCurrentStep(1);
        setStepperKey((key) => key + 1);
        onOpenChange(false);
    };

    const close = () => {
        if (!processing) resetAndClose();
    };

    const submit = () => {
        if (processing) return;
        transform((values) => ({
            ...values,
            is_active: values.is_active ? '1' : '0',
        }));
        const submitRoute = isEdit
            ? route('admin.users.update', user.id)
            : route('admin.users.store');

        const options = {
            preserveScroll: true,
            onSuccess: resetAndClose,
            onError: (serverErrors) => {
                let step = 1;
                for (let i = 0; i < stepFields.length; i++) {
                    if (stepFields[i].some((field) => serverErrors[field])) {
                        step = i + 1;
                        break;
                    }
                }
                setCurrentStep(step);
                setStepperKey((key) => key + 1);
            },
        };

        if (isEdit) {
            put(submitRoute, options);
            return;
        }

        post(submitRoute, options);
    };

    return (
        <Modal
            open={open}
            onClose={close}
            title={isEdit ? `Edit ${user.full_name}` : 'Add account'}
            description={
                isEdit
                    ? "Update this account's profile and access."
                    : 'Create a portal account and choose what the account holder can access.'
            }
            maxWidth={640}
            maxHeight="90vh"
            closeOnBackdrop={!processing}
            closeOnEscape={!processing}
            className="[&>div:first-child]:px-6 [&>div:first-child]:pb-5 [&>div:first-child]:pt-6 [&>div:first-child_h2]:!text-lg [&>div:first-child_p]:!mt-3 [&>div:first-child_p]:!text-sm [&>div:last-child]:px-6 [&>div:last-child]:py-5"
            footer={
                <>
                    <Button
                        type="button"
                        variant="tertiary"
                        className="h-10 rounded-md px-5 text-sm"
                        onClick={currentStep > 1 ? () => stepperRef.current?.back() : close}
                        disabled={processing}
                    >
                        {currentStep > 1 ? 'Back' : 'Cancel'}
                    </Button>
                    <Button
                        type="button"
                        variant="primary"
                        className="h-10 rounded-md px-5 text-sm"
                        onClick={() => (currentStep === totalSteps ? stepperRef.current?.complete() : stepperRef.current?.next())}
                        loading={processing}
                    >
                        {currentStep === totalSteps
                            ? (isEdit ? 'Save changes' : 'Create account')
                            : 'Continue'}
                    </Button>
                </>
            }
        >
            <div>
                <Stepper
                    ref={stepperRef}
                    key={stepperKey}
                    initialStep={currentStep}
                    onStepChange={setCurrentStep}
                    onFinalStepCompleted={submit}
                    stepLabels={isEdit ? ['Profile', 'Access'] : ['Profile', 'Security', 'Access']}
                    hideDefaultFooter
                >
                    <Step>
                        <AccountFields data={data} updateField={updateField} errors={errors} />
                    </Step>
                    {!isEdit && (
                        <Step>
                            <SecurityFields data={data} updateField={updateField} errors={errors} isEdit={false} />
                        </Step>
                    )}
                    <Step>
                        <AccessFields
                            data={data}
                            updateField={updateField}
                            errors={errors}
                            allowAdminCreation={allowAdminCreation || user?.role === 'admin'}
                            customers={customers}
                            isSelf={Boolean(user?.is_self)}
                            editingUserId={user?.id}
                        />
                    </Step>
                </Stepper>
            </div>
        </Modal>
    );
}
