import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-primary bg-primary/10 text-primary focus-visible:border-primary focus-visible:bg-primary/20 focus-visible:text-primary'
                    : 'border-transparent text-muted-foreground hover:border-border hover:bg-hover hover:text-foreground focus-visible:border-border focus-visible:bg-hover focus-visible:text-foreground'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset ${className}`}
        >
            {children}
        </Link>
    );
}
