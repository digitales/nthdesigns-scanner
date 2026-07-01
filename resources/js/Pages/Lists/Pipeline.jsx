import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ListsSubNav from '@/Components/ListsSubNav';
import {
    AnglePill,
    Button,
    DataTable,
    EmptyState,
    Field,
    FilterBar,
    Icons,
    Input,
    PageHeader,
    Pagination,
    RowActions,
    ScoreBadge,
    Segmented,
    Select,
} from '@/Components/ui';

export default function ListsPipeline({ rows = [], filters = {}, meta, pagination }) {
    const setBookedFilter = (booked) => {
        router.get('/lists/pipeline', { ...filters, booked: booked ? 1 : undefined }, { preserveState: true });
    };

    const submitFilters = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        const params = Object.fromEntries(form.entries());
        if (filters.booked) {
            params.booked = 1;
        }
        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] == null) {
                delete params[key];
            }
        });
        router.get('/lists/pipeline', params, { preserveState: true });
    };

    const hasFilters =
        filters.niche ||
        filters.city ||
        filters.min_score ||
        filters.outreach_status ||
        (filters.sort && filters.sort !== 'report_age');

    return (
        <AuthenticatedLayout>
            <Head title="Outreach pipeline" />

            <main className="page page-wide">
                <PageHeader
                    eyebrow="Lists"
                    title={`${meta.total} prospect${meta.total !== 1 ? 's' : ''} in outreach queue.`}
                    sub="Read-only view of your outreach queue. Manage and refresh reports from the Outreach page."
                />

                <ListsSubNav active="pipeline" />

                <div className="queue-header mb-16">
                    <Segmented
                        value={filters.booked ? 'booked' : 'all'}
                        onChange={(value) => setBookedFilter(value === 'booked')}
                        options={[
                            { value: 'all', label: 'Outreach' },
                            { value: 'booked', label: 'Booked' },
                        ]}
                    />
                    <Link href="/outreach" className="btn-secondary btn-sm">
                        Open outreach →
                    </Link>
                </div>

                <FilterBar onSubmit={submitFilters}>
                    <Field label="Niche">
                        <Input type="text" name="niche" defaultValue={filters.niche ?? ''} />
                    </Field>
                    <Field label="City">
                        <Input type="text" name="city" defaultValue={filters.city ?? ''} />
                    </Field>
                    <Field label="Min score">
                        <Input type="number" name="min_score" min="0" max="100" defaultValue={filters.min_score ?? ''} />
                    </Field>
                    <Field label="Outreach status">
                        <Select name="outreach_status" defaultValue={filters.outreach_status ?? ''}>
                            <option value="">All</option>
                            <option value="none">No draft</option>
                            <option value="drafted">Drafted</option>
                            <option value="sent">Sent</option>
                        </Select>
                    </Field>
                    <Field label="Sort">
                        <Select name="sort" defaultValue={filters.sort ?? 'report_age'}>
                            <option value="report_age">Report age (oldest first)</option>
                            <option value="score">Score (highest first)</option>
                            <option value="name">Name (A–Z)</option>
                        </Select>
                    </Field>
                    <div className="filter-action">
                        <Button kind="secondary" size="sm" type="submit">
                            Apply
                        </Button>
                        <Link href={filters.booked ? '/lists/pipeline?booked=1' : '/lists/pipeline'} className="micro">
                            Reset
                        </Link>
                    </div>
                </FilterBar>

                {rows.length === 0 ? (
                    <EmptyState
                        icon={Icons.Mail}
                        title={hasFilters ? 'No prospects match these filters.' : 'Outreach queue is empty.'}
                        sub={hasFilters ? undefined : 'Add prospects from search results or a saved list.'}
                        action={
                            !hasFilters ? (
                                <Link href="/search">
                                    <Button kind="secondary" size="sm">
                                        Go to search
                                    </Button>
                                </Link>
                            ) : undefined
                        }
                    />
                ) : (
                    <>
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>Business</th>
                                    <th>Niche</th>
                                    <th>City</th>
                                    <th>Score</th>
                                    <th>Report age</th>
                                    <th>Outreach</th>
                                    <th>Booked</th>
                                    <th className="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id}>
                                        <td>
                                            <Link href={`/prospects/${row.prospect_id}`} className="table-link">
                                                {row.business_name}
                                            </Link>
                                            <div className="micro mt-4">
                                                <AnglePill angle={row.dominant_angle} />
                                            </div>
                                        </td>
                                        <td>{row.niche}</td>
                                        <td>{row.city}</td>
                                        <td>
                                            <ScoreBadge value={row.combined_score} withBar={false} />
                                        </td>
                                        <td>
                                            {row.report_age_label ? (
                                                <span className={row.report_stale ? 'text-warning' : undefined}>
                                                    {row.report_age_label}
                                                    {row.report_stale ? ' · Stale' : ''}
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td>{row.outreach_status_label}</td>
                                        <td>{row.booked_label ?? '—'}</td>
                                        <td className="text-right">
                                            <RowActions>
                                                <Link href={`/prospects/${row.prospect_id}`} className="btn-ghost btn-xs">
                                                    View
                                                </Link>
                                                <Link href="/outreach" className="btn-ghost btn-xs">
                                                    Open in outreach
                                                </Link>
                                            </RowActions>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                        <Pagination pagination={pagination} href="/lists/pipeline" query={filters} />
                    </>
                )}
            </main>
        </AuthenticatedLayout>
    );
}
