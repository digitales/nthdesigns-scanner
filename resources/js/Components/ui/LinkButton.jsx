import { Link } from '@inertiajs/react';

export default function LinkButton({
    kind = 'secondary',
    size = 'md',
    className = '',
    children,
    ...rest
}) {
    const sizeClass = size === 'sm' ? ' btn-sm' : size === 'xs' ? ' btn-xs' : size === 'lg' ? ' btn-lg' : '';
    return (
        <Link className={`btn btn-${kind}${sizeClass} ${className}`.trim()} {...rest}>
            {children}
        </Link>
    );
}
