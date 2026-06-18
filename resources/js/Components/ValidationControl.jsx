import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Button, Stack } from '@/Components/ui';

const STATUS_LABELS = {
    high_chance: 'High chance',
    low_chance: 'Low chance',
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

function suggestPattern(prospect) {
    const franchiseFlag = (prospect.validator_flags ?? []).find((flag) => flag.startsWith('franchise_signal:'));
    if (franchiseFlag) {
        return franchiseFlag.split(':')[1] ?? '';
    }

    const name = prospect.business_name ?? '';
    const firstWord = name.split(/\s+/)[0] ?? '';

    return firstWord.length >= 3 ? firstWord.toLowerCase() : '';
}

export default function ValidationControl({ prospect }) {
    const [validating, setValidating] = useState(false);
    const [showOverrideForm, setShowOverrideForm] = useState(false);
    const [showSignalForm, setShowSignalForm] = useState(false);
    const [overrideStatus, setOverrideStatus] = useState('high_chance');
    const [overrideNote, setOverrideNote] = useState('');
    const [signalPattern, setSignalPattern] = useState('');
    const [signalLabel, setSignalLabel] = useState('');
    const [signalNotes, setSignalNotes] = useState('');

    const status = prospect.validator_status;
    const hasOverride = Boolean(prospect.validator_override_status);

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
        <Stack gap={12}>
            <Stack direction="row" gap={8} align="center" className="validation-control-header">
                {status ? (
                    <Badge className={`validation-badge validation-badge--${status}`}>
                        {STATUS_LABELS[status] ?? status}
                    </Badge>
                ) : (
                    <span className="micro">Not validated yet</span>
                )}
                {hasOverride && (
                    <Badge>Operator override</Badge>
                )}
                {prospect.validator_ran_at && (
                    <span className="micro text-stone">
                        Ran {new Date(prospect.validator_ran_at).toLocaleString()}
                    </span>
                )}
            </Stack>

            {prospect.validator_summary && (
                <p className="micro">{prospect.validator_summary}</p>
            )}

            {hasOverride && prospect.validator_override_note && (
                <p className="micro text-stone">Override note: {prospect.validator_override_note}</p>
            )}

            {(prospect.validator_flags ?? []).length > 0 && (
                <ul className="qualification-flags">
                    {(prospect.validator_flags ?? []).map((flag, index) => (
                        <li key={index}>{flag}</li>
                    ))}
                </ul>
            )}

            <Stack direction="row" gap={8} className="validation-control-actions">
                <Button
                    kind="secondary"
                    size="sm"
                    onClick={handleRevalidate}
                    disabled={validating}
                >
                    {validating ? 'Validating…' : 'Re-validate'}
                </Button>
                {!showOverrideForm ? (
                    <Button kind="ghost" size="sm" onClick={() => setShowOverrideForm(true)}>
                        Override…
                    </Button>
                ) : null}
                {hasOverride && (
                    <Button kind="ghost" size="sm" onClick={clearOverride}>
                        Clear override
                    </Button>
                )}
                <Button kind="ghost" size="sm" onClick={openSignalForm}>
                    Add global signal…
                </Button>
            </Stack>

            {showOverrideForm && (
                <Stack as="form" gap={10} onSubmit={saveOverride}>
                    <label className="micro">Override status</label>
                    <select
                        className="input"
                        value={overrideStatus}
                        onChange={(e) => setOverrideStatus(e.target.value)}
                    >
                        <option value="high_chance">High chance</option>
                        <option value="low_chance">Low chance</option>
                    </select>
                    <label className="micro">Note (optional)</label>
                    <textarea
                        className="textarea w-full"
                        rows={2}
                        value={overrideNote}
                        onChange={(e) => setOverrideNote(e.target.value)}
                        placeholder="Why this override applies"
                    />
                    <Stack direction="row" gap={8}>
                        <Button kind="primary" size="sm" type="submit">Save override</Button>
                        <Button kind="ghost" size="sm" type="button" onClick={() => setShowOverrideForm(false)}>
                            Cancel
                        </Button>
                    </Stack>
                </Stack>
            )}

            {showSignalForm && (
                <Stack as="form" gap={10} onSubmit={saveSignal}>
                    <p className="micro">
                        Adds a franchise signal for all future validations. Matching prospects will be re-validated.
                    </p>
                    <label className="micro">Pattern</label>
                    <input
                        className="input"
                        value={signalPattern}
                        onChange={(e) => setSignalPattern(e.target.value)}
                        placeholder="e.g. smileworks"
                        required
                    />
                    <label className="micro">Label</label>
                    <input
                        className="input"
                        value={signalLabel}
                        onChange={(e) => setSignalLabel(e.target.value)}
                        placeholder="e.g. Smileworks Dental Group"
                        required
                    />
                    <label className="micro">Notes (optional)</label>
                    <textarea
                        className="textarea w-full"
                        rows={2}
                        value={signalNotes}
                        onChange={(e) => setSignalNotes(e.target.value)}
                        placeholder="Why this signal was added"
                    />
                    <Stack direction="row" gap={8}>
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
