import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Button,
    Card,
    EmptyState,
    Field,
    Icon,
    Icons,
    Input,
    PageHeader,
    Select,
    Stack,
    Toast,
} from '@/Components/ui';

function ListTypeIcon({ type }) {
    const icon = type === 'smart' ? Icons.Filter : Icons.Bookmark;
    const label = type === 'smart' ? 'Smart filter' : 'Manual list';
    return (
        <span className="list-type-icon" title={label}>
            <Icon d={icon} size={18} />
        </span>
    );
}

export default function ListsIndex({ lists = [], sort = 'updated', tagSuggestions = [] }) {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(flash?.success ?? null);
    const [creating, setCreating] = useState(false);
    const form = useForm({
        name: '',
        type: 'manual',
        description: '',
        'filter.niche': '',
        'filter.city': '',
        'filter.min_score': '',
    });

    const submitCreate = (e) => {
        e.preventDefault();
        const payload = {
            name: form.data.name,
            type: form.data.type,
            description: form.data.description || null,
        };
        if (form.data.type === 'smart') {
            payload.filter = {
                niche: form.data['filter.niche'] || null,
                city: form.data['filter.city'] || null,
                min_score: form.data['filter.min_score'] ? Number(form.data['filter.min_score']) : null,
            };
        }
        router.post('/lists', payload, {
            onSuccess: () => {
                setCreating(false);
                form.reset();
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Lists" />

            <main className="page page-wide">
                <PageHeader
                    eyebrow="Lists"
                    title="Curate prospects and track follow-up."
                    sub="Manual lists for hand-picked outreach. Smart lists save filter rules and refresh automatically."
                    actions={
                        <Stack direction="row" gap={8}>
                            <Link href="/lists/browse" className="btn-secondary btn-sm">
                                Browse all prospects
                            </Link>
                            <Button kind="primary" size="sm" icon={Icons.Plus} onClick={() => setCreating(true)}>
                                New list
                            </Button>
                        </Stack>
                    }
                />

                <Stack direction="row" gap={8} className="mb-16">
                    <Button
                        kind={sort === 'updated' ? 'primary' : 'ghost'}
                        size="sm"
                        onClick={() => router.get('/lists', { sort: 'updated' })}
                    >
                        Recently updated
                    </Button>
                    <Button
                        kind={sort === 'due_soon' ? 'primary' : 'ghost'}
                        size="sm"
                        onClick={() => router.get('/lists', { sort: 'due_soon' })}
                    >
                        Due soon
                    </Button>
                </Stack>

                {creating && (
                    <Card title="Create list" className="mb-24">
                        <form onSubmit={submitCreate}>
                            <Stack gap={12}>
                                <Field label="Name">
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        required
                                    />
                                </Field>
                                <Field label="Type">
                                    <Select
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                    >
                                        <option value="manual">Manual list</option>
                                        <option value="smart">Smart filter</option>
                                    </Select>
                                </Field>
                                {form.data.type === 'smart' && (
                                    <>
                                        <Field label="Niche filter">
                                            <Input
                                                value={form.data['filter.niche']}
                                                onChange={(e) => form.setData('filter.niche', e.target.value)}
                                            />
                                        </Field>
                                        <Field label="City filter">
                                            <Input
                                                value={form.data['filter.city']}
                                                onChange={(e) => form.setData('filter.city', e.target.value)}
                                            />
                                        </Field>
                                        <Field label="Min score">
                                            <Input
                                                type="number"
                                                min="0"
                                                max="100"
                                                value={form.data['filter.min_score']}
                                                onChange={(e) => form.setData('filter.min_score', e.target.value)}
                                            />
                                        </Field>
                                    </>
                                )}
                                <Stack direction="row" gap={8}>
                                    <Button kind="primary" size="sm" type="submit" disabled={form.processing}>
                                        Create
                                    </Button>
                                    <Button kind="ghost" size="sm" type="button" onClick={() => setCreating(false)}>
                                        Cancel
                                    </Button>
                                </Stack>
                            </Stack>
                        </form>
                    </Card>
                )}

                {lists.length === 0 ? (
                    <EmptyState
                        icon={Icons.List}
                        title="No lists yet."
                        sub="Create a manual list to track follow-up, or a smart filter to watch matching prospects."
                    />
                ) : (
                    <div className="lists-grid">
                        {lists.map((list) => (
                            <Link key={list.id} href={`/lists/${list.id}`} className="list-card-link">
                                <Card pad className="card--compact">
                                    <Stack direction="row" align="center" gap={8}>
                                        <ListTypeIcon type={list.type} />
                                        <div className="card-title card-title--flush">{list.name}</div>
                                        {list.overdue_count > 0 && (
                                            <span className="badge badge--warn">{list.overdue_count} overdue</span>
                                        )}
                                    </Stack>
                                    <div className="micro mt-8">
                                        {list.type_label}
                                        {list.prospect_count != null && ` · ${list.prospect_count} prospects`}
                                        {list.type === 'smart' && list.prospect_count == null && ' · dynamic'}
                                    </div>
                                    <div className="micro text-stone mt-4">Updated {list.updated_at}</div>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}

                {toast && <Toast onClose={() => setToast(null)}>{toast}</Toast>}
            </main>
        </AuthenticatedLayout>
    );
}
