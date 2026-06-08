import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    AnglePill,
    Button,
    DataTable,
    EmptyState,
    Icon,
    Icons,
    PageHeader,
    RowActions,
    ScoreBadge,
    Select,
    Stack,
    Toast,
} from '@/Components/ui';

function ListTypeIcon({ type }) {
    const icon = type === 'smart' ? Icons.Filter : Icons.Bookmark;
    return <Icon d={icon} size={18} />;
}

export default function ListsShow({ list, rows = [], statuses = [], manualLists = [] }) {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(null);

    useEffect(() => {
        if (flash?.success) setToast(flash.success);
        if (flash?.shared_url) {
            navigator.clipboard.writeText(flash.shared_url);
            setToast('Share link copied to clipboard');
        }
    }, [flash]);

    const updateItem = (itemId, data) => {
        router.patch(`/lists/${list.id}/items/${itemId}`, data, { preserveScroll: true });
    };

    const removeItem = (itemId) => {
        router.delete(`/lists/${list.id}/items/${itemId}`, { preserveScroll: true });
    };

    const shareList = () => {
        router.post(`/lists/${list.id}/share`, {}, { preserveScroll: true });
    };

    const addToManualList = (targetListId, prospectId) => {
        router.post(`/lists/${targetListId}/items`, { prospect_ids: [prospectId] }, { preserveScroll: true });
    };

    const isManual = list.type === 'manual';

    return (
        <AuthenticatedLayout>
            <Head title={list.name} />

            <main className="page page-wide">
                <PageHeader
                    eyebrow={
                        <Stack direction="row" align="center" gap={8}>
                            <ListTypeIcon type={list.type} />
                            <span>{list.type_label}</span>
                        </Stack>
                    }
                    title={list.name}
                    sub={list.description || (isManual ? 'Track status and follow-up dates per prospect.' : 'Live results from your saved filter.')}
                    actions={
                        <Stack direction="row" gap={8}>
                            <Button kind="secondary" size="sm" icon={Icons.Share} onClick={shareList}>
                                Share
                            </Button>
                            <Link href="/lists" className="btn-ghost btn-sm">
                                All lists
                            </Link>
                        </Stack>
                    }
                />

                {rows.length === 0 ? (
                    <EmptyState
                        icon={Icons.List}
                        title="No prospects in this list."
                        sub={isManual ? 'Add prospects from search results or prospect detail.' : 'Adjust your smart filter rules.'}
                    />
                ) : (
                    <DataTable>
                        <thead>
                            <tr>
                                <th>Business</th>
                                <th>Niche / City</th>
                                <th>Combined</th>
                                {isManual && <th>Status</th>}
                                {isManual && <th>Follow-up</th>}
                                <th className="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr
                                    key={row.prospect.id}
                                    className={row.is_overdue ? 'overdue' : ''}
                                    onClick={() => router.visit(`/prospects/${row.prospect.id}?from=list&list_id=${list.id}`)}
                                >
                                    <td className="biz">{row.prospect.business_name}</td>
                                    <td className="micro">
                                        {row.prospect.niche} · {row.prospect.city}
                                    </td>
                                    <td>
                                        <ScoreBadge value={row.prospect.combined_score} withBar={false} />
                                    </td>
                                    {isManual && (
                                        <td onClick={(e) => e.stopPropagation()}>
                                            <Select
                                                value={row.status}
                                                onChange={(e) => updateItem(row.item_id, { status: e.target.value })}
                                            >
                                                {statuses.map((s) => (
                                                    <option key={s.value} value={s.value}>{s.label}</option>
                                                ))}
                                            </Select>
                                        </td>
                                    )}
                                    {isManual && (
                                        <td onClick={(e) => e.stopPropagation()}>
                                            <input
                                                type="date"
                                                className="input input--compact"
                                                value={row.follow_up_at ?? ''}
                                                onChange={(e) => updateItem(row.item_id, { follow_up_at: e.target.value || null })}
                                            />
                                        </td>
                                    )}
                                    <td className="text-right" onClick={(e) => e.stopPropagation()}>
                                        <RowActions>
                                            {!isManual && manualLists.length > 0 && (
                                                <Select
                                                    defaultValue=""
                                                    onChange={(e) => {
                                                        if (e.target.value) addToManualList(e.target.value, row.prospect.id);
                                                        e.target.value = '';
                                                    }}
                                                >
                                                    <option value="">Save to…</option>
                                                    {manualLists.map((l) => (
                                                        <option key={l.id} value={l.id}>{l.name}</option>
                                                    ))}
                                                </Select>
                                            )}
                                            {isManual && (
                                                <button
                                                    type="button"
                                                    className="btn-ghost btn-xs"
                                                    onClick={() => removeItem(row.item_id)}
                                                >
                                                    Remove
                                                </button>
                                            )}
                                        </RowActions>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                )}

                {toast && <Toast onClose={() => setToast(null)}>{toast}</Toast>}
            </main>
        </AuthenticatedLayout>
    );
}
