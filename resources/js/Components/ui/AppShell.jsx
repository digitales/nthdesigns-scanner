import { Link, usePage } from '@inertiajs/react';
import Brand from './Brand';
import { NotificationBell } from '@/Components/ui';
import SearchBar from './SearchBar';
import Status from './Status';
import UserMenu from './UserMenu';

export default function AppShell({ children }) {
    const { auth, outreachSelectionCount = 0 } = usePage().props;
    const user = auth.user;

    const navItems = [
        { href: '/search', label: 'Search', match: ['search.index', 'searches.index', 'searches.show'] },
        { href: '/niches', label: 'Niches', match: ['niches.index'] },
        { href: '/outreach', label: 'Outreach', match: ['outreach.index', 'outreach.*'], count: outreachSelectionCount },
        { href: '/warmup', label: 'Warmup', match: ['warmup.index', 'warmup.*'] },
        { href: '/lists', label: 'Lists', match: ['lists.index', 'lists.show', 'lists.browse', 'saved.index'] },
        { href: '/ignored', label: 'Ignored', match: ['ignored.index'] },
        { href: '/reports', label: 'Reports', match: ['reports.index'] },
        { href: '/bookings', label: 'Bookings', match: ['bookings.index'] },
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
                    <SearchBar />
                    <Status kind="ready">APIs online</Status>
                    <NotificationBell />
                    <UserMenu user={user} />
                </div>
            </div>
            {children}
        </div>
    );
}
