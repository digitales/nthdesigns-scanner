import { Link } from '@inertiajs/react';
import { Card, Status } from '@/Components/ui';

function SearchStatus({ status }) {
    const map = {
        pending: ['pending', 'Queued'],
        discovering: ['pending', 'Discovering'],
        auditing: ['pending', 'Auditing'],
        complete: ['ready', 'Complete'],
        failed: ['failed', 'Failed'],
    };
    const [kind, label] = map[status] ?? map.pending;

    return <Status kind={kind}>{label}</Status>;
}

export default function SearchHistoryCard({ search, showFound = false }) {
    const isDirectUrl = search.source === 'direct_url';

    return (
        <Link
            href={`/searches/${search.id}`}
            style={{
                display: 'block',
                textDecoration: 'none',
                color: 'inherit',
            }}
        >
            <Card pad style={{ padding: '12px 14px' }}>
                {isDirectUrl ? (
                    <>
                        <div style={{ fontWeight: 500, fontSize: 13 }}>
                            {search.submitted_url?.replace(/^https?:\/\//, '') ?? 'Single site'}
                        </div>
                        <div className="micro" style={{ marginTop: 4 }}>
                            Single site · {search.created_at}
                        </div>
                    </>
                ) : (
                    <>
                        <div style={{ fontWeight: 500, fontSize: 13 }}>{search.niche}</div>
                        <div className="micro" style={{ marginTop: 4 }}>
                            {search.city} · {search.created_at}
                        </div>
                    </>
                )}
                {showFound && search.total_found != null ? (
                    <div className="micro" style={{ marginTop: 4 }}>
                        {search.total_found} found
                    </div>
                ) : null}
                <div style={{ marginTop: 8 }}>
                    <SearchStatus status={search.status} />
                </div>
            </Card>
        </Link>
    );
}
