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

function getMailboxStatusDisplay(mailbox) {
    const isSeedOnly = mailbox.is_seed_mailbox && !mailbox.is_outreach_mailbox;

    if (isSeedOnly && mailbox.status === 'pending') {
        return { kind: 'ready', label: 'Connected' };
    }

    return {
        kind: STATUS_KIND[mailbox.status] ?? 'pending',
        label: STATUS_LABELS[mailbox.status] ?? mailbox.status,
    };
}

export default function WarmupMailboxCard({
    mailbox,
    canStartWarmup,
    poolParticipationAllowed,
    onStart,
    onTogglePause,
    onTogglePool,
    onRemove,
}) {
    const isOutreach = mailbox.is_outreach_mailbox;
    const isSeedOnly = mailbox.is_seed_mailbox && !mailbox.is_outreach_mailbox;
    const statusDisplay = getMailboxStatusDisplay(mailbox);

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
                        {isSeedOnly && poolParticipationAllowed && mailbox.is_pool_participant && (
                            <span className="warmup-role-tag">Network</span>
                        )}
                    </div>
                </div>
                <Status kind={statusDisplay.kind}>
                    {statusDisplay.label}
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
                {isOutreach && mailbox.status === 'pending' && canStartWarmup && (
                    <Button type="button" onClick={() => onStart(mailbox.id)}>
                        Start warmup
                    </Button>
                )}

                {isOutreach && ['warming', 'ready', 'paused'].includes(mailbox.status) && (
                    <Button type="button" kind="secondary" onClick={() => onTogglePause(mailbox.id)}>
                        {mailbox.status === 'paused' ? 'Resume' : 'Pause'}
                    </Button>
                )}

                {isSeedOnly && poolParticipationAllowed && (
                    <Button
                        type="button"
                        kind="secondary"
                        onClick={() => onTogglePool(mailbox.id, !mailbox.is_pool_participant)}
                    >
                        {mailbox.is_pool_participant ? 'Leave network' : 'Join network'}
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
