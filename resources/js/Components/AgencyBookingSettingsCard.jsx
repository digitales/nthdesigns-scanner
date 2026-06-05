import { router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button, Card, Checkbox, Field, FormError, Grid, Input, Select, Stack, Status } from '@/Components/ui';

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
        <Stack gap={16} className="stack--section">
            <div className="micro" style={{ fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.08em', color: 'var(--color-stone-500)' }}>
                {title}
            </div>
            {children}
        </Stack>
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
            <Stack direction="row" justify="between" align="start" gap={16} style={{ marginBottom: 12 }}>
                <p className="micro stack--grow" style={{ margin: 0 }}>
                    Prospects book 30-minute review calls inline on public reports. Slots sync from your shared Fastmail calendar via CalDAV.
                </p>
                <Status kind={status.kind}>{status.label}</Status>
            </Stack>

            <form onSubmit={save}>
                <Stack gap={16}>
                    <Stack as="label" className="micro" direction="row" gap={8} align="center">
                        <Checkbox
                            checked={data.enabled}
                            onChange={(checked) => setData('enabled', checked)}
                        />
                        Enable inline booking on public reports
                    </Stack>

                    {!data.enabled && (
                        <p className="micro" style={{ margin: 0, color: 'var(--color-stone-500)' }}>
                            When off, report CTAs use the Booking URL fallback in Defaults below.
                        </p>
                    )}

                    {data.enabled && (
                        <>
                            <Subsection title="Fastmail connection">
                                <Grid gap={16}>
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
                                </Grid>

                                <Stack direction="row" gap={12} align="center" wrap>
                                    <Button kind="secondary" type="button" disabled={testing || !data.fastmail_username} onClick={testConnection}>
                                        {testing ? 'Testing…' : 'Test connection'}
                                    </Button>
                                    {!agencyBooking.has_app_password && !data.fastmail_app_password && (
                                        <span className="micro" style={{ color: 'var(--color-stone-500)' }}>
                                            Enter an app password to test.
                                        </span>
                                    )}
                                    {Array.isArray(flash?.agency_booking_calendars) && flash?.success && !recentlySuccessful && (
                                        <span className="micro" style={{ color: 'var(--color-positive)' }}>
                                            {flash.success}
                                        </span>
                                    )}
                                </Stack>

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
                                <Grid cols="auto" gap={16}>
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
                                </Grid>

                                <div>
                                    <div className="micro" style={{ fontWeight: 500, marginBottom: 8 }}>Working hours</div>
                                    <Grid cols="hours">
                                        {DAYS.map((day) => {
                                            const enabled = data.working_hours[day]?.enabled ?? false;
                                            return (
                                                <div key={day} className="grid--contents">
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
                                    </Grid>
                                </div>
                            </Subsection>

                            <Subsection title="Confirmation email">
                                <Grid gap={16}>
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
                                </Grid>
                            </Subsection>
                        </>
                    )}

                    <FormError message={errors.agency_booking} />

                    <Stack direction="row" gap={12} align="center" wrap style={{ paddingTop: data.enabled ? 4 : 0 }}>
                        <Button kind="primary" type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save booking settings'}
                        </Button>
                        {recentlySuccessful && (
                            <span className="micro" style={{ color: 'var(--color-positive)' }}>Saved.</span>
                        )}
                    </Stack>
                </Stack>
            </form>
        </Card>
    );
}
