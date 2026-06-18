import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Card,
    DataTable,
    LinkButton,
    Page,
    PageHeader,
    SplitRow,
    Stack,
    StatTile,
    StatsStrip,
} from '@/Components/ui';

export default function WarmupAdminPool({ pool, stats, recent_exclusions, top_bounce_seeds }) {
    return (
        <AuthenticatedLayout>
            <Head title="Warmup pool" />

            <Page width="wide" className="page-wide">
                <PageHeader
                    eyebrow="Admin"
                    title="Shared seed pool."
                    sub="Network health across all participating seed mailboxes."
                    actions={<LinkButton href="/warmup" kind="secondary">Back to warmup</LinkButton>}
                />

                <StatsStrip>
                    <StatTile label="Active pool seeds" value={pool.active_count} warm={pool.pool_ready} />
                    <StatTile label="Min size (start gate)" value={pool.min_size} />
                    <StatTile label="Alert threshold" value={pool.alert_size} />
                    <StatTile label="Pool sends (24h)" value={stats.sends_24h} />
                    <StatTile label="Pool sends (7d)" value={stats.sends_7d} />
                </StatsStrip>

                <Stack gap={16}>
                    <Card title="Recent exclusions" pad={recent_exclusions.length > 0}>
                        {recent_exclusions.length === 0 ? (
                            <p className="micro text-muted m-0">No bounce exclusions in the lookback window.</p>
                        ) : (
                            <DataTable tableClassName="ptable--paper">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Excluded</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recent_exclusions.map((row, index) => (
                                        <tr key={`${row.email}-${index}`}>
                                            <td>{row.email}</td>
                                            <td className="micro text-stone">
                                                {row.excluded_at ? new Date(row.excluded_at).toLocaleString('en-GB') : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </DataTable>
                        )}
                    </Card>

                    <Card title="Top bounce rates" pad={top_bounce_seeds.length > 0}>
                        {top_bounce_seeds.length === 0 ? (
                            <p className="micro text-muted m-0">No bounces recorded in the lookback window.</p>
                        ) : (
                            <DataTable tableClassName="ptable--paper">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Received</th>
                                        <th>Bounces</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {top_bounce_seeds.map((row) => (
                                        <tr key={row.email}>
                                            <td>{row.email}</td>
                                            <td>{row.total_received}</td>
                                            <td>{row.bounces}</td>
                                            <td>{Math.round(row.bounce_rate * 100)}%</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </DataTable>
                        )}
                    </Card>

                    <Card title="Thresholds">
                        <Stack gap={8}>
                            <SplitRow>
                                <span className="micro text-medium">Start gate</span>
                                <span className="micro">{pool.min_size} active seeds</span>
                            </SplitRow>
                            <SplitRow>
                                <span className="micro text-medium">Low-pool alert</span>
                                <span className="micro">Below {pool.alert_size} active seeds</span>
                            </SplitRow>
                        </Stack>
                    </Card>
                </Stack>
            </Page>
        </AuthenticatedLayout>
    );
}
