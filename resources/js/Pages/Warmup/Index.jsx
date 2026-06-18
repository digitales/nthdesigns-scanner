import { Head, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WarmupMailboxCard from '@/Pages/Warmup/components/WarmupMailboxCard';
import WarmupSetupAside from '@/Pages/Warmup/components/WarmupSetupAside';
import {
    EmptyState,
    Icon,
    Icons,
    LinkButton,
    Page,
    PageHeader,
    SidebarLayout,
    Stack,
    StatTile,
    StatsStrip,
} from '@/Components/ui';

export default function WarmupIndex({ mailboxes, seedCount }) {
    const { flash } = usePage().props;

    const outreachMailboxes = mailboxes.filter((m) => m.is_outreach_mailbox);
    const seedMailboxes = mailboxes.filter((m) => m.is_seed_mailbox);
    const hasOutreach = outreachMailboxes.length > 0;
    const needsSeeds = seedCount < 2;
    const warmingCount = outreachMailboxes.filter((m) => m.status === 'warming').length;
    const readyCount = outreachMailboxes.filter((m) => m.status === 'ready').length;

    const startWarmup = (id) => router.post(`/warmup/${id}/start`);
    const togglePause = (id) => router.post(`/warmup/${id}/toggle-pause`);
    const removeMailbox = (id, email) => {
        if (!window.confirm(`Remove ${email}? Send history will be deleted.`)) return;
        router.delete(`/warmup/${id}`);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Warmup" />

            <Page width="wide" className="page-wide">
                <PageHeader
                    eyebrow="Email warmup"
                    title="Domain reputation."
                    sub="Warm up outreach mailboxes before cold email. Connect your sending domain and seed accounts, then let the engine run for 2–4 weeks."
                    actions={<LinkButton href="/warmup/connect">Add mailbox</LinkButton>}
                />

                {flash?.success && (
                    <div className="skip-banner banner-positive banner-success">{flash.success}</div>
                )}

                {mailboxes.length > 0 && (
                    <StatsStrip>
                        <StatTile label="Connected" value={mailboxes.length} />
                        <StatTile label="Seed accounts" value={seedCount} warm={needsSeeds && hasOutreach} />
                        <StatTile label="Warming" value={warmingCount} />
                        <StatTile label="Ready" value={readyCount} warm={readyCount > 0} />
                    </StatsStrip>
                )}

                {needsSeeds && hasOutreach && (
                    <div className="skip-banner">
                        <Icon d={Icons.Lock} size={14} />
                        Add at least 2 seed mailboxes before starting warmup.
                    </div>
                )}

                {mailboxes.length === 0 ? (
                    <SidebarLayout>
                        <EmptyState
                            icon={Icons.Mail}
                            title="No mailboxes connected"
                            sub="Add your outreach mailbox and at least two seed accounts to begin building sending reputation."
                            action={<LinkButton href="/warmup/connect">Add outreach mailbox</LinkButton>}
                        />
                        <WarmupSetupAside
                            hasOutreach={false}
                            seedCount={0}
                            hasWarming={false}
                            hasReady={false}
                        />
                    </SidebarLayout>
                ) : (
                    <SidebarLayout>
                        <Stack gap={16}>
                            <section>
                                <div className="warmup-section-title">Outreach mailboxes</div>
                                {outreachMailboxes.length === 0 ? (
                                    <div className="warm-panel">
                                        <p className="micro m-0 mb-8">
                                            No outreach mailbox yet — connect the address you plan to send from.
                                        </p>
                                        <LinkButton href="/warmup/connect">Add outreach mailbox</LinkButton>
                                    </div>
                                ) : (
                                    <div className="warmup-mailbox-list">
                                        {outreachMailboxes.map((mailbox) => (
                                            <WarmupMailboxCard
                                                key={mailbox.id}
                                                mailbox={mailbox}
                                                needsSeeds={needsSeeds}
                                                onStart={startWarmup}
                                                onTogglePause={togglePause}
                                                onRemove={removeMailbox}
                                            />
                                        ))}
                                    </div>
                                )}
                            </section>

                            {seedMailboxes.some((m) => !m.is_outreach_mailbox) && (
                                <section>
                                    <div className="warmup-section-title">Seed accounts</div>
                                    <div className="warmup-mailbox-list">
                                        {seedMailboxes
                                            .filter((m) => !m.is_outreach_mailbox)
                                            .map((mailbox) => (
                                                <WarmupMailboxCard
                                                    key={mailbox.id}
                                                    mailbox={mailbox}
                                                    needsSeeds={false}
                                                    onStart={startWarmup}
                                                    onTogglePause={togglePause}
                                                    onRemove={removeMailbox}
                                                />
                                            ))}
                                    </div>
                                </section>
                            )}
                        </Stack>

                        <WarmupSetupAside
                            hasOutreach={hasOutreach}
                            seedCount={seedCount}
                            hasWarming={warmingCount > 0}
                            hasReady={readyCount > 0}
                        />
                    </SidebarLayout>
                )}
            </Page>
        </AuthenticatedLayout>
    );
}
