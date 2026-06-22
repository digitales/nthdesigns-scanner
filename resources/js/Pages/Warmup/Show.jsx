import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WarmupAlertBanners from '@/Pages/Warmup/components/WarmupAlertBanners';
import WarmupConnectionPanel from '@/Pages/Warmup/components/WarmupConnectionPanel';
import {
    Card,
    DataTable,
    EmptyState,
    Icons,
    LinkButton,
    MetaList,
    Page,
    PageHeader,
    ScoreCard,
    SidebarLayout,
    SplitRow,
    Stack,
    StatTile,
    StatsStrip,
    Status,
} from '@/Components/ui';

const STATUS_LABELS = {
    sent: 'Sent',
    opened: 'Opened',
    replied: 'Replied',
    rescued: 'Rescued',
    bounced: 'Bounced',
};

const STATUS_KIND = {
    sent: 'pending',
    opened: 'ready',
    replied: 'ready',
    rescued: 'pending',
    bounced: 'pending',
};

const MAILBOX_STATUS = {
    pending: 'Pending',
    warming: 'Warming',
    ready: 'Ready to send',
    at_risk: 'At risk',
    paused: 'Paused',
    failed: 'Connection failed',
};

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    const day = date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    const time = date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    return `${day}, ${time}`;
}

function readinessMessage(mailbox, score) {
    if (mailbox.status === 'failed') {
        return 'Connection failed — test the connection below and update your app password if needed.';
    }
    if (mailbox.status === 'ready') {
        return 'This mailbox has reached the deliverability threshold and is ready for outreach.';
    }
    if (mailbox.status === 'paused') {
        return 'Warmup is paused. Resume to continue building reputation.';
    }
    if (score != null && score < 50 && mailbox.days_warming > 3) {
        return 'Deliverability is low — check SPF and DKIM records on your sending domain.';
    }
    if (mailbox.status === 'warming') {
        return 'Warmup is running. First sends go out within your send window; score updates daily once seed inboxes process emails.';
    }
    return 'Start warmup from the dashboard once seed accounts are connected.';
}

function seedStatusMessage(mailbox) {
    if (mailbox.status === 'failed') {
        return 'This seed account cannot connect — update credentials below so warmup replies can send.';
    }
    if (mailbox.consecutive_failures > 0) {
        return 'Recent connection failures were detected. Test the connection and update credentials if needed.';
    }
    return 'Seed accounts receive warmup emails and send short replies to build sender reputation.';
}

function ActivityTable({ rows, variant }) {
    if (rows.length === 0) {
        return (
            <EmptyState
                icon={Icons.Mail}
                title={variant === 'received' ? 'No emails received yet' : 'No sends yet'}
                sub={
                    variant === 'received'
                        ? 'Warmup emails addressed to this seed will appear here.'
                        : 'Warmup emails will appear here once the daily job runs.'
                }
            />
        );
    }

    return (
        <DataTable tableClassName="ptable--paper ptable--warmup-sends" className="data-table--scroll">
            <colgroup>
                <col className="col-sent" />
                <col className="col-recipient" />
                <col className="col-subject" />
                <col className="col-status" />
                <col className="col-date" />
                <col className="col-date" />
                <col className="col-date" />
            </colgroup>
            <thead>
                <tr>
                    <th className="col-sent">{variant === 'received' ? 'Received' : 'Sent'}</th>
                    <th>{variant === 'received' ? 'From' : 'Recipient'}</th>
                    <th>Subject</th>
                    <th className="col-status">Status</th>
                    <th className="col-date">Opened</th>
                    <th className="col-date">Replied</th>
                    <th className="col-date">Rescued</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.id}>
                        <td className="col-sent micro text-stone">
                            <time dateTime={row.sent_at}>{formatDate(row.sent_at)}</time>
                        </td>
                        <td className="micro text-stone">
                            {variant === 'received' ? row.sender : row.recipient ?? '—'}
                        </td>
                        <td className="note-cell">
                            <div className="body-sm-medium-tight truncate" title={row.subject}>
                                {row.subject}
                            </div>
                        </td>
                        <td className="col-status">
                            <Status kind={STATUS_KIND[row.status] ?? 'pending'}>
                                {STATUS_LABELS[row.status] ?? row.status}
                            </Status>
                        </td>
                        <td className="col-date micro text-stone">
                            {row.opened_at ? <time dateTime={row.opened_at}>{formatDate(row.opened_at)}</time> : '—'}
                        </td>
                        <td className="col-date micro text-stone">
                            {row.replied_at ? <time dateTime={row.replied_at}>{formatDate(row.replied_at)}</time> : '—'}
                        </td>
                        <td className="col-date micro text-stone">
                            {row.rescued_from_spam_at ? (
                                <time dateTime={row.rescued_from_spam_at}>{formatDate(row.rescued_from_spam_at)}</time>
                            ) : (
                                '—'
                            )}
                        </td>
                    </tr>
                ))}
            </tbody>
        </DataTable>
    );
}

