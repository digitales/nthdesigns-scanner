import { Link } from '@inertiajs/react';

export default function Brand({ href = '/', product = 'Prospect Scanner', className = '' }) {
    const inner = (
        <>
            <span className="brand-mark" aria-hidden="true" />
            <span className="brand-name">nthdesigns</span>
            {product ? (
                <>
                    <span className="brand-sep">/</span>
                    <span className="brand-product">{product}</span>
                </>
            ) : null}
        </>
    );

    const cls = `app-brand ${className}`.trim();

    return href ? (
        <Link href={href} className={`${cls} no-underline text-inherit`}>
            {inner}
        </Link>
    ) : (
        <div className={cls}>{inner}</div>
    );
}
