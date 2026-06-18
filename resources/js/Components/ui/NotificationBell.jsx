import { Link, router, usePage } from '@inertiajs/react';
import { useId, useRef, useState } from 'react';
import { useDismissiblePopover } from '@/hooks/useDismissiblePopover';
import Icon, { Icons } from './Icons';

function formatWhen(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function alertLabel(type) {
    switch (type) {
        case 'ready':
            return 'Ready to send';
        case 'at_risk':
            return 'Deliverability at risk';
        case 'connection_failed':
            return 'Connection failed';
        default:
            return 'Warmup alert';
    }
}

function NotificationItem({ item, onSelect }) {
    const content = (
        <>
            <span className="notification-bell-item-label">{alertLabel(item.type)}</span>
            <span className="notification-bell-item-message">{item.message}</span>
            <span className="notification-bell-item-time">{formatWhen(item.created_at)}</span>
        </>
    );

    if (item.url) {
        return (
            <Link
                href={item.url}
                className="notification-bell-item"
                role="menuitem"
                onClick={onSelect}
            >
                {content}
            </Link>
        );
    }

    return (
        <button type="button" className="notification-bell-item" role="menuitem" onClick={onSelect}>
            {content}
        </button>
    );
}

export default function NotificationBell() {
    const { notifications = [], unreadNotificationsCount = 0 } = usePage().props;
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);
    const menuId = useId();

    const close = () => setOpen(false);
    useDismissiblePopover({ open, onClose: close, rootRef });

    const markRead = (id) => {
        router.post(`/notifications/${id}/read`, {}, { preserveScroll: true });
    };

    const markAllRead = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    };

    const handleSelect = (id) => {
        markRead(id);
        close();
    };

    return (
        <div className="notification-bell" ref={rootRef}>
            <button
                type="button"
                className={`notification-bell-trigger${open ? ' is-open' : ''}`}
                aria-expanded={open}
                aria-haspopup="menu"
                aria-controls={menuId}
                aria-label={`Notifications${unreadNotificationsCount ? `, ${unreadNotificationsCount} unread` : ''}`}
                onClick={() => setOpen((prev) => !prev)}
            >
                <Icon d={Icons.Bell} size={16} />
                {unreadNotificationsCount > 0 ? (
                    <span className="notification-bell-count">{unreadNotificationsCount}</span>
                ) : null}
            </button>

            {open ? (
                <div id={menuId} className="notification-bell-panel" role="menu">
                    <div className="notification-bell-header">
                        <span className="notification-bell-title">Notifications</span>
                        {unreadNotificationsCount > 0 ? (
                            <button type="button" className="notification-bell-mark-all" onClick={markAllRead}>
                                Mark all as read
                            </button>
                        ) : null}
                    </div>

                    {notifications.length === 0 ? (
                        <div className="notification-bell-empty">No unread notifications.</div>
                    ) : (
                        <ul className="notification-bell-list" role="list">
                            {notifications.map((item) => (
                                <li key={item.id}>
                                    <NotificationItem
                                        item={item}
                                        onSelect={() => handleSelect(item.id)}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            ) : null}
        </div>
    );
}
