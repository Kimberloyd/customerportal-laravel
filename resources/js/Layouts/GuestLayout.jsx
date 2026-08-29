import { Link } from '@inertiajs/react';
import SideRays from '@/components/SideRays';
import Typewriter from '../../../components/fancy/text/typewriter';

export default function GuestLayout({ children }) {
    return (
        <main className="grid min-h-screen bg-white lg:grid-cols-2">
            <section className="relative hidden overflow-hidden bg-[#2E3192] px-12 py-10 lg:flex lg:flex-col lg:items-start xl:px-20 xl:py-14">
                <div aria-hidden="true" className="absolute inset-0">
                    <SideRays
                        rayColor1="#ffffff"
                        rayColor2="#fffcf3"
                        origin="top-left"
                        speed={1}
                        intensity={5}
                        spread={1.5}
                        tilt={1}
                        saturation={2}
                        blend={1}
                        falloff={1.5}
                        className="h-full w-full"
                    />
                </div>
                <div aria-hidden="true" className="absolute inset-0 bg-slate-950/35" />

                <Link
                    href="/"
                    className="absolute left-12 top-10 z-10 block w-fit xl:left-20"
                    aria-label="Theomeds Marketing home"
                >
                    <img
                        src="/images/TM Horizontal Lockup_Transparent BG.png"
                        alt="Theomeds Marketing"
                        className="h-24 w-auto"
                    />
                </Link>

                <div className="relative z-10 flex flex-1 items-center">
                    <div className="max-w-xl text-left text-4xl font-semibold leading-tight tracking-tight text-white xl:text-5xl">
                        Delay is not an{' '}
                        <Typewriter
                            text={['option.', 'excuse.', 'alternative.']}
                            speed={50}
                            waitTime={2000}
                            deleteSpeed={30}
                            className="text-[#00A652]"
                            cursorClassName="ml-1 text-[#00A652]"
                        />
                    </div>
                </div>

                <p className="relative z-10 max-w-lg pb-2 text-left text-lg leading-8 text-slate-200">
                    Sign in to manage purchase orders, account activity, and customer communication.
                </p>
            </section>

            <section className="relative flex min-h-screen items-center justify-center px-6 py-10 sm:px-10 lg:px-16">
                <div className="w-full max-w-md">
                    {children}
                </div>
            </section>
        </main>
    );
}
