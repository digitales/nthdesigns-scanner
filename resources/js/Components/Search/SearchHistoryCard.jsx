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
        <Link href={`/searches/${search.id}`} className="history-card-link">
            <Card pad className="history-card-pad">
                {isDirectUrl ? (
                    <>
                        <div className="history-card-title">
                            {search.submitted_url?.replace(/^https?:\/\//, '') ?? 'Single site'}
                        </div>
                        <div className="micro history-card-meta">
                            Single site · {search.created_at}
                        </div>
                    </>
                ) : (
                    <>
                        <div className="history-card-title">{search.niche}</div>
                        <div className="micro history-card-meta">
                            {search.city} · {search.created_at}
                        </div>
                    </>
                )}
                {showFound && search.total_found != null ? (
                    <div className="micro history-card-meta">
                        {search.total_found} found
                    </div>
                ) : null}
                <div className="history-card-status">
                    <SearchStatus status={search.status} />
                </div>
            </Card>
        </Link>
    );
}
