import { Link, usePage } from '@inertiajs/react';
import Status from './Status';

function Avatar({ initials }) {
    return <span className="avatar">{initials}</span>;
}

function userInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
}

export default function AppShell({ children }) {
    const { auth, outreachSelectionCount = 0 } = usePage().props;
    const user = auth.user;

    const navItems = [
        { href: '/search', label: 'Search', match: ['search.index', 'searches.show'] },
        { href: '/outreach', label: 'Outreach', match: ['outreach.index', 'outreach.*'], count: outreachSelectionCount },
        { href: '/saved', label: 'Saved', match: ['saved.index'] },
        { href: '/reports', label: 'Reports', match: ['reports.index'] },
    ];

    const isActive = (patterns) =>
        patterns.some((p) => {
            if (p.endsWith('.*')) {
                const base = p.slice(0, -2);
                return route().current(`${base}.*`);
            }
            return route().current(p);
        });

    return (
        <div className="app">
            <div className="app-topbar">
                <Link href="/search" className="app-brand" style={{ textDecoration: 'none', color: 'inherit' }}>
                    <span className="brand-mark" />
                    <span className="brand-name">nthdesigns</span>
                    <span className="brand-sep">/</span>
                    <span className="brand-product">Prospect Scanner</span>
                </Link>

                <nav className="app-nav">
                    {navItems.map((n) => (
                        <Link
                            key={n.href}
                            href={n.href}
                            className={isActive(n.match) ? 'active' : ''}
                        >
                            {n.label}
                            {n.count > 0 ? <span className="count">{n.count}</span> : null}
                        </Link>
                    ))}
                </nav>

                <div className="app-tools">
                    <Status kind="ready">APIs online</Status>
                    <span className="user-chip">
                        <Avatar initials={userInitials(user?.name)} />
                        {user?.name}
                    </span>
                </div>
            </div>
            {children}
        </div>
    );
}
