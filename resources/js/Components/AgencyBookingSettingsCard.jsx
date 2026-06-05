import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button, Card, Checkbox, Field, FormError, Input } from '@/Components/ui';

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

export default function AgencyBookingSettingsCard({ agencyBooking }) {
    const { errors } = usePage().props;
    const { data, setData, patch, processing, recentlySuccessful } = useForm({
        enabled: agencyBooking.enabled,
        fastmail_username: agencyBooking.fastmail_username,
        fastmail_app_password: '',
        caldav_calendar_url: agencyBooking.caldav_calendar_url,
        timezone: agencyBooking.timezone,
        min_notice_hours: agencyBooking.min_notice_hours,
        buffer_minutes: agencyBooking.buffer_minutes,
        confirmation_from_email: agencyBooking.confirmation_from_email,
        confirmation_from_name: agencyBooking.confirmation_from_name,
        working_hours: agencyBooking.working_hours,
    });

    const [testing, setTesting] = useState(false);

    const save = (e) => {
        e.preventDefault();
        patch('/settings/agency-booking', { preserveScroll: true });
    };

    const testConnection = (e) => {
        e.preventDefault();
        setTesting(true);
        router.post('/settings/agency-booking/test', {
            fastmail_username: data.fastmail_username,
            fastmail_app_password: data.fastmail_app_password,
        }, {
            preserveScroll: true,
            onFinish: () => setTesting(false),
        });
    };

    const setDay = (day, key, value) => {
        setData('working_hours', {
            ...data.working_hours,
            [day]: { ...data.working_hours[day], [key]: value },
        });
    };

    return (
        <Card title="Agency booking (Fastmail)">
            <p className="micro" style={{ margin: '0 0 16px' }}>
                Inline booking on public reports uses your shared Fastmail calendar. When enabled, report CTAs use native scheduling instead of the booking URL below.
            </p>
            {agencyBooking.native_active && (
                <p className="micro" style={{ color: 'var(--color-positive)', margin: '0 0 16px' }}>
                    Native booking is active on public reports.
                </p>
            )}
            <form onSubmit={save}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <label className="micro" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <Checkbox
                            checked={data.enabled}
                            onChange={(e) => setData('enabled', e.target.checked)}
                        />
                        Enable inline report booking
                    </label>

                    <Field label="Fastmail email">
                        <Input
                            type="email"
                            value={data.fastmail_username}
                            onChange={(e) => setData('fastmail_username', e.target.value)}
                            placeholder="bookings@yourdomain.com"
                        />
                    </Field>

                    <Field
                        label="Fastmail app password"
                        hint={agencyBooking.has_app_password ? 'Leave blank to keep the current password' : 'Create under Fastmail → Privacy & Security → App passwords'}
                    >
                        <Input
                            type="password"
                            value={data.fastmail_app_password}
                            onChange={(e) => setData('fastmail_app_password', e.target.value)}
                            autoComplete="new-password"
                        />
                    </Field>

                    <Field label="CalDAV calendar URL" hint="Filled automatically after a successful connection test, or paste from Fastmail calendar settings">
                        <Input
                            type="url"
                            value={data.caldav_calendar_url}
                            onChange={(e) => setData('caldav_calendar_url', e.target.value)}
                        />
                    </Field>

                    <Field label="Minimum notice (hours)">
                        <Input
                            type="number"
                            min={1}
                            max={168}
                            value={data.min_notice_hours}
                            onChange={(e) => setData('min_notice_hours', Number(e.target.value))}
                        />
                    </Field>

                    <Field label="Confirmation from email">
                        <Input
                            type="email"
                            value={data.confirmation_from_email}
                            onChange={(e) => setData('confirmation_from_email', e.target.value)}
                            placeholder={data.fastmail_username || 'bookings@yourdomain.com'}
                        />
                    </Field>

                    <Field label="Confirmation from name">
                        <Input
                            value={data.confirmation_from_name}
                            onChange={(e) => setData('confirmation_from_name', e.target.value)}
                            placeholder="nthdesigns"
                        />
                    </Field>

                    <div>
                        <div className="micro" style={{ fontWeight: 500, marginBottom: 8 }}>Working hours (UK)</div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                            {DAYS.map((day) => (
                                <div key={day} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                    <label className="micro" style={{ width: 90, display: 'flex', gap: 6, textTransform: 'capitalize' }}>
                                        <Checkbox
                                            checked={data.working_hours[day]?.enabled ?? false}
                                            onChange={(e) => setDay(day, 'enabled', e.target.checked)}
                                        />
                                        {day.slice(0, 3)}
                                    </label>
                                    <Input
                                        type="time"
                                        value={data.working_hours[day]?.start ?? '09:00'}
                                        onChange={(e) => setDay(day, 'start', e.target.value)}
                                        disabled={!data.working_hours[day]?.enabled}
                                    />
                                    <span className="micro">–</span>
                                    <Input
                                        type="time"
                                        value={data.working_hours[day]?.end ?? '17:00'}
                                        onChange={(e) => setDay(day, 'end', e.target.value)}
                                        disabled={!data.working_hours[day]?.enabled}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    <FormError message={errors.agency_booking} />

                    <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                        <Button kind="primary" type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save agency booking'}
                        </Button>
                        {recentlySuccessful && (
                            <span className="micro" style={{ color: 'var(--color-positive)' }}>Saved.</span>
                        )}
                    </div>
                </div>
            </form>

            <form onSubmit={testConnection} style={{ marginTop: 24, paddingTop: 24, borderTop: '1px solid var(--color-line)' }}>
                <p className="micro" style={{ margin: '0 0 12px' }}>Test CalDAV connection (uses the email and password above, or saved password if left blank).</p>
                <Button kind="secondary" type="submit" disabled={testing}>
                    {testing ? 'Testing…' : 'Test Fastmail connection'}
                </Button>
            </form>
        </Card>
    );
}
