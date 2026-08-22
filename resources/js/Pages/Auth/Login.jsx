import Checkbox from '@/components/Checkbox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
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

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
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

                <div className="mt-4">
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

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600">
                            Remember me
                        </span>
                    </label>
                </div>

                <div className="mt-4 flex items-center justify-end">
                    <Button type="submit" variant="primary" className="ms-4" disabled={processing}>
                        Log in
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
