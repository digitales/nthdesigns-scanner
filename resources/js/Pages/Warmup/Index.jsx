import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    Grid,
    LinkButton,
    Page,
    PageHeader,
    ScoreBadge,
    Stack,
} from '@/Components/ui';

const STATUS_LABELS = {
    pending: 'Pending',
    warming: 'Warming',
    ready: 'Ready',
    paused: 'Paused',
    failed: 'Failed',
};

function statusClass(status) {
    if (status === 'ready') return 'text-positive';
    if (status === 'warming') return 'text-medium';
    if (status === 'paused') return 'text-muted';
    if (status === 'failed') return 'text-critical';
    return '';
}

export default function WarmupIndex({ mailboxes, seedCount }) {
    const { flash } = usePage().props;

    const outreachMailboxes = mailboxes.filter((m) => m.is_outreach_mailbox);
    const hasOutreach = outreachMailboxes.length > 0;
    const needsSeeds = seedCount < 2;

    const startWarmup = (id) => {
        router.post(`/warmup/${id}/start`);
    };

    const togglePause = (id) => {
        router.post(`/warmup/${id}/toggle-pause`);
    };

    const removeMailbox = (id, email) => {
        if (!window.confirm(`Remove ${email}? Send history will be deleted.`)) {
            return;
        }
        router.delete(`/warmup/${id}`);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Warmup" />

            <Page width="wide">
                <PageHeader
                    eyebrow="Email warmup"
                    title="Domain reputation."
                    sub="Warm up outreach mailboxes before cold email. Connect your sending domain and seed accounts, then let the engine run for 2–4 weeks."
                    actions={
                        <LinkButton href="/warmup/connect">Add mailbox</LinkButton>
                    }
                />

                {flash?.success && (
                    <p className="micro text-positive mb-16">{flash.success}</p>
                )}

                {mailboxes.length === 0 ? (
                    <EmptyState
                        title="No mailboxes connected"
                        sub="Add your outreach mailbox (e.g. ross@nthdesign.co.uk) and at least two seed accounts to begin warmup."
                        action={
                            <LinkButton href="/warmup/connect">Add outreach mailbox</LinkButton>
                        }
                    />
                ) : (
                    <Stack gap={16}>
                        {needsSeeds && hasOutreach && (
                            <div className="skip-banner">
                                Add at least 2 seed mailboxes (Gmail, Outlook, etc.) before starting warmup.
                                <Link href="/warmup/connect" className="micro text-medium ml-8">
                                    Add seed mailbox →
                                </Link>
                            </div>
                        )}

                        <Grid cols={2}>
                            {mailboxes.map((mailbox) => (
                                <Card key={mailbox.id} title={mailbox.email}>
                                    <Stack gap={12}>
                                        <div className="flex flex-wrap gap-8 items-center">
                                            <span className="micro text-medium capitalize">{mailbox.provider}</span>
                                            {mailbox.is_outreach_mailbox && (
                                                <Badge className="badge-neutral">Outreach</Badge>
                                            )}
                                            {mailbox.is_seed_mailbox && (
                                                <Badge className="badge-neutral">Seed</Badge>
                                            )}
                                            <span className={`micro ${statusClass(mailbox.status)}`}>
                                                {STATUS_LABELS[mailbox.status] ?? mailbox.status}
                                            </span>
                                        </div>

                                        {mailbox.is_outreach_mailbox && (
                                            <div className="flex items-center gap-12">
                                                <ScoreBadge
                                                    value={mailbox.deliverability_score ?? '—'}
                                                    withBar
                                                />
                                                <span className="micro text-muted">
                                                    {mailbox.days_warming} days warming · {mailbox.sends_today} sent today
                                                </span>
                                            </div>
                                        )}

                                        <div className="flex flex-wrap gap-8">
                                            {mailbox.is_outreach_mailbox &&
                                                mailbox.status === 'pending' &&
                                                !needsSeeds && (
                                                    <Button
                                                        type="button"
                                                        onClick={() => startWarmup(mailbox.id)}
                                                    >
                                                        Start warmup
                                                    </Button>
                                                )}

                                            {mailbox.is_outreach_mailbox &&
                                                ['warming', 'ready', 'paused'].includes(mailbox.status) && (
                                                    <Button
                                                        type="button"
                                                        kind="secondary"
                                                        onClick={() => togglePause(mailbox.id)}
                                                    >
                                                        {mailbox.status === 'paused' ? 'Resume' : 'Pause'}
                                                    </Button>
                                                )}

                                            {mailbox.is_outreach_mailbox && (
                                                <LinkButton href={`/warmup/${mailbox.id}`} kind="secondary">
                                                    View details
                                                </LinkButton>
                                            )}

                                            <Button
                                                type="button"
                                                kind="ghost"
                                                onClick={() => removeMailbox(mailbox.id, mailbox.email)}
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                    </Stack>
                                </Card>
                            ))}
                        </Grid>
                    </Stack>
                )}
            </Page>
        </AuthenticatedLayout>
    );
}
