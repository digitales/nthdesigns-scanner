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

    return (
        <Stack gap={12}>
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
