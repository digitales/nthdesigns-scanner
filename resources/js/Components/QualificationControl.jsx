import { useState } from 'react';
import { Button, Eyebrow } from '@/Components/ui';
import { groupQualificationFlags } from '@/utils/qualificationFlags';

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

    const status = prospect.qualification_status;
    const flags = prospect.qualification_flags ?? [];
    const groups = groupQualificationFlags(flags, status);
    const hasMixedSignals = status === 'caution'
        && groups.positive.length > 0
        && groups.negative.length > 0;

    return (
        <div className="qualification-details">
            <div className="qualification-details-top">
                <Eyebrow className="qualification-details-eyebrow">Qualification assessment</Eyebrow>
                {prospect.qualification_ran_at && (
                    <time
                        className="micro text-stone"
                        dateTime={prospect.qualification_ran_at}
                    >
                        Assessed {new Date(prospect.qualification_ran_at).toLocaleString()}
                    </time>
                )}
            </div>

            {prospect.qualification_summary && (
                <div className={`qualification-callout qualification-callout--${status}`}>
                    <p className="qualification-summary">{prospect.qualification_summary}</p>
                </div>
            )}

            {flags.length > 0 && (
                <div className="qualification-signals">
                    {hasMixedSignals ? (
                        <>
                            {groups.positive.length > 0 && (
                                <QualificationFlagGroup
                                    label="Positive signals"
                                    flags={groups.positive}
                                    tone="positive"
                                />
                            )}
                            {groups.negative.length > 0 && (
                                <QualificationFlagGroup
                                    label="Concerns"
                                    flags={groups.negative}
                                    tone="negative"
                                />
                            )}
                            {groups.neutral.length > 0 && (
                                <QualificationFlagGroup
                                    label="Other notes"
                                    flags={groups.neutral}
                                    tone="neutral"
                                />
                            )}
                        </>
                    ) : (
                        <QualificationFlagGroup
                            label="Signals found"
                            flags={flags}
                            tone={status === 'qualified' ? 'positive' : status === 'skip' ? 'negative' : 'neutral'}
                        />
                    )}
                </div>
            )}
        </div>
    );
}

function QualificationFlagGroup({ label, flags, tone }) {
    return (
        <div className="qualification-flag-group">
            <div className="eyebrow eyebrow-spaced">{label}</div>
            <div className="flag-wrap qualification-flag-wrap" role="list">
                {flags.map((flag) => (
                    <span
                        key={flag}
                        role="listitem"
                        className={`qualification-flag qualification-flag--${tone}`}
                    >
                        <span className="qualification-flag-mark" aria-hidden="true" />
                        {flag}
                    </span>
                ))}
            </div>
        </div>
    );
}
