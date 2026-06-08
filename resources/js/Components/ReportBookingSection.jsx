import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button, Field, Input, Textarea } from '@/Components/ui';

function bookingErrorMessage(status, data) {
    if (status === 409) {
        return data.message ?? 'That time was just taken. Please choose another slot.';
    }
    if (status === 503) {
        return data.message ?? 'Booking is temporarily unavailable. Please try again shortly.';
    }
    if (status === 422) {
        return data.message ?? 'Please check your details and try again.';
    }

    return data.message ?? 'Booking failed. Please try another time.';
}

function parseSlot(slot) {
    if (!slot || typeof slot !== 'object') {
        return null;
    }

    const startsAt = typeof slot.starts_at === 'string' ? slot.starts_at : null;
    const label = typeof slot.label === 'string' ? slot.label : '';

    if (!startsAt || !label) {
        return null;
    }

    const [dayLabel = label, timeLabel = ''] = label.split(', ');

    return { ...slot, starts_at: startsAt, label, dayLabel, timeLabel };
}

function groupSlotsByDay(slots) {
    const groups = new Map();

    for (const slot of slots.map(parseSlot).filter(Boolean)) {
        const dateKey = slot.starts_at.slice(0, 10);

        if (!groups.has(dateKey)) {
            groups.set(dateKey, { dateKey, dayLabel: slot.dayLabel, slots: [] });
        }

        groups.get(dateKey).slots.push(slot);
    }

    return Array.from(groups.values());
}

function preferredSlotForDay(daySlots) {
    const preferredTimes = ['10:00 AM', '2:00 PM', '11:00 AM', '3:00 PM'];

    for (const time of preferredTimes) {
        const match = daySlots.find((slot) => slot.timeLabel === time);

        if (match) {
            return match;
        }
    }

    return daySlots[Math.min(1, daySlots.length - 1)] ?? daySlots[0];
}

function curateSuggestions(slots, max = 3) {
    try {
        const suggestions = groupSlotsByDay(slots)
            .slice(0, max)
            .map((day) => preferredSlotForDay(day.slots))
            .filter(Boolean);

        if (suggestions.length > 0) {
            return suggestions;
        }
    } catch {
        // Fall through to raw slots below.
    }

    return slots.filter((slot) => parseSlot(slot)).slice(0, max);
}

function BookingSlotButton({ slot, selected, onSelect, children }) {
    const parsed = parseSlot(slot) ?? slot;

    return (
        <button
            type="button"
            role="option"
            aria-selected={selected?.starts_at === parsed.starts_at}
            onClick={() => onSelect(parsed)}
            className={`booking-slot${selected?.starts_at === parsed.starts_at ? ' booking-slot--selected' : ''}`}
        >
            {children ?? parsed.label}
        </button>
    );
}

function BookingForm({ form, setForm, submit, submitting }) {
    return (
        <form onSubmit={submit} className="booking-form">
            <Field label="Your name" htmlFor="booking-attendee-name">
                <Input
                    id="booking-attendee-name"
                    required
                    value={form.attendee_name}
                    onChange={(e) => setForm((f) => ({ ...f, attendee_name: e.target.value }))}
                />
            </Field>
            <Field label="Email" htmlFor="booking-attendee-email">
                <Input
                    id="booking-attendee-email"
                    type="email"
                    required
                    value={form.attendee_email}
                    onChange={(e) => setForm((f) => ({ ...f, attendee_email: e.target.value }))}
                />
            </Field>
            <Field label="Phone (optional)" htmlFor="booking-attendee-phone">
                <Input
                    id="booking-attendee-phone"
                    value={form.attendee_phone}
                    onChange={(e) => setForm((f) => ({ ...f, attendee_phone: e.target.value }))}
                />
            </Field>
            <Field label="Anything we should know? (optional)" htmlFor="booking-note">
                <Textarea
                    id="booking-note"
                    value={form.note}
                    onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))}
                />
            </Field>
            <div className="booking-panel--center">
                <Button kind="accent" size="lg" type="submit" disabled={submitting}>
                    {submitting ? 'Booking…' : 'Confirm booking'}
                </Button>
            </div>
        </form>
    );
}

function SlotsDayFirst({ slots, selected, onSelect, timezoneLabel, showIntro = true }) {
    const days = useMemo(() => groupSlotsByDay(slots), [slots]);
    const [activeDayKey, setActiveDayKey] = useState(days[0]?.dateKey ?? null);

    useEffect(() => {
        if (!days.some((day) => day.dateKey === activeDayKey)) {
            setActiveDayKey(days[0]?.dateKey ?? null);
        }
    }, [days, activeDayKey]);

    const activeDay = days.find((day) => day.dateKey === activeDayKey);

    return (
        <>
            {showIntro && <p className="micro mb-8">Pick a day, then a time ({timezoneLabel})</p>}
            <div className="booking-day-picker" role="tablist" aria-label="Available days">
                {days.map((day) => (
                    <button
                        key={day.dateKey}
                        type="button"
                        role="tab"
                        aria-selected={activeDayKey === day.dateKey}
                        onClick={() => setActiveDayKey(day.dateKey)}
                        className={`booking-day-pill${activeDayKey === day.dateKey ? ' booking-day-pill--selected' : ''}`}
                    >
                        {day.dayLabel}
                    </button>
                ))}
            </div>
            {activeDay && (
                <div className="booking-time-row" role="listbox" aria-label={`Times on ${activeDay.dayLabel}`}>
                    {activeDay.slots.map((slot) => (
                        <BookingSlotButton key={slot.starts_at} slot={slot} selected={selected} onSelect={onSelect}>
                            {slot.timeLabel}
                        </BookingSlotButton>
                    ))}
                </div>
            )}
        </>
    );
}

