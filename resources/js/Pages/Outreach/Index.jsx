import { Head, Link, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import OutreachEmailCard from '@/Components/OutreachEmailCard';
import {
    AnglePill,
    Button,
    Card,
    EmptyState,
    Field,
    Icon,
    Icons,
    Input,
    PageHeader,
    ScoreBadge,
    Segmented,
} from '@/Components/ui';

export default function OutreachIndex({ selection, emailsByProspect, defaults, flash }) {
    const { data, setData, post, processing } = useForm({
        agency_name: defaults.agency_name,
        pitch_angle: defaults.pitch_angle,
        cpc_benchmark: defaults.cpc_benchmark,
    });

    const skippedCount = selection.filter((s) => !s.report_ready).length;
    const eligibleCount = selection.filter((s) => s.report_ready).length;

    const removeFromQueue = (prospectId) => {
        router.delete(`/outreach/selections/${prospectId}`);
    };

    const clearQueue = () => router.delete('/outreach/selections');

    const generateAll = (e) => {
        e.preventDefault();
        post('/outreach/generate');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Outreach" />

            <main className="page page-wide">
                <PageHeader
                    eyebrow="D · Outreach workspace"
                    title={`${selection.length} prospect${selection.length !== 1 ? 's' : ''} in queue.`}
                    sub="Batch-generate personalised emails. Prospects without a report are skipped automatically."
                />

                {flash?.success && (
                    <div className="skip-banner" style={{ background: 'var(--color-positive-soft)', borderColor: 'oklch(0.58 0.11 145 / 0.25)', color: 'oklch(0.35 0.08 145)' }}>
                        {flash.success}
                    </div>
                )}

                {flash?.skipped?.length > 0 && (
                    <div className="skip-banner">
                        <Icon d={Icons.Lock} size={14} />
                        Skipped (no report): {flash.skipped.join(', ')}
                    </div>
                )}

                {skippedCount > 0 && (
                    <div className="skip-banner">
                        <Icon d={Icons.Lock} size={14} />
                        {skippedCount} prospect{skippedCount !== 1 ? 's' : ''} will be skipped — outreach requires an embedded link.
                    </div>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: '300px 1fr', gap: 32 }}>
                    <section>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                            <div className="card-title" style={{ margin: 0 }}>Queue</div>
                            {selection.length > 0 && (
                                <button type="button" className="micro" onClick={clearQueue} style={{ background: 'none', border: 'none', cursor: 'pointer' }}>
                                    Clear all
                                </button>
                            )}
                        </div>

                        {selection.length === 0 ? (
                            <EmptyState
                                icon={Icons.Mail}
                                title="Queue is empty."
                                sub="Add prospects from search results or saved list."
                                action={
                                    <Link href="/search">
                                        <Button kind="secondary" size="sm">Go to search</Button>
                                    </Link>
                                }
                            />
                        ) : (
                            <ul style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                {selection.map((item) => (
                                    <li key={item.id} className="queue-chip">
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div style={{ fontWeight: 500, fontSize: 13, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.business_name}</div>
                                            <div style={{ display: 'flex', gap: 8, marginTop: 6, alignItems: 'center' }}>
                                                <ScoreBadge value={item.combined_score} withBar={false} />
                                                <AnglePill angle={item.dominant_angle} />
                                            </div>
                                            <div className="micro" style={{ marginTop: 4 }}>
                                                {item.report_ready ? 'Report ready' : 'No report'}
                                            </div>
                                        </div>
                                        <button type="button" className="remove" onClick={() => removeFromQueue(item.prospect_id)} aria-label="Remove">
                                            ×
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section>
                        <Card title="Generate emails" style={{ marginBottom: 24 }}>
                        <form onSubmit={generateAll}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
                                <Field label="Pitch angle">
                                    <Segmented
                                        value={data.pitch_angle}
                                        onChange={(v) => setData('pitch_angle', v)}
                                        options={[
                                            { value: 'auto', label: 'Auto' },
                                            { value: 'gbp', label: 'GBP' },
                                            { value: 'accessibility', label: 'A11y' },
                                            { value: 'combined', label: 'Both' },
                                        ]}
                                    />
                                </Field>
                                <Field label="Agency name" hint="optional">
                                    <Input
                                        value={data.agency_name}
                                        onChange={(e) => setData('agency_name', e.target.value)}
                                        placeholder="nthdesigns"
                                    />
                                </Field>
                            </div>
                            <Field label="CPC benchmark" hint="optional">
                                <div className="input-with-prefix">
                                    <span className="prefix">£</span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={data.cpc_benchmark}
                                        onChange={(e) => setData('cpc_benchmark', e.target.value)}
                                    />
                                </div>
                            </Field>
                            <div style={{ marginTop: 20 }}>
                                <Button
                                    kind="primary"
                                    size="lg"
                                    type="submit"
                                    disabled={processing || selection.length === 0}
                                    icon={processing ? undefined : Icons.Send}
                                    className="w-full justify-center"
                                >
                                    {processing ? 'Generating…' : `Generate ${eligibleCount} email${eligibleCount !== 1 ? 's' : ''}`}
                                </Button>
                            </div>
                        </form>
                        </Card>

                        {selection.map((item) => {
                            const emails = emailsByProspect[item.prospect_id] ?? [];
                            if (emails.length === 0) return null;
                            return (
                                <div key={item.prospect_id} style={{ marginBottom: 24 }}>
                                    <h3 style={{ fontSize: 14, fontWeight: 500, marginBottom: 12 }}>{item.business_name}</h3>
                                    {emails.map((email) => (
                                        <div key={email.id} style={{ marginBottom: 16 }}>
                                            <OutreachEmailCard
                                                email={{ ...email, combined_score: item.combined_score }}
                                                reportUrl={item.report_url}
                                                performanceScore={item.performance_score}
                                            />
                                        </div>
                                    ))}
                                </div>
                            );
                        })}
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
