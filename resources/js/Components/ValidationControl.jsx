import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Button, Eyebrow, Field, Stack } from '@/Components/ui';
import {
    formatValidatorFlag,
    groupValidatorFlags,
    shouldDisplayValidatorFlag,
} from '@/utils/validatorFlags';

const STATUS_LABELS = {
    high_chance: 'High chance',
    low_chance: 'Low chance',
};

const CALLOUT_CLASS = {
    high_chance: 'validation-callout--high_chance',
    low_chance: 'validation-callout--low_chance',
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function requestProspectValidation(prospectId) {
    const res = await fetch(`/prospects/${prospectId}/validate`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!res.ok && res.status !== 202) {
        throw new Error('Validation request failed');
    }

    return res.json();
}

export async function requestProspectForceQualification(prospectId) {
    const res = await fetch(`/prospects/${prospectId}/qualify`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ force: true }),
    });

    if (!res.ok && res.status !== 202) {
        throw new Error('Force qualification request failed');
    }

    return res.json();
}

function suggestPattern(prospect) {
    const franchiseFlag = (prospect.validator_flags ?? []).find((flag) => flag.startsWith('franchise_signal:'));
    if (franchiseFlag) {
        return franchiseFlag.split(':')[1] ?? '';
    }

    const name = prospect.business_name ?? '';
    const firstWord = name.split(/\s+/)[0] ?? '';

    return firstWord.length >= 3 ? firstWord.toLowerCase() : '';
}

function ValidationFlagGroup({ label, flags, tone }) {
    return (
        <div className="validation-flag-group">
            <div className="eyebrow eyebrow-spaced">{label}</div>
            <div className="validation-flags" role="list">
                {flags.map((flag) => (
                    <span
                        key={flag}
                        role="listitem"
                        className={`qualification-flag qualification-flag--${tone}`}
                    >
                        <span className="qualification-flag-mark" aria-hidden="true" />
                        {formatValidatorFlag(flag)}
                    </span>
                ))}
            </div>
        </div>
    );
}

function ValidationSignals({ flags, status }) {
    const visibleFlags = flags.filter((flag) => shouldDisplayValidatorFlag(flag, flags));

    if (visibleFlags.length === 0) {
        return null;
    }

    const groups = groupValidatorFlags(flags, status);
    const hasMixedSignals = groups.positive.length > 0 && groups.negative.length > 0;

    return (
        <div className="validation-signals">
            {hasMixedSignals ? (
                <>
                    {groups.positive.length > 0 && (
                        <ValidationFlagGroup
                            label="Positive signals"
                            flags={groups.positive}
                            tone="positive"
                        />
                    )}
                    {groups.negative.length > 0 && (
                        <ValidationFlagGroup
                            label="Concerns"
                            flags={groups.negative}
                            tone="negative"
                        />
                    )}
                    {groups.neutral.length > 0 && (
                        <ValidationFlagGroup
                            label="Other notes"
                            flags={groups.neutral}
                            tone="neutral"
                        />
                    )}
                </>
            ) : (
                <ValidationFlagGroup
                    label="Signals found"
                    flags={visibleFlags}
                    tone={status === 'high_chance' ? 'positive' : status === 'low_chance' ? 'negative' : 'neutral'}
                />
            )}
        </div>
    );
}

