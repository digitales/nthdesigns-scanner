import { Link } from '@inertiajs/react';
import { Icon, Icons, SkipBanner } from '@/Components/ui';

function formatReadyDate(value) {
    if (!value) {
        return null;
    }

    return new Date(`${value}T12:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function WarmupReadinessBanner({ readiness }) {
    if (!readiness) {
        return null;
    }

    if (readiness.state === 'ready') {
        return (
            <SkipBanner kind="success">
                Sending from {readiness.primary_email}. Mailbox is ready for cold outreach.
            </SkipBanner>
        );
    }

    if (readiness.state === 'not_ready') {
        const readyDate = formatReadyDate(readiness.estimated_ready_date);

        return (
            <SkipBanner icon={<Icon d={Icons.Lock} size={14} />}>
                Your outreach domain isn&apos;t ready yet
                {readyDate ? `. Estimated ready ${readyDate}` : ''}.{' '}
                <Link href="/warmup" className="link-inline">
                    View warmup
                </Link>
                .
            </SkipBanner>
        );
    }

    if (readiness.state === 'no_mailbox') {
        return (
            <SkipBanner icon={<Icon d={Icons.Lock} size={14} />}>
                No outreach mailbox connected.{' '}
                <Link href="/warmup/connect" className="link-inline">
                    Connect a sending domain
                </Link>{' '}
                before cold email.
            </SkipBanner>
        );
    }

    return null;
}
