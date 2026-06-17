import { useState } from 'react';
import { Button } from '@/Components/ui';

const STATUS_LABELS = {
    qualified: 'Qualified',
    caution: 'Caution',
    skip: 'Skip',
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function requestProspectQualification(prospectId) {
    const res = await fetch(`/prospects/${prospectId}/qualify`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!res.ok && res.status !== 202) {
        throw new Error('Qualification request failed');
    }

    return res.json();
}

export function qualifyProspectsWithStagger(prospectIds, onStart) {
    prospectIds.forEach((id, index) => {
        setTimeout(() => {
            onStart(id);
            requestProspectQualification(id).catch(() => {});
        }, index * 200);
    });
}

export default function QualificationControl({
    prospect,
    isPending = false,
    isExpanded = false,
    onToggleExpand,
    onQualifyStart,
}) {
    const [submitting, setSubmitting] = useState(false);
    const status = prospect.qualification_status;
    const checking = isPending || submitting;

    const handleQualify = async (e) => {
        e.stopPropagation();

        if (checking || status) {
            return;
        }

        setSubmitting(true);
        onQualifyStart?.(prospect.id);

        try {
            await requestProspectQualification(prospect.id);
        } catch {
            setSubmitting(false);
        }
    };

    const handleBadgeClick = (e) => {
        e.stopPropagation();
        onToggleExpand?.();
    };

    if (checking && !status) {
        return (
            <span className="qualification-pending">
                <span className="spinner spinner--sm" />
                Checking…
            </span>
        );
    }

    if (!status) {
        return (
            <Button
                kind="secondary"
                size="sm"
                className="btn-qualify"
                onClick={handleQualify}
            >
                Qualify
            </Button>
        );
    }

    return (
        <button
            type="button"
            className={`qualification-badge qualification-badge--${status}${isExpanded ? ' qualification-badge--open' : ''}`}
            onClick={handleBadgeClick}
            title="Show qualification details"
        >
            {STATUS_LABELS[status] ?? status}
        </button>
    );
}

export function QualificationDetails({ prospect }) {
    if (!prospect.qualification_status) {
        return null;
    }

    const flags = prospect.qualification_flags ?? [];

    return (
        <div className="qualification-details">
            {prospect.qualification_summary && (
                <p className="qualification-summary">{prospect.qualification_summary}</p>
            )}
            {flags.length > 0 && (
                <ul className="qualification-flags">
                    {flags.map((flag, i) => (
                        <li key={i}>{flag}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
