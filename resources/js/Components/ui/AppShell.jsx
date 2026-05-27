import { Link, usePage } from '@inertiajs/react';
import Brand from './Brand';
import Status from './Status';
import UserMenu from './UserMenu';

export default function AppShell({ children }) {
    const { auth, outreachSelectionCount = 0 } = usePage().props;
    const user = auth.user;

    const navItems = [
        { href: '/search', label: 'Search', match: ['search.index', 'searches.show'] },
        { href: '/outreach', label: 'Outreach', match: ['outreach.index', 'outreach.*'], count: outreachSelectionCount },
        { href: '/saved', label: 'Saved', match: ['saved.index'] },
        { href: '/reports', label: 'Reports', match: ['reports.index'] },
        { href: '/settings', label: 'Settings', match: ['settings.index', 'settings.*'] },
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
                <Brand href="/search" />

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
                    <UserMenu user={user} />
                </div>
            </div>
            {children}
        </div>
    );
}
