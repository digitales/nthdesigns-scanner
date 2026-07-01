import { Link } from '@inertiajs/react';

export default function ListsSubNav({ active = 'index' }) {
    const linkClass = (key) => (active === key ? 'btn-secondary btn-sm' : 'btn-ghost btn-sm');

    return (
        <nav className="lists-sub-nav mb-16" aria-label="Lists sections">
            <Link href="/lists" className={linkClass('index')}>
                My lists
            </Link>
            <Link href="/lists/browse" className={linkClass('browse')}>
                Browse
            </Link>
            <Link href="/lists/pipeline" className={linkClass('pipeline')}>
                Outreach pipeline
            </Link>
        </nav>
    );
}
