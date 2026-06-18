import { Button, LinkButton, ScoreBadge, Status } from '@/Components/ui';

const STATUS_KIND = {
    pending: 'pending',
    warming: 'ready',
    ready: 'ready',
    paused: 'pending',
    failed: 'pending',
};

const STATUS_LABELS = {
    pending: 'Pending',
    warming: 'Warming',
    ready: 'Ready',
    paused: 'Paused',
    failed: 'Failed',
};

export default function WarmupMailboxCard({
    mailbox,
    needsSeeds,
    onStart,
    onTogglePause,
    onRemove,
}) {
    const isOutreach = mailbox.is_outreach_mailbox;

    return (
        <article className="card card-pad warmup-mailbox-card">
            <div className="warmup-mailbox-card__top">
                <div className="min-w-0">
                    <div className="warmup-mailbox-card__email">{mailbox.email}</div>
                    <div className="warmup-mailbox-card__meta">
                        <span className="micro text-muted capitalize">{mailbox.provider}</span>
                        {mailbox.is_outreach_mailbox && (
                            <span className="warmup-role-tag">Outreach</span>
                        )}
                        {mailbox.is_seed_mailbox && (
                            <span className="warmup-role-tag">Seed</span>
                        )}
                    </div>
                </div>
                <Status kind={STATUS_KIND[mailbox.status] ?? 'pending'}>
                    {STATUS_LABELS[mailbox.status] ?? mailbox.status}
                </Status>
            </div>

            {isOutreach && (
                <div className="warmup-mailbox-card__stats">
                    <ScoreBadge value={mailbox.deliverability_score ?? '—'} withBar />
                    <span className="micro text-muted">
                        {mailbox.days_warming} days · {mailbox.sends_today} sent today
                    </span>
                </div>
            )}

            <div className="warmup-mailbox-card__actions">
                {isOutreach && mailbox.status === 'pending' && !needsSeeds && (
                    <Button type="button" onClick={() => onStart(mailbox.id)}>
                        Start warmup
                    </Button>
                )}

                {isOutreach && ['warming', 'ready', 'paused'].includes(mailbox.status) && (
                    <Button type="button" kind="secondary" onClick={() => onTogglePause(mailbox.id)}>
                        {mailbox.status === 'paused' ? 'Resume' : 'Pause'}
                    </Button>
                )}

                {isOutreach && (
                    <LinkButton href={`/warmup/${mailbox.id}`} kind="secondary">
                        View details
                    </LinkButton>
                )}

                <Button type="button" kind="ghost" size="sm" onClick={() => onRemove(mailbox.id, mailbox.email)}>
                    Remove
                </Button>
            </div>
        </article>
    );
}
