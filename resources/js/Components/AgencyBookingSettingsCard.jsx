import { router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button, Card, Checkbox, Field, FormError, Input, Select, Status } from '@/Components/ui';

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

const TIMEZONES = [
    { value: 'Europe/London', label: 'UK (London)' },
    { value: 'Europe/Dublin', label: 'Ireland (Dublin)' },
    { value: 'America/New_York', label: 'US Eastern' },
    { value: 'America/Chicago', label: 'US Central' },
    { value: 'America/Denver', label: 'US Mountain' },
    { value: 'America/Los_Angeles', label: 'US Pacific' },
];

function Subsection({ title, children }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16, paddingTop: 20, borderTop: '1px solid var(--color-line)' }}>
            <div className="micro" style={{ fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.08em', color: 'var(--color-stone-500)' }}>
                {title}
            </div>
            {children}
        </div>
    );
}

function bookingStatus(agencyBooking, enabled) {
    if (!enabled) {
        return { kind: 'pending', label: 'Off — using fallback booking URL' };
    }
    if (agencyBooking.native_active) {
        return { kind: 'ready', label: 'Active on public reports' };
    }
    return { kind: 'pending', label: 'Setup incomplete' };
}

export default function AgencyBookingSettingsCard({ agencyBooking }) {
    const { errors, flash } = usePage().props;
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

    const discoveredCalendars = flash?.agency_booking_calendars ?? [];
    const status = bookingStatus(agencyBooking, data.enabled);

    const timezoneOptions = useMemo(() => {
        const known = new Set(TIMEZONES.map((tz) => tz.value));
        if (data.timezone && !known.has(data.timezone)) {
            return [{ value: data.timezone, label: data.timezone }, ...TIMEZONES];
        }
        return TIMEZONES;
    }, [data.timezone]);

    const save = (e) => {
        e.preventDefault();
        patch('/settings/agency-booking', { preserveScroll: true });
    };

    const testConnection = () => {
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

    const pickCalendar = (url) => {
        setData('caldav_calendar_url', url);
    };

    return (
        <Card title="Report booking (Fastmail)">
            <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 16, marginBottom: 12 }}>
                <p className="micro" style={{ margin: 0, flex: 1 }}>
                    Prospects book 30-minute review calls inline on public reports. Slots sync from your shared Fastmail calendar via CalDAV.
                </p>
                <Status kind={status.kind}>{status.label}</Status>
            </div>

            <form onSubmit={save}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <label className="micro" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <Checkbox
                            checked={data.enabled}
                            onChange={(checked) => setData('enabled', checked)}
                        />
                        Enable inline booking on public reports
                    </label>

                    {!data.enabled && (
                        <p className="micro" style={{ margin: 0, color: 'var(--color-stone-500)' }}>
                            When off, report CTAs use the Booking URL fallback in Defaults below.
                        </p>
                    )}

                    {data.enabled && (
                        <>
                            <Subsection title="Fastmail connection">
                                <div style={{ display: 'grid', gap: 16 }}>
                                    <Field label="Fastmail email">
                                        <Input
                                            type="email"
                                            name="fastmail_username"
                                            value={data.fastmail_username}
                                            onChange={(e) => setData('fastmail_username', e.target.value)}
                                            placeholder="bookings@yourdomain.com"
                                        />
                                    </Field>

                                    <Field
                                        label="App password"
                                        hint={agencyBooking.has_app_password ? 'Leave blank to keep the saved password' : 'Fastmail → Privacy & Security → App passwords'}
                                    >
                                        <Input
                                            type="password"
                                            name="fastmail_app_password"
                                            value={data.fastmail_app_password}
                                            onChange={(e) => setData('fastmail_app_password', e.target.value)}
                                            autoComplete="new-password"
                                        />
                                    </Field>
                                </div>

                                <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'center' }}>
                                    <Button kind="secondary" type="button" disabled={testing || !data.fastmail_username} onClick={testConnection}>
                                        {testing ? 'Testing…' : 'Test connection'}
                                    </Button>
                                    {!agencyBooking.has_app_password && !data.fastmail_app_password && (
                                        <span className="micro" style={{ color: 'var(--color-stone-500)' }}>
                                            Enter an app password to test.
                                        </span>
                                    )}
                                </div>

                                {discoveredCalendars.length > 1 ? (
                                    <Field label="Calendar" hint="Choose which Fastmail calendar receives bookings">
                                        <Select
                                            name="caldav_calendar_url"
                                            value={data.caldav_calendar_url}
                                            onChange={(e) => pickCalendar(e.target.value)}
                                        >
                                            <option value="">Select a calendar…</option>
                                            {discoveredCalendars.map((cal) => (
                                                <option key={cal.url} value={cal.url}>
                                                    {cal.name}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                ) : (
                                    <Field
                                        label="CalDAV calendar URL"
                                        hint="Filled automatically after a successful test, or paste from Fastmail calendar settings"
                                    >
                                        <Input
                                            type="url"
                                            name="caldav_calendar_url"
                                            value={data.caldav_calendar_url}
                                            onChange={(e) => setData('caldav_calendar_url', e.target.value)}
                                        />
                                    </Field>
                                )}
                            </Subsection>

                            <Subsection title="Availability">
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: 16 }}>
                                    <Field label="Min. notice (hours)" hint="How far ahead prospects must book">
                                        <Input
                                            type="number"
                                            name="min_notice_hours"
                                            min={1}
                                            max={168}
                                            value={data.min_notice_hours}
                                            onChange={(e) => setData('min_notice_hours', Number(e.target.value))}
                                        />
                                    </Field>

                                    <Field label="Buffer (minutes)" hint="Gap between consecutive calls">
                                        <Input
                                            type="number"
                                            name="buffer_minutes"
                                            min={0}
                                            max={60}
                                            value={data.buffer_minutes}
                                            onChange={(e) => setData('buffer_minutes', Number(e.target.value))}
                                        />
                                    </Field>

                                    <Field label="Timezone">
                                        <Select
                                            name="timezone"
                                            value={data.timezone}
                                            onChange={(e) => setData('timezone', e.target.value)}
                                        >
                                            {timezoneOptions.map((tz) => (
                                                <option key={tz.value} value={tz.value}>
                                                    {tz.label}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>

                                    <Field label="Call length">
                                        <Input type="text" value="30 minutes" readOnly disabled />
                                    </Field>
                                </div>

                                <div>
                                    <div className="micro" style={{ fontWeight: 500, marginBottom: 8 }}>Working hours</div>
                                    <div
                                        style={{
                                            display: 'grid',
                                            gridTemplateColumns: 'minmax(72px, auto) 20px minmax(100px, 1fr) 12px minmax(100px, 1fr)',
                                            gap: '8px 8px',
                                            alignItems: 'center',
                                        }}
                                    >
                                        {DAYS.map((day) => {
                                            const enabled = data.working_hours[day]?.enabled ?? false;
                                            return (
                                                <div key={day} style={{ display: 'contents' }}>
                                                    <label className="micro" style={{ textTransform: 'capitalize' }}>
                                                        {day.slice(0, 3)}
                                                    </label>
                                                    <Checkbox
                                                        checked={enabled}
                                                        onChange={(checked) => setDay(day, 'enabled', checked)}
                                                        aria-label={`${day} enabled`}
                                                    />
                                                    <Input
                                                        type="time"
                                                        value={data.working_hours[day]?.start ?? '09:00'}
                                                        onChange={(e) => setDay(day, 'start', e.target.value)}
                                                        disabled={!enabled}
                                                    />
                                                    <span className="micro" style={{ textAlign: 'center' }}>–</span>
                                                    <Input
                                                        type="time"
                                                        value={data.working_hours[day]?.end ?? '17:00'}
                                                        onChange={(e) => setDay(day, 'end', e.target.value)}
                                                        disabled={!enabled}
                                                    />
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </Subsection>

                            <Subsection title="Confirmation email">
                                <div style={{ display: 'grid', gap: 16 }}>
                                    <Field label="From email">
                                        <Input
                                            type="email"
                                            name="confirmation_from_email"
                                            value={data.confirmation_from_email}
                                            onChange={(e) => setData('confirmation_from_email', e.target.value)}
                                            placeholder={data.fastmail_username || 'bookings@yourdomain.com'}
                                        />
                                    </Field>

                                    <Field label="From name">
                                        <Input
                                            name="confirmation_from_name"
                                            value={data.confirmation_from_name}
                                            onChange={(e) => setData('confirmation_from_name', e.target.value)}
                                            placeholder="nthdesigns"
                                        />
                                    </Field>
                                </div>
                            </Subsection>
                        </>
                    )}

                    <FormError message={errors.agency_booking} />

                    <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'center', paddingTop: data.enabled ? 4 : 0 }}>
                        <Button kind="primary" type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save booking settings'}
                        </Button>
                        {recentlySuccessful && (
                            <span className="micro" style={{ color: 'var(--color-positive)' }}>Saved.</span>
                        )}
                    </div>
                </div>
            </form>
        </Card>
    );
}
