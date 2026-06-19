import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WarmupAlertBanners from '@/Pages/Warmup/components/WarmupAlertBanners';
import {
    Card,
    DataTable,
    EmptyState,
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

export default function WarmupShow({ mailbox, sends, stats, estimated_ready_date, alerts = [] }) {
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

                <WarmupAlertBanners mailbox={mailbox} score={score} alerts={alerts} />

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
                                        <th className="col-sent">Sent</th>
                                        <th>Recipient</th>
                                        <th>Subject</th>
                                        <th className="col-status">Status</th>
                                        <th className="col-date">Opened</th>
                                        <th className="col-date">Replied</th>
                                        <th className="col-date">Rescued</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sends.map((row) => (
                                        <tr key={row.id}>
                                            <td className="col-sent micro text-stone">
                                                <time dateTime={row.sent_at}>{formatDate(row.sent_at)}</time>
                                            </td>
                                            <td className="micro text-stone">{row.recipient ?? '—'}</td>
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
                                                {row.opened_at ? (
                                                    <time dateTime={row.opened_at}>{formatDate(row.opened_at)}</time>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="col-date micro text-stone">
                                                {row.replied_at ? (
                                                    <time dateTime={row.replied_at}>{formatDate(row.replied_at)}</time>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="col-date micro text-stone">
                                                {row.rescued_from_spam_at ? (
                                                    <time dateTime={row.rescued_from_spam_at}>
                                                        {formatDate(row.rescued_from_spam_at)}
                                                    </time>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
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

                    </Stack>
                </SidebarLayout>
            </Page>
        </AuthenticatedLayout>
    );
}
