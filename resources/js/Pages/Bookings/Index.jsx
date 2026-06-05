import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Button,
    DataTable,
    EmptyState,
    Page,
    PageHeader,
    Pagination,
    Segmented,
    Stack,
    StatTile,
    Status,
} from '@/Components/ui';

export default function BookingsIndex({ bookings, filters, stats, pagination }) {
    const setPast = (past) => {
        router.get('/bookings', past ? { past: 1 } : {}, { preserveState: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Bookings" />

            <Page width="wide" className="page-wide">
                <PageHeader
                    eyebrow="Review calls"
                    title="Upcoming bookings"
                    sub="Native report bookings synced from your agency calendar."
                />

                <Stack direction="row" gap={16} className="mb-16">
                    <StatTile label="Upcoming" value={stats.upcoming} />
                    <StatTile label="Unsent confirmations" value={stats.unsent_confirmations} />
                </Stack>

                <Segmented
                    className="mb-16"
                    value={filters.past ? 'past' : 'upcoming'}
                    onChange={(value) => setPast(value === 'past')}
                    options={[
                        { value: 'upcoming', label: 'Upcoming' },
                        { value: 'past', label: 'Past' },
                    ]}
                />

                {bookings.length === 0 ? (
                    <EmptyState
                        title={filters.past ? 'No past bookings.' : 'No upcoming bookings.'}
                        sub="Bookings appear here when prospects reserve a slot on a public report."
                    />
                ) : (
                    <>
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Prospect</th>
                                    <th>Attendee</th>
                                    <th>Note</th>
                                    <th>Status</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {bookings.map((booking) => (
                                    <tr key={booking.id}>
                                        <td>
                                            <div className="body-sm-medium-tight">{booking.label}</div>
                                            <div className="micro text-stone">{booking.timezone_label}</div>
                                        </td>
                                        <td>
                                            <div className="body-sm-medium-tight">{booking.business_name}</div>
                                            <div className="micro text-stone">{booking.niche} · {booking.city}</div>
                                        </td>
                                        <td>
                                            <div>{booking.attendee_name}</div>
                                            <div className="micro break-all">{booking.attendee_email}</div>
                                            {booking.attendee_phone && (
                                                <div className="micro text-stone">{booking.attendee_phone}</div>
                                            )}
                                        </td>
                                        <td className="micro">{booking.note || '—'}</td>
                                        <td>
                                            <Status kind={booking.confirmation_sent ? 'ready' : 'pending'}>
                                                {booking.confirmation_sent ? 'Confirmed' : 'Mail pending'}
                                            </Status>
                                        </td>
                                        <td>
                                            <Stack direction="row" gap={8}>
                                                {booking.prospect_url && (
                                                    <Link href={booking.prospect_url}>
                                                        <Button kind="ghost" size="sm">Prospect</Button>
                                                    </Link>
                                                )}
                                                {booking.report_url && (
                                                    <a href={booking.report_url} target="_blank" rel="noopener noreferrer">
                                                        <Button kind="ghost" size="sm">Report</Button>
                                                    </a>
                                                )}
                                            </Stack>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                        <Pagination pagination={pagination} href="/bookings" query={filters.past ? { past: 1 } : {}} />
                    </>
                )}
            </Page>
        </AuthenticatedLayout>
    );
}