export default function WarmupShow({
    mailbox,
    sends = [],
    received = [],
    stats,
    estimated_ready_date,
    alerts = [],
}) {
    const score = mailbox.deliverability_score;
    const isOutreach = mailbox.is_outreach_mailbox;
    const isSeedOnly = mailbox.is_seed_mailbox && !mailbox.is_outreach_mailbox;
    const isBoth = mailbox.is_outreach_mailbox && mailbox.is_seed_mailbox;
    const roleLabel = isBoth ? 'Outreach · Seed' : isOutreach ? 'Outreach' : 'Seed';

    return (
        <AuthenticatedLayout>
            <Head title={`Warmup — ${mailbox.email}`} />

            <Page width="wide" className="page-wide">
                <PageHeader
                    eyebrow="Warmup"
                    title={mailbox.email}
                    sub={`${mailbox.provider} · ${roleLabel} · ${MAILBOX_STATUS[mailbox.status] ?? mailbox.status}`}
                    back="All mailboxes"
                    onBack={() => router.visit('/warmup')}
                />

                {isOutreach && <WarmupAlertBanners mailbox={mailbox} score={score} alerts={alerts} />}

                {mailbox.status === 'failed' && (
                    <div className="skip-banner skip-banner--critical">
                        Connection failed. Test the connection below and update your app password if credentials have changed.
                    </div>
                )}

                {isOutreach ? (
                    <>
                        <div className="warmup-detail-hero">
                            <ScoreCard
                                label="Deliverability score"
                                value={score ?? '—'}
                                highlight={score != null && score >= 80}
                                healthScore={score != null}
                            />
                            <div className="score-card-row score-card-row--dual">
                                <StatTile label="Days warming" value={mailbox.days_warming} />
                                <StatTile label="Sends this week" value={stats.sends_this_week} />
                            </div>
                        </div>

                        <StatsStrip>
                            <StatTile
                                label="Replies received"
                                value={stats.replies_received}
                                warm={stats.replies_received > 0}
                            />
                            <StatTile label="Spam rescues" value={stats.spam_rescues} />
                            <StatTile label="Estimated ready" value={estimated_ready_date ?? '—'} />
                            <StatTile
                                label="Last inbox check"
                                value={
                                    mailbox.last_imap_check_at
                                        ? formatDate(mailbox.last_imap_check_at).split(',')[0]
                                        : '—'
                                }
                            />
                        </StatsStrip>
                    </>
                ) : (
                    <StatsStrip>
                        <StatTile label="Received this week" value={stats.received_this_week} />
                        <StatTile
                            label="Replies sent"
                            value={stats.replies_sent_this_week}
                            warm={stats.replies_sent_this_week > 0}
                        />
                        <StatTile label="Spam rescues" value={stats.spam_rescues} />
                        <StatTile
                            label="Last inbox check"
                            value={
                                mailbox.last_imap_check_at
                                    ? formatDate(mailbox.last_imap_check_at).split(',')[0]
                                    : '—'
                            }
                        />
                    </StatsStrip>
                )}

                <SidebarLayout className="warmup-detail-layout">
                    <Card
                        title={isSeedOnly ? 'Received emails' : 'Send history'}
                        pad={isSeedOnly ? received.length > 0 : sends.length > 0}
                    >
                        {isSeedOnly ? (
                            <ActivityTable rows={received} variant="received" />
                        ) : (
                            <ActivityTable rows={sends} variant="sent" />
                        )}
                    </Card>

                    <Stack gap={12}>
                        <WarmupConnectionPanel mailbox={mailbox} />

                        <Card title="Status">
                            <Stack gap={12}>
                                <Status kind={mailbox.status === 'ready' ? 'ready' : 'pending'}>
                                    {MAILBOX_STATUS[mailbox.status] ?? mailbox.status}
                                </Status>
                                <p className="micro text-muted m-0">
                                    {isSeedOnly ? seedStatusMessage(mailbox) : readinessMessage(mailbox, score)}
                                </p>
                            </Stack>
                        </Card>

                        {isOutreach && estimated_ready_date && mailbox.status === 'warming' && (
                            <Card title="On track">
                                <p className="micro m-0">
                                    At the current ramp schedule, this mailbox should reach ready status around{' '}
                                    <strong>{estimated_ready_date}</strong>.
                                </p>
                            </Card>
                        )}

                        {isSeedOnly && mailbox.is_pool_participant && (
                            <Card title="Network pool">
                                <p className="micro m-0">
                                    This seed is participating in the shared network and may receive warmup emails from
                                    other users&apos; outreach mailboxes.
                                </p>
                            </Card>
                        )}

                        {isOutreach && (
                            <Card title="Health checks">
                                <MetaList>
                                    <SplitRow>
                                        <span className="micro text-medium">Target score</span>
                                        <span className="micro">80+</span>
                                    </SplitRow>
                                    <SplitRow>
                                        <span className="micro text-medium">Ramp period</span>
                                        <span className="micro">14 days default</span>
                                    </SplitRow>
                                    <SplitRow>
                                        <span className="micro text-medium">Daily job</span>
                                        <span className="micro">08:00 UTC</span>
                                    </SplitRow>
                                </MetaList>
                            </Card>
                        )}

                        <LinkButton href="/warmup" kind="secondary">
                            Back to all mailboxes
                        </LinkButton>
                    </Stack>
                </SidebarLayout>
            </Page>
        </AuthenticatedLayout>
    );
}
