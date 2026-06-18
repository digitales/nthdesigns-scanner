import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Card,
    DataTable,
    EmptyState,
    Icon,
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
    paused: 'Paused',
    failed: 'Connection failed',
};

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function readinessMessage(mailbox, score) {
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
        return 'Warmup is running. Score updates daily once seed inboxes process emails.';
    }
    return 'Start warmup from the dashboard once seed accounts are connected.';
}

export default function WarmupShow({ mailbox, sends, stats, estimated_ready_date }) {
    const score = mailbox.deliverability_score;

    return (
        <AuthenticatedLayout>
            <Head title={`Warmup — ${mailbox.email}`} />

            <Page width="wide" className="page-wide">
                <PageHeader
                    eyebrow="Warmup"
                    title={mailbox.email}
                    sub={`${mailbox.provider} · ${MAILBOX_STATUS[mailbox.status] ?? mailbox.status}`}
                    back="All mailboxes"
                    onBack={() => router.visit('/warmup')}
                    actions={
                        <LinkButton href="/warmup" kind="secondary">
                            Back to warmup
                        </LinkButton>
                    }
                />

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
                    <StatTile label="Replies received" value={stats.replies_received} warm={stats.replies_received > 0} />
                    <StatTile label="Spam rescues" value={stats.spam_rescues} />
                    <StatTile
                        label="Estimated ready"
                        value={estimated_ready_date ?? '—'}
                    />
                    <StatTile
                        label="Last inbox check"
                        value={mailbox.last_imap_check_at ? formatDate(mailbox.last_imap_check_at).split(',')[0] : '—'}
                    />
                </StatsStrip>

                <SidebarLayout className="warmup-detail-layout">
                    <Card title="Send history" pad={sends.length > 0}>
                        {sends.length === 0 ? (
                            <EmptyState
                                icon={Icons.Mail}
                                title="No sends yet"
                                sub="Warmup emails will appear here once the daily job runs."
                            />
                        ) : (
                            <DataTable tableClassName="ptable--paper">
                                <thead>
                                    <tr>
                                        <th>Sent</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Opened</th>
                                        <th>Replied</th>
                                        <th>Rescued</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sends.map((row) => (
                                        <tr key={row.id}>
                                            <td>
                                                <div className="body-sm-medium-tight">{formatDate(row.sent_at)}</div>
                                            </td>
                                            <td className="note-cell">
                                                <div className="body-sm-medium-tight truncate" title={row.subject}>
                                                    {row.subject}
                                                </div>
                                            </td>
                                            <td>
                                                <Status kind={STATUS_KIND[row.status] ?? 'pending'}>
                                                    {STATUS_LABELS[row.status] ?? row.status}
                                                </Status>
                                            </td>
                                            <td className="micro text-stone">{formatDate(row.opened_at)}</td>
                                            <td className="micro text-stone">{formatDate(row.replied_at)}</td>
                                            <td className="micro text-stone">{formatDate(row.rescued_from_spam_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </DataTable>
                        )}
                    </Card>

                    <Stack gap={12}>
                        <Card title="Status">
                            <Stack gap={12}>
                                <Status kind={mailbox.status === 'ready' ? 'ready' : 'pending'}>
                                    {MAILBOX_STATUS[mailbox.status] ?? mailbox.status}
                                </Status>
                                <p className="micro text-muted m-0">
                                    {readinessMessage(mailbox, score)}
                                </p>
                            </Stack>
                        </Card>

                        {estimated_ready_date && mailbox.status === 'warming' && (
                            <Card title="On track">
                                <p className="micro m-0">
                                    At the current ramp schedule, this mailbox should reach ready status around{' '}
                                    <strong>{estimated_ready_date}</strong>.
                                </p>
                            </Card>
                        )}

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

                        {score != null && score < 60 && mailbox.days_warming > 3 && (
                            <div className="skip-banner">
                                <Icon d={Icons.Lock} size={14} />
                                Deliverability at risk — verify SPF and DKIM before continuing.
                            </div>
                        )}
                    </Stack>
                </SidebarLayout>
            </Page>
        </AuthenticatedLayout>
    );
}
