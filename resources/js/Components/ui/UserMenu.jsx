import { Link } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';

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

function Chevron() {
    return (
        <svg className="user-chip-chevron" viewBox="0 0 12 12" aria-hidden="true">
            <path
                d="M3 4.5L6 7.5L9 4.5"
                stroke="currentColor"
                strokeWidth="1.4"
                fill="none"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

export default function UserMenu({ user }) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);
    const menuId = useId();

    useEffect(() => {
        if (!open) return undefined;

        const onKeyDown = (e) => {
            if (e.key === 'Escape') setOpen(false);
        };

        const onPointerDown = (e) => {
            if (!rootRef.current?.contains(e.target)) setOpen(false);
        };

        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('pointerdown', onPointerDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('pointerdown', onPointerDown);
        };
    }, [open]);

    const close = () => setOpen(false);

    if (!user) return null;

    const initials = userInitials(user.name);

    return (
        <div className="user-menu" ref={rootRef}>
            <button
                type="button"
                className={`user-chip user-chip-trigger${open ? ' is-open' : ''}`}
                aria-expanded={open}
                aria-haspopup="menu"
                aria-controls={menuId}
                onClick={() => setOpen((prev) => !prev)}
            >
                <Avatar initials={initials} />
                <span className="user-chip-name">{user.name}</span>
                <Chevron />
            </button>

            {open ? (
                <div id={menuId} className="user-menu-panel" role="menu">
                    <div className="user-menu-header">
                        <div className="user-menu-name">{user.name}</div>
                        {user.email ? <div className="user-menu-email">{user.email}</div> : null}
                    </div>

                    <div className="user-menu-divider" role="separator" />

                    <Link
                        href={route('profile.edit')}
                        className="user-menu-item"
                        role="menuitem"
                        onClick={close}
                    >
                        Profile
                    </Link>
                    <Link
                        href={route('settings.index')}
                        className="user-menu-item"
                        role="menuitem"
                        onClick={close}
                    >
                        Settings
                    </Link>

                    <div className="user-menu-divider" role="separator" />

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="user-menu-item user-menu-item--danger"
                        role="menuitem"
                        onClick={close}
                    >
                        Log out
                    </Link>
                </div>
            ) : null}
        </div>
    );
}