export default function ValidationControl({ prospect }) {
    const [validating, setValidating] = useState(false);
    const [qualifying, setQualifying] = useState(false);
    const [showOverrideForm, setShowOverrideForm] = useState(false);
    const [showSignalForm, setShowSignalForm] = useState(false);
    const [overrideStatus, setOverrideStatus] = useState('high_chance');
    const [overrideNote, setOverrideNote] = useState('');
    const [signalPattern, setSignalPattern] = useState('');
    const [signalLabel, setSignalLabel] = useState('');
    const [signalNotes, setSignalNotes] = useState('');

    const status = prospect.validator_status;
    const hasOverride = Boolean(prospect.validator_override_status);
    const flags = prospect.validator_flags ?? [];

    const handleRevalidate = async () => {
        if (validating) {
            return;
        }

        setValidating(true);

        try {
            await requestProspectValidation(prospect.id);
            router.reload({ only: ['prospect'], preserveScroll: true });
        } catch {
            setValidating(false);
        }
    };

    const handleForceQualify = async () => {
        if (qualifying) {
            return;
        }

        setQualifying(true);

        try {
            await requestProspectForceQualification(prospect.id);
            router.reload({ only: ['prospect'], preserveScroll: true });
        } catch {
            setQualifying(false);
        }
    };

    const saveOverride = (e) => {
        e.preventDefault();
        router.post(`/prospects/${prospect.id}/validator-override`, {
            status: overrideStatus,
            note: overrideNote.trim() || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowOverrideForm(false);
                setOverrideNote('');
            },
        });
    };

    const clearOverride = () => {
        router.delete(`/prospects/${prospect.id}/validator-override`, { preserveScroll: true });
    };

    const openSignalForm = () => {
        const pattern = suggestPattern(prospect);
        setSignalPattern(pattern);
        setSignalLabel(pattern ? pattern.replace(/\b\w/g, (c) => c.toUpperCase()) : '');
        setSignalNotes('');
        setShowSignalForm(true);
    };

    const saveSignal = (e) => {
        e.preventDefault();

        router.post('/settings/validation-rules', {
            pattern: signalPattern.trim(),
            label: signalLabel.trim(),
            notes: signalNotes.trim() || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowSignalForm(false);
            },
        });
    };

    return (
        <Stack gap={0} className="validation-control">
            <section className="validation-section" aria-labelledby="validation-assessment-heading">
                <div className="validation-section-head validation-section-head--row">
                    <div id="validation-assessment-heading">
                        <Eyebrow>Outreach fit</Eyebrow>
                        {prospect.validator_ran_at && (
                            <time
                                className="micro text-stone validation-ran-at"
                                dateTime={prospect.validator_ran_at}
                            >
                                Ran {new Date(prospect.validator_ran_at).toLocaleString()}
                            </time>
                        )}
                    </div>
                    <Stack direction="row" gap={8} align="center" className="validation-status-row">
                        {status ? (
                            <Badge className={`validation-badge validation-badge--${status}`}>
                                {STATUS_LABELS[status] ?? status}
                            </Badge>
                        ) : (
                            <span className="micro text-stone">Not validated yet</span>
                        )}
                        {hasOverride && (
                            <Badge className="validation-badge validation-badge--override">
                                Operator override
                            </Badge>
                        )}
                    </Stack>
                </div>

                {!status ? (
                    <div className="validation-empty">
                        <p className="micro text-stone">
                            Run validation to assess whether this prospect is worth cold outreach.
                        </p>
                    </div>
                ) : (
                    <>
                        {prospect.validator_summary && (
                            <div className={`validation-callout ${CALLOUT_CLASS[status] ?? 'validation-callout--neutral'}`}>
                                <p className="validation-summary">{prospect.validator_summary}</p>
                            </div>
                        )}

                        {hasOverride && prospect.validator_override_note && (
                            <p className="micro text-stone validation-override-note">
                                Override note: {prospect.validator_override_note}
                            </p>
                        )}

                        <ValidationSignals flags={flags} status={status} />
                    </>
                )}

                <Stack direction="row" gap={8} className="validation-control-actions">
                    <Button
                        kind="secondary"
                        size="sm"
                        type="button"
                        onClick={handleRevalidate}
                        disabled={validating}
                    >
                        {validating ? 'Validating…' : status ? 'Re-validate' : 'Validate'}
                    </Button>
                    <Button
                        kind="ghost"
                        size="sm"
                        type="button"
                        onClick={handleForceQualify}
                        disabled={qualifying}
                    >
                        {qualifying ? 'Qualifying…' : 'Force qualify'}
                    </Button>
                    {!showOverrideForm ? (
                        <Button kind="ghost" size="sm" type="button" onClick={() => setShowOverrideForm(true)}>
                            Override…
                        </Button>
                    ) : null}
                    {hasOverride && (
                        <Button kind="ghost" size="sm" type="button" onClick={clearOverride}>
                            Clear override
                        </Button>
                    )}
                    <Button kind="ghost" size="sm" type="button" onClick={openSignalForm}>
                        Add global signal…
                    </Button>
                </Stack>
            </section>

            {showOverrideForm && (
                <Stack as="form" gap={10} className="validation-form" onSubmit={saveOverride}>
                    <Field label="Override status" htmlFor="validator-override-status">
                        <select
                            id="validator-override-status"
                            className="input"
                            value={overrideStatus}
                            onChange={(e) => setOverrideStatus(e.target.value)}
                        >
                            <option value="high_chance">High chance</option>
                            <option value="low_chance">Low chance</option>
                        </select>
                    </Field>
                    <Field label="Note" hint="Optional" htmlFor="validator-override-note">
                        <textarea
                            id="validator-override-note"
                            className="textarea w-full"
                            rows={2}
                            value={overrideNote}
                            onChange={(e) => setOverrideNote(e.target.value)}
                            placeholder="Why this override applies"
                        />
                    </Field>
                    <Stack direction="row" gap={8} className="validation-form-actions">
                        <Button kind="primary" size="sm" type="submit">Save override</Button>
                        <Button kind="ghost" size="sm" type="button" onClick={() => setShowOverrideForm(false)}>
                            Cancel
                        </Button>
                    </Stack>
                </Stack>
            )}

            {showSignalForm && (
                <Stack as="form" gap={10} className="validation-form" onSubmit={saveSignal}>
                    <p className="micro text-stone validation-form-lead">
                        Adds a franchise signal for all future validations. Matching prospects will be re-validated.
                    </p>
                    <Field label="Pattern" htmlFor="validation-signal-pattern">
                        <input
                            id="validation-signal-pattern"
                            className="input"
                            value={signalPattern}
                            onChange={(e) => setSignalPattern(e.target.value)}
                            placeholder="e.g. smileworks"
                            required
                        />
                    </Field>
                    <Field label="Label" htmlFor="validation-signal-label">
                        <input
                            id="validation-signal-label"
                            className="input"
                            value={signalLabel}
                            onChange={(e) => setSignalLabel(e.target.value)}
                            placeholder="e.g. Smileworks Dental Group"
                            required
                        />
                    </Field>
                    <Field label="Notes" hint="Optional" htmlFor="validation-signal-notes">
                        <textarea
                            id="validation-signal-notes"
                            className="textarea w-full"
                            rows={2}
                            value={signalNotes}
                            onChange={(e) => setSignalNotes(e.target.value)}
                            placeholder="Why this signal was added"
                        />
                    </Field>
                    <Stack direction="row" gap={8} className="validation-form-actions">
                        <Button kind="primary" size="sm" type="submit">Add signal</Button>
                        <Button kind="ghost" size="sm" type="button" onClick={() => setShowSignalForm(false)}>
                            Cancel
                        </Button>
                    </Stack>
                </Stack>
            )}
        </Stack>
    );
}
