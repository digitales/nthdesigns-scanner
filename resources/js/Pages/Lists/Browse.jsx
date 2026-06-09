import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    AnglePill,
    Button,
    Card,
    DataTable,
    EmptyState,
    Field,
    FilterBar,
    Grid,
    IconButton,
    Icons,
    Input,
    PageHeader,
    Pagination,
    RowActions,
    ScoreBadge,
    Select,
    Stack,
    Toast,
} from '@/Components/ui';

export default function ListsBrowse({ prospects, warmLeads, filters, meta, pagination, manualLists = [] }) {
    const [toast, setToast] = useState(null);

    const submitFilters = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        const params = Object.fromEntries(form.entries());
        if (!params.warm) delete params.warm;
        router.get('/lists/browse', params, { preserveState: true });
    };

    const addToOutreach = (prospectId) => {
        router.post('/outreach/selections', { prospect_ids: [prospectId] });
    };

    const addToList = (listId, prospectId) => {
        router.post(`/lists/${listId}/items`, { prospect_ids: [prospectId] }, { preserveScroll: true });
    };

    const exportCsv = () => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/exports';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrf) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = csrf;
            form.appendChild(input);
        }
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== '' && value != null) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
        });
        document.body.appendChild(form);
        form.submit();
        form.remove();
    };

    return (
        <AuthenticatedLayout>
            <Head title="Browse prospects" />

            <main className="page page-wide">
                <PageHeader
                    eyebrow="Lists"
                    title={`${meta.total} prospect${meta.total !== 1 ? 's' : ''} across searches.`}
                    sub="Filter all prospects. Save matches as a smart list from the Lists hub."
                    actions={
                        <Stack direction="row" gap={8}>
                            <Link href="/lists" className="btn-ghost btn-sm">My lists</Link>
                            <Button kind="secondary" size="sm" icon={Icons.Download} onClick={exportCsv}>
                                Export CSV
                            </Button>
                        </Stack>
                    }
                />

                {warmLeads.length > 0 && !filters.warm && (
                    <section className="warm-panel">
                        <Stack direction="row" justify="between" align="center" className="mb-16">
                            <div className="card-title card-title--flush">Warm leads</div>
                            <Link href="/lists/browse?warm=1" className="micro text-accent-deep">
                                Filter to warm →
                            </Link>
                        </Stack>
                        <Grid cols={3} gap={12}>
                            {warmLeads.slice(0, 3).map((p) => (
                                <Link key={p.id} href={`/prospects/${p.id}`} className="warm-lead-link">
                                    <Card pad className="card--compact">
                                        <div className="warm-lead-name">{p.business_name}</div>
                                        <div className="micro mt-4">{p.niche} · {p.city}</div>
                                        <div className="mt-10">
                                            <ScoreBadge value={p.combined_score} withBar={false} />
                                        </div>
                                    </Card>
                                </Link>
                            ))}
                        </Grid>
                    </section>
                )}

                <FilterBar onSubmit={submitFilters}>
                    <Field label="From">
                        <Input type="date" name="from" defaultValue={filters.from ?? ''} />
                    </Field>
                    <Field label="To">
                        <Input type="date" name="to" defaultValue={filters.to ?? ''} />
                    </Field>
                    <Field label="Niche">
                        <Input type="text" name="niche" defaultValue={filters.niche ?? ''} />
                    </Field>
                    <Field label="City">
                        <Input type="text" name="city" defaultValue={filters.city ?? ''} />
                    </Field>
                    <Field label="Min score">
                        <Input type="number" name="min_score" min="0" max="100" defaultValue={filters.min_score ?? ''} />
                    </Field>
                    <Field label="Warm">
                        <label className="filter-checkbox-label">
                            <input type="checkbox" className="checkbox" name="warm" value="1" defaultChecked={!!filters.warm} />
                            Warm only
                        </label>
                    </Field>
                    <div className="filter-action">
                        <Button kind="primary" size="sm" type="submit">Apply</Button>
                        <Link href="/lists/browse" className="micro">Reset</Link>
                    </div>
                </FilterBar>

                {prospects.length === 0 ? (
                    <EmptyState icon={Icons.Search} title="No prospects match these filters." />
                ) : (
                    <DataTable>
                        <thead>
                            <tr>
                                <th>Business</th>
                                <th>Niche / City</th>
                                <th>Combined</th>
                                <th>Angle</th>
                                <th className="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {prospects.map((p) => (
                                <tr key={p.id} className={p.is_warm ? 'warm' : ''} onClick={() => router.visit(`/prospects/${p.id}`)}>
                                    <td className="biz">{p.business_name}</td>
                                    <td className="micro">{p.niche} · {p.city}</td>
                                    <td><ScoreBadge value={p.combined_score} withBar={false} /></td>
                                    <td><AnglePill angle={p.dominant_angle} /></td>
                                    <td className="text-right" onClick={(e) => e.stopPropagation()}>
                                        <RowActions>
                                            {manualLists.length > 0 && (
                                                <Select
                                                    defaultValue=""
                                                    onChange={(e) => {
                                                        if (e.target.value) addToList(e.target.value, p.id);
                                                        e.target.value = '';
                                                    }}
                                                >
                                                    <option value="">Add to list…</option>
                                                    {manualLists.map((l) => (
                                                        <option key={l.id} value={l.id}>{l.name}</option>
                                                    ))}
                                                </Select>
                                            )}
                                            <button type="button" className="btn-ghost btn-xs" onClick={() => addToOutreach(p.id)}>
                                                + Queue
                                            </button>
                                        </RowActions>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                )}

                <Pagination pagination={pagination} href="/lists/browse" query={filters} />

                {toast && <Toast onClose={() => setToast(null)}>{toast}</Toast>}
            </main>
        </AuthenticatedLayout>
    );
}
