import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Badge,
    Card,
    DataTable,
    EmptyState,
    LinkButton,
    Page,
    PageHeader,
    ScoreBadge,
    Stack,
    StatTile,
    StatsStrip,
} from '@/Components/ui';

const STATUS_LABELS = {
    sent: 'Sent',
    opened: 'Opened',
    replied: 'Replied',
    rescued: 'Rescued',
    bounced: 'Bounced',
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

export default function WarmupShow({ mailbox, sends, stats, estimated_ready_date }) {
    return (
        <AuthenticatedLayout>
            <Head title={`Warmup — ${mailbox.email}`} />

            <Page width="wide">
                <PageHeader
                    eyebrow="Warmup"
                    title={mailbox.email}
                    sub={`${mailbox.provider} · ${mailbox.status}`}
                    actions={
                        <LinkButton href="/warmup" kind="secondary">
                            Back to mailboxes
                        </LinkButton>
                    }
                />

                <Stack gap={16}>
                    <div className="flex flex-wrap items-center gap-16">
                        <ScoreBadge value={mailbox.deliverability_score ?? '—'} withBar />
                        <Badge className={mailbox.status === 'ready' ? 'text-positive' : ''}>
                            {mailbox.status}
                        </Badge>
                    </div>

                    <StatsStrip>
                        <StatTile label="Days warming" value={mailbox.days_warming} />
                        <StatTile label="Sends this week" value={stats.sends_this_week} />
                        <StatTile label="Replies received" value={stats.replies_received} />
                        <StatTile label="Spam rescues" value={stats.spam_rescues} />
                    </StatsStrip>

                    {estimated_ready_date && mailbox.status === 'warming' && (
                        <Card title="Estimated ready date">
                            <p className="micro m-0">
                                On current trajectory, this mailbox should be ready around{' '}
                                <strong>{estimated_ready_date}</strong>.
                            </p>
                        </Card>
                    )}

                    {mailbox.last_imap_check_at && (
                        <p className="micro text-muted m-0">
                            Last inbox check: {formatDate(mailbox.last_imap_check_at)}
                        </p>
                    )}

                    <Card title="Send history">
                        {sends.length === 0 ? (
                            <EmptyState
                                title="No sends yet"
                                sub="Warmup emails will appear here once the daily job runs."
                            />
                        ) : (
                            <DataTable>
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
                                            <td className="micro">{formatDate(row.sent_at)}</td>
                                            <td className="micro">
                                                <span className="truncate max-w-[240px] inline-block" title={row.subject}>
                                                    {row.subject}
                                                </span>
                                            </td>
                                            <td className="micro">{STATUS_LABELS[row.status] ?? row.status}</td>
                                            <td className="micro">{formatDate(row.opened_at)}</td>
                                            <td className="micro">{formatDate(row.replied_at)}</td>
                                            <td className="micro">{formatDate(row.rescued_from_spam_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </DataTable>
                        )}
                    </Card>
                </Stack>
            </Page>
        </AuthenticatedLayout>
    );
}