function SlotsCurated({ slots, selected, onSelect, timezoneLabel }) {
    const suggestions = useMemo(() => curateSuggestions(slots), [slots]);
    const [showAll, setShowAll] = useState(false);

    if (showAll) {
        return (
            <>
                <p className="micro mb-8">All available times ({timezoneLabel})</p>
                <SlotsDayFirst
                    slots={slots}
                    selected={selected}
                    onSelect={onSelect}
                    timezoneLabel={timezoneLabel}
                    showIntro={false}
                />
                <button type="button" className="booking-expand" onClick={() => setShowAll(false)}>
                    Back to suggested times
                </button>
            </>
        );
    }

    return (
        <>
            <p className="micro mb-4">Suggested times ({timezoneLabel})</p>
            <p className="booking-lede">Three openings that usually work well. Pick one, or browse the full week.</p>
            <div className="booking-suggestions" role="listbox" aria-label="Suggested times">
                {suggestions.map((slot) => {
                    const parsed = parseSlot(slot) ?? slot;

                    return (
                        <BookingSlotButton key={parsed.starts_at} slot={slot} selected={selected} onSelect={onSelect}>
                            {parsed.timeLabel ? (
                                <>
                                    <span className="booking-suggestion-day">{parsed.dayLabel}</span>
                                    <span className="booking-suggestion-time">{parsed.timeLabel}</span>
                                </>
                            ) : (
                                parsed.label
                            )}
                        </BookingSlotButton>
                    );
                })}
            </div>
            {slots.length > suggestions.length && (
                <button type="button" className="booking-expand" onClick={() => setShowAll(true)}>
                    Browse all times
                </button>
            )}
        </>
    );
}

export default function ReportBookingSection({ token, businessName, existingBooking, timezoneLabel = 'UK (London)' }) {
    const [slots, setSlots] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(true);
    const [selected, setSelected] = useState(null);
    const [form, setForm] = useState({ attendee_name: '', attendee_email: '', attendee_phone: '', note: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [confirmed, setConfirmed] = useState(existingBooking ?? null);
    const [resolvedTimezoneLabel, setResolvedTimezoneLabel] = useState(timezoneLabel);

    const loadSlots = useCallback(async () => {
        setLoadingSlots(true);
        setError(null);

        try {
            const res = await fetch(`/r/${token}/slots`, { headers: { Accept: 'application/json' } });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(bookingErrorMessage(res.status, data));
            }

            if (data.timezone_label) {
                setResolvedTimezoneLabel(data.timezone_label);
            }

            if (data.booking) {
                setConfirmed(data.booking);
            } else {
                const nextSlots = Array.isArray(data.slots) ? data.slots : [];
                setSlots(nextSlots.filter((slot) => slot?.starts_at && slot?.label));
            }
        } catch (e) {
            setError(e.message ?? 'Could not load available times.');
        } finally {
            setLoadingSlots(false);
        }
    }, [token]);

    useEffect(() => {
        if (!confirmed) {
            loadSlots();
        }
    }, [confirmed, loadSlots]);

    const submit = async (e) => {
        e.preventDefault();
        if (!selected) {
            return;
        }

        setSubmitting(true);
        setError(null);

        try {
            const res = await fetch(`/r/${token}/book`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    starts_at: selected.starts_at,
                    ...form,
                }),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(bookingErrorMessage(res.status, data));
            }

            setConfirmed(data.booking);
        } catch (err) {
            setError(err.message ?? 'Booking failed.');
            loadSlots();
        } finally {
            setSubmitting(false);
        }
    };

    if (confirmed) {
        return (
            <div className="booking-panel--narrow">
                <p className="booking-confirmed-title">
                    You&apos;re booked for {confirmed.label}.
                </p>
                <p className="micro m-0">
                    {confirmed.confirmation_sent
                        ? `Confirmation sent to ${confirmed.attendee_name}.`
                        : `You're confirmed, ${confirmed.attendee_name}. We'll email you shortly.`}
                    {' '}We&apos;ll walk through the findings for {businessName} on the call.
                </p>
                {(confirmed.google_calendar_url || confirmed.ics_url) && (
                    <div className="booking-add-to-calendar">
                        {confirmed.google_calendar_url && (
                            <a
                                href={confirmed.google_calendar_url}
                                className="btn btn-secondary btn-sm"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Add to Google Calendar
                            </a>
                        )}
                        {confirmed.ics_url && (
                            <a href={confirmed.ics_url} className="btn btn-ghost btn-sm">
                                Download .ics
                            </a>
                        )}
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="booking-panel">
            {loadingSlots && slots.length === 0 && <p className="micro">Loading available times…</p>}
            {error && (
                <p className="micro text-critical mb-16" role="alert">
                    {error}
                </p>
            )}

            {!loadingSlots && slots.length === 0 && !error && (
                <p className="micro">No times are available right now. Please check back soon or reply to our email.</p>
            )}

            {slots.length > 0 && (
                <>
                    <SlotsCurated
                        slots={slots}
                        selected={selected}
                        onSelect={setSelected}
                        timezoneLabel={resolvedTimezoneLabel}
                    />

                    {selected && (
                        <BookingForm form={form} setForm={setForm} submit={submit} submitting={submitting} />
                    )}
                </>
            )}
        </div>
    );
}
