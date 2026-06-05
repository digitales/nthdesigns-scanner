import { useCallback, useEffect, useState } from 'react';
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
                setSlots(data.slots ?? []);
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
            {loadingSlots && <p className="micro">Loading available times…</p>}
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
                    <p className="micro mb-12">Choose a time ({resolvedTimezoneLabel})</p>
                    <div className="booking-slots-grid" role="listbox" aria-label="Available times">
                        {slots.map((slot) => (
                            <button
                                key={slot.starts_at}
                                type="button"
                                role="option"
                                aria-selected={selected?.starts_at === slot.starts_at}
                                onClick={() => setSelected(slot)}
                                className={`booking-slot${selected?.starts_at === slot.starts_at ? ' booking-slot--selected' : ''}`}
                            >
                                {slot.label}
                            </button>
                        ))}
                    </div>

                    {selected && (
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
                    )}
                </>
            )}
        </div>
    );
}
