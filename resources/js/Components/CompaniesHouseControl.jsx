import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Button, Stack } from '@/Components/ui';

const STATUS_LABELS = {
    matched: 'Matched',
    no_match: 'No match',
    dissolved: 'Dissolved',
    caution: 'Caution',
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function requestCompaniesHouseCheck(prospectId) {
    const res = await fetch(`/prospects/${prospectId}/companies-house/check`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!res.ok && res.status !== 202) {
        throw new Error('Companies House check request failed');
    }

    return res.json();
}

export default function CompaniesHouseControl({ prospect }) {
    const [checking, setChecking] = useState(false);

    const hasRegistration = Boolean(
        prospect.registered_company_name || prospect.registered_company_number,
    );
    const wasCleared = Boolean(
        !hasRegistration && prospect.registered_company_cleared_at,
    );
    const [showForm, setShowForm] = useState(!hasRegistration && !wasCleared);
    const [confirmClear, setConfirmClear] = useState(false);
    const [form, setForm] = useState({
        name: prospect.registered_company_name ?? '',
        number: prospect.registered_company_number ?? '',
        note: prospect.registered_company_note ?? '',
    });

    const status = prospect.companies_house_status;

    const handleCheck = async () => {
        if (checking) {
            return;
        }

        setChecking(true);

        try {
            await requestCompaniesHouseCheck(prospect.id);
            router.reload({ only: ['prospect'], preserveScroll: true });
        } catch {
            setChecking(false);
        }
    };

    const saveRegistration = (e) => {
        e.preventDefault();
        router.post(`/prospects/${prospect.id}/registered-company`, {
            name: form.name.trim() || null,
            number: form.number.trim() || null,
            note: form.note.trim() || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowForm(false);
                setConfirmClear(false);
            },
        });
    };

    const clearRegistration = () => {
        router.delete(`/prospects/${prospect.id}/registered-company`, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmClear(false);
                setForm({ name: '', number: '', note: '' });
                setShowForm(true);
            },
        });
    };

    return (
        <Stack gap={12}>
            <Stack gap={10} className="registered-company-section">
                <p className="micro text-stone">Registered company</p>

                {wasCleared && (
                    <p className="micro text-stone">
                        Registration cleared
                        {prospect.registered_company_cleared_by_name
                            ? ` by ${prospect.registered_company_cleared_by_name}`
                            : ''}
                        {prospect.registered_company_cleared_at
                            ? ` on ${new Date(prospect.registered_company_cleared_at).toLocaleString()}`
                            : ''}
                        .
                    </p>
                )}

                {hasRegistration && !showForm && (
                    <Stack gap={6}>
                        {prospect.registered_company_name && (
                            <p className="micro">Name: {prospect.registered_company_name}</p>
                        )}
                        {prospect.registered_company_number && (
                            <p className="micro">Number: {prospect.registered_company_number}</p>
                        )}
                        {prospect.registered_company_note && (
                            <p className="micro text-stone">Note: {prospect.registered_company_note}</p>
                        )}
                        {prospect.registered_company_at && (
                            <p className="micro text-stone">
                                Registered
                                {prospect.registered_company_by_name
                                    ? ` by ${prospect.registered_company_by_name}`
                                    : ''}
                                {' '}on {new Date(prospect.registered_company_at).toLocaleString()}
                            </p>
                        )}
                        <Stack direction="row" gap={8}>
                            <Button kind="ghost" size="sm" onClick={() => setShowForm(true)}>Edit</Button>
                            {!confirmClear ? (
                                <Button kind="ghost" size="sm" onClick={() => setConfirmClear(true)}>Clear</Button>
                            ) : (
                                <>
                                    <Button kind="secondary" size="sm" onClick={clearRegistration}>Confirm clear</Button>
                                    <Button kind="ghost" size="sm" onClick={() => setConfirmClear(false)}>Cancel</Button>
                                </>
                            )}
                        </Stack>
                    </Stack>
                )}

                {showForm && (
                    <Stack as="form" gap={10} onSubmit={saveRegistration}>
                        <label className="micro">Registered company name</label>
                        <input
                            className="input"
                            value={form.name}
                            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                            placeholder="Legal entity name"
                        />
                        <label className="micro">Companies House number</label>
                        <input
                            className="input"
                            value={form.number}
                            onChange={(e) => setForm((f) => ({ ...f, number: e.target.value }))}
                            placeholder="8-character number"
                            maxLength={8}
                        />
                        <label className="micro">Note (optional)</label>
                        <textarea
                            className="textarea w-full"
                            rows={2}
                            value={form.note}
                            onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))}
                            placeholder="e.g. Found on website footer"
                        />
                        <Stack direction="row" gap={8}>
                            <Button kind="primary" size="sm" type="submit">Save registration</Button>
                            {hasRegistration && (
                                <Button kind="ghost" size="sm" type="button" onClick={() => setShowForm(false)}>
                                    Cancel
                                </Button>
                            )}
                        </Stack>
                    </Stack>
                )}
            </Stack>

            <Stack direction="row" gap={8} align="center" className="companies-house-control-header">
                {status ? (
                    <Badge className={`companies-house-badge companies-house-badge--${status}`}>
                        {STATUS_LABELS[status] ?? status}
                    </Badge>
                ) : (
                    <span className="micro">Not checked yet</span>
                )}
                {prospect.companies_house_number && (
                    <span className="micro text-stone">No. {prospect.companies_house_number}</span>
                )}
                {prospect.companies_house_checked_at && (
                    <span className="micro text-stone">
                        Checked {new Date(prospect.companies_house_checked_at).toLocaleString()}
                    </span>
                )}
            </Stack>

            {prospect.companies_house_summary && (
                <p className="micro">{prospect.companies_house_summary}</p>
            )}

            {(prospect.companies_house_flags ?? []).length > 0 && (
                <ul className="qualification-flags">
                    {(prospect.companies_house_flags ?? []).map((flag, index) => (
                        <li key={index}>{flag}</li>
                    ))}
                </ul>
            )}

            <Stack direction="row" gap={8} className="companies-house-control-actions">
                <Button
                    kind="secondary"
                    size="sm"
                    onClick={handleCheck}
                    disabled={checking}
                >
                    {checking ? 'Checking…' : status ? 'Recheck' : 'Check Companies House'}
                </Button>
            </Stack>
        </Stack>
    );
}
