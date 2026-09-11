import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <main className="grid min-h-screen bg-white lg:grid-cols-2">
            <a
                href="#main-content"
                className="sr-only focus-visible:not-sr-only focus-visible:absolute focus-visible:left-2 focus-visible:top-2 focus-visible:z-50 focus-visible:rounded-md focus-visible:bg-primary focus-visible:px-4 focus-visible:py-2 focus-visible:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
            >
                Skip to main content
            </a>
            <section className="relative hidden overflow-hidden bg-white px-12 py-10 lg:flex lg:flex-col lg:items-start xl:px-20 xl:py-14">
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
                        <div className="max-w-xl text-left font-display text-4xl italic leading-tight tracking-wide text-foreground/80 xl:text-5xl">
                            Delay is not an <span className="text-[#00A652]">Option</span>.
                        </div>
                    </div>

                <p className="relative z-10 max-w-lg pb-2 text-left text-lg leading-8 text-gray-600">
                    Sign in to manage purchase orders, account activity, and customer communication.
                </p>
                <Link
                    href={route('terms-and-privacy')}
                    className="relative z-10 mt-3 text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Terms &amp; Privacy
                </Link>
            </section>

            <section className="relative flex min-h-screen flex-col items-center justify-center px-6 py-10 sm:px-10 lg:px-16">
                <Link href="/" className="mb-8 block w-fit lg:hidden" aria-label="Theomeds Marketing home">
                    <img
                        src="/images/TM Horizontal Lockup_Transparent BG.png"
                        alt="Theomeds Marketing"
                        className="h-16 w-auto"
                    />
                </Link>
                <div id="main-content" tabIndex={-1} className="w-full max-w-md focus:outline-none">
                    {children}
                </div>
            </section>
        </main>
    );
}
