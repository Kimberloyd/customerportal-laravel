import { Input } from '@/components/motion/input';
import SpecularButton from '@/components/SpecularButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function TwoFactorChallenge() {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const submit = (event) => {
        event.preventDefault();
        post(route('two-factor.verify'));
    };

    return (
        <GuestLayout>
            <Head title="Two-factor authentication" />
            <div className="mb-8">
                <h2 className="text-3xl font-semibold tracking-tight text-gray-950">Verify your sign-in</h2>
                <p className="mt-2 text-sm leading-6 text-gray-600">
                    Enter the six-digit code from your authenticator app or one unused recovery code.
                </p>
            </div>

            <form onSubmit={submit}>
                <Input
                    id="code"
                    label="Authentication code"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    autoFocus
                    value={data.code}
                    error={errors.code}
                    onChange={(value) => setData('code', value)}
                />

                <div className="mt-8">
                    <SpecularButton
                        type="submit"
                        size="md"
                        radius={999}
                        tint="#34379b"
                        tintOpacity={1}
                        textColor="#ffffff"
                        lineColor="#d8d9ff"
                        baseColor="#242675"
                        intensity={1.25}
                        shineSize={12}
                        shineFade={45}
                        thickness={1.1}
                        speed={0.4}
                        autoAnimate
                        disabled={processing}
                        className="w-full"
                    >
                        {processing ? 'Verifying…' : 'Verify'}
                    </SpecularButton>
                </div>
            </form>

            <Link href={route('login')} className="mt-5 block text-center text-sm font-medium text-primary hover:underline">
                Return to sign in
            </Link>
        </GuestLayout>
    );
}
