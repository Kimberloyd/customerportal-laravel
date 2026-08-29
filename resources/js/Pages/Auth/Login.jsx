import { Checkbox } from '@/components/motion/checkbox';
import { Input } from '@/components/motion/input';
import SpecularButton from '@/components/SpecularButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="mb-8">
                <h2 className="text-3xl font-semibold tracking-tight text-gray-950">
                    Welcome back
                </h2>
                <p className="mt-2 text-sm leading-6 text-gray-600">
                    Enter your account details to continue.
                </p>
            </div>

            {status && (
                <div className="mb-5 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700" role="status">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <Input
                        id="email"
                        label="Email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        error={errors.email}
                        onChange={(value) => setData('email', value)}
                    />
                </div>

                <div className="mt-5">
                    <Input
                        id="password"
                        label="Password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        error={errors.password}
                        onChange={(value) => setData('password', value)}
                    />
                </div>

                <div className="mt-5">
                    <Checkbox
                        id="remember"
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', checked)}
                        label="Remember me"
                    />
                </div>

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
                        {processing ? 'Continuing…' : 'Continue'}
                    </SpecularButton>
                </div>
            </form>
        </GuestLayout>
    );
}
