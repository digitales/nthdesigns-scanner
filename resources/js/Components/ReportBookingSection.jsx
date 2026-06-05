import { useCallback, useEffect, useState } from 'react';
import { Button, Field, Input } from '@/Components/ui';

export default function ReportBookingSection({ token, businessName, existingBooking }) {
    const [slots, setSlots] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(true);
    const [selected, setSelected] = useState(null);
    const [form, setForm] = useState({ attendee_name: '', attendee_email: '', attendee_phone: '', note: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [confirmed, setConfirmed] = useState(existingBooking ?? null);

    const loadSlots = useCallback(async () => {
        setLoadingSlots(true);
        setError(null);

        try {
            const res = await fetch(`/r/${token}/slots`, { headers: { Accept: 'application/json' } });
            if (!res.ok) {
                throw new Error('Could not load available times.');
            }
            const data = await res.json();
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
                throw new Error(data.message ?? 'Booking failed. Please try another time.');
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
                    Confirmation sent to {confirmed.attendee_name}. We&apos;ll walk through the findings for {businessName} on the call.
                </p>
            </div>
        );
    }

    return (
        <div className="booking-panel">
            {loadingSlots && <p className="micro">Loading available times…</p>}
            {error && (
                <p className="micro text-critical mb-16">
                    {error}
                </p>
            )}

            {!loadingSlots && slots.length === 0 && !error && (
                <p className="micro">No times are available right now. Please check back soon or reply to our email.</p>
            )}

            {slots.length > 0 && (
                <>
                    <p className="micro mb-12">Choose a time (UK)</p>
                    <div className="booking-slots-grid">
                        {slots.map((slot) => (
                            <button
                                key={slot.starts_at}
                                type="button"
                                onClick={() => setSelected(slot)}
                                className={`booking-slot${selected?.starts_at === slot.starts_at ? ' booking-slot--selected' : ''}`}
                            >
                                {slot.label}
                            </button>
                        ))}
                    </div>

                    {selected && (
                        <form onSubmit={submit} className="booking-form">
                            <Field label="Your name">
                                <Input
                                    required
                                    value={form.attendee_name}
                                    onChange={(e) => setForm((f) => ({ ...f, attendee_name: e.target.value }))}
                                />
                            </Field>
                            <Field label="Email">
                                <Input
                                    type="email"
                                    required
                                    value={form.attendee_email}
                                    onChange={(e) => setForm((f) => ({ ...f, attendee_email: e.target.value }))}
                                />
                            </Field>
                            <Field label="Phone (optional)">
                                <Input
                                    value={form.attendee_phone}
                                    onChange={(e) => setForm((f) => ({ ...f, attendee_phone: e.target.value }))}
                                />
                            </Field>
                            <Field label="Anything we should know? (optional)">
                                <Input
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
