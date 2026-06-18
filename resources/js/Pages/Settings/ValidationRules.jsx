import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Card, Field, FormError, Input, Page, PageHeader, Stack } from '@/Components/ui';

export default function ValidationRules({ builtInSignals, operatorSignals }) {
    const { flash } = usePage().props;
    const createForm = useForm({
        pattern: '',
        label: '',
        notes: '',
    });
    const [showBuiltIn, setShowBuiltIn] = useState(false);

    const createSignal = (e) => {
        e.preventDefault();
        createForm.post('/settings/validation-rules', {
            onSuccess: () => createForm.reset(),
        });
    };

    const toggleActive = (signal) => {
        router.patch(`/settings/validation-rules/${signal.id}`, {
            active: !signal.active,
        });
    };

    const deactivateSignal = (signal) => {
        if (!confirm(`Deactivate "${signal.label}"? Previously matched prospects will be re-validated.`)) {
            return;
        }

        router.delete(`/settings/validation-rules/${signal.id}`);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Validation rules" />

            <Page width="narrow">
                <PageHeader
                    eyebrow="Settings"
                    title="Validation rules"
                    sub="Franchise and corporate signals used when scoring outreach viability. Built-in rules ship with the app; add operator signals as you discover new chains."
                />

                <p className="micro mb-16">
                    <Link href="/settings">← Back to settings</Link>
                </p>

                {flash?.success && (
                    <p className="micro text-positive mb-16">
                        {flash.success}
                    </p>
                )}

                <Card title="Built-in signals" className="mb-24">
                    <p className="micro mb-8">
                        {builtInSignals.length} patterns from config — updated on deploy.
                    </p>
                    <Button kind="ghost" size="sm" onClick={() => setShowBuiltIn((current) => !current)}>
                        {showBuiltIn ? 'Hide list' : 'Show list'}
                    </Button>
                    {showBuiltIn && (
                        <ul className="qualification-flags mt-12">
                            {builtInSignals.map((pattern) => (
                                <li key={pattern}>{pattern}</li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Operator signals" className="mb-24">
                    {operatorSignals.length === 0 ? (
                        <p className="micro">No operator signals yet.</p>
                    ) : (
                        <ul className="settings-list">
                            {operatorSignals.map((signal) => (
                                <li key={signal.id} className="settings-list-item">
                                    <div>
                                        <strong className="body-sm-medium">{signal.label}</strong>
                                        <p className="micro">
                                            Pattern: <code>{signal.pattern}</code>
                                            {' · '}
                                            {signal.active ? 'Active' : 'Inactive'}
                                            {' · '}
                                            Added by {signal.created_by} · {signal.created_at}
                                        </p>
                                        {signal.notes && (
                                            <p className="micro text-stone">{signal.notes}</p>
                                        )}
                                    </div>
                                    <Stack direction="row" gap={8}>
                                        <Button kind="ghost" size="sm" onClick={() => toggleActive(signal)}>
                                            {signal.active ? 'Deactivate' : 'Activate'}
                                        </Button>
                                        {signal.active && (
                                            <Button kind="ghost" size="sm" onClick={() => deactivateSignal(signal)}>
                                                Remove
                                            </Button>
                                        )}
                                    </Stack>
                                </li>
                            ))}
                        </ul>
                    )}

                    <form onSubmit={createSignal} className="settings-form-divider">
                        <Stack gap={12}>
                            <Field label="Pattern">
                                <Input
                                    type="text"
                                    value={createForm.data.pattern}
                                    onChange={(e) => createForm.setData('pattern', e.target.value)}
                                    placeholder="e.g. smileworks"
                                    required
                                />
                                <FormError message={createForm.errors.pattern} />
                            </Field>
                            <Field label="Label">
                                <Input
                                    type="text"
                                    value={createForm.data.label}
                                    onChange={(e) => createForm.setData('label', e.target.value)}
                                    placeholder="e.g. Smileworks Dental Group"
                                    required
                                />
                                <FormError message={createForm.errors.label} />
                            </Field>
                            <Field label="Notes (optional)">
                                <Input
                                    type="text"
                                    value={createForm.data.notes}
                                    onChange={(e) => createForm.setData('notes', e.target.value)}
                                    placeholder="Why this signal was added"
                                />
                                <FormError message={createForm.errors.notes} />
                            </Field>
                            <Button kind="primary" size="sm" type="submit" disabled={createForm.processing}>
                                {createForm.processing ? 'Adding…' : 'Add signal'}
                            </Button>
                        </Stack>
                    </form>
                </Card>
            </Page>
        </AuthenticatedLayout>
    );
}
