import { useEffect, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import Brand from './Brand';
import { NotificationBell } from '@/Components/ui';
import SearchBar from './SearchBar';
import Status from './Status';
import UserMenu from './UserMenu';

export default function AppShell({ children }) {
    const { auth, outreachSelectionCount = 0 } = usePage().props;
    const user = auth.user;
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef(null);

    const navItems = [
        { href: '/search', label: 'Search', match: ['search.index', 'searches.index', 'searches.show'], priority: true },
        { href: '/niches', label: 'Niches', match: ['niches.index'], priority: true },
        { href: '/outreach', label: 'Outreach', match: ['outreach.index', 'outreach.*'], count: outreachSelectionCount, priority: true },
        { href: '/warmup', label: 'Warmup', match: ['warmup.index', 'warmup.*'], priority: true },
        { href: '/lists', label: 'Lists', match: ['lists.index', 'lists.show', 'lists.browse', 'lists.pipeline', 'saved.index'], priority: true },
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

    useEffect(() => {
        const handler = (e) => {
            if (menuRef.current && !menuRef.current.contains(e.target)) {
                setMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const extraItems = navItems.filter((n) => !n.priority);
    const hasActiveExtra = extraItems.some((n) => isActive(n.match));

    return (
        <div className="app">
            <div className="app-topbar">
                <Brand href="/search" />

                <nav className="app-nav">
                    {navItems.map((n) => (
                        <Link
                            key={n.href}
                            href={n.href}
                            className={[
                                isActive(n.match) ? 'active' : '',
                                !n.priority ? 'app-nav-extra' : '',
                            ].filter(Boolean).join(' ')}
                        >
                            {n.label}
                            {n.count > 0 ? <span className="count">{n.count}</span> : null}
                        </Link>
                    ))}
                </nav>

                <div className="app-tools">
                    <SearchBar />
                    <span className="app-topbar-status">
                        <Status kind="ready">APIs online</Status>
                    </span>
                    <NotificationBell />
                    <UserMenu user={user} />

                    <div className="app-menu-toggle" ref={menuRef}>
                        <button
                            className={[
                                'app-menu-btn',
                                menuOpen ? 'is-open' : '',
                                hasActiveExtra ? 'has-active' : '',
                            ].filter(Boolean).join(' ')}
                            onClick={() => setMenuOpen((o) => !o)}
                            aria-label="Open navigation menu"
                            aria-expanded={menuOpen}
                        >
                            <svg width="16" height="14" viewBox="0 0 16 14" fill="none" aria-hidden="true">
                                <path d="M1 1.5h14M1 7h14M1 12.5h14" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
                            </svg>
                        </button>
                        {menuOpen && (
                            <div className="app-menu-panel">
                                {navItems.map((n) => (
                                    <Link
                                        key={n.href}
                                        href={n.href}
                                        className={[
                                            'app-menu-item',
                                            isActive(n.match) ? 'active' : '',
                                            n.priority ? 'app-menu-item--priority' : '',
                                        ].filter(Boolean).join(' ')}
                                        onClick={() => setMenuOpen(false)}
                                    >
                                        {n.label}
                                        {n.count > 0 ? <span className="count">{n.count}</span> : null}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
            {children}
        </div>
    );
}
