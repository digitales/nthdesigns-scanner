import { Link } from '@inertiajs/react';

function pageNumbers(current, last) {
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const pages = new Set([1, last, current, current - 1, current + 1]);
    const sorted = [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);
    const result = [];

    for (let i = 0; i < sorted.length; i += 1) {
        if (i > 0 && sorted[i] - sorted[i - 1] > 1) {
            result.push('…');
        }
        result.push(sorted[i]);
    }

    return result;
}

export default function Pagination({ pagination, href = '/searches', query = {} }) {
    const { current_page, last_page, total, per_page } = pagination;

    if (!last_page || last_page <= 1) {
        return null;
    }

    const from = (current_page - 1) * per_page + 1;
    const to = Math.min(current_page * per_page, total);
    const pages = pageNumbers(current_page, last_page);

    const pageHref = (page) => {
        const params = new URLSearchParams();
        Object.entries(query).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                params.set(key, String(value));
            }
        });
        if (page > 1) {
            params.set('page', String(page));
        }
        const qs = params.toString();
        return qs ? `${href}?${qs}` : href;
    };

    return (
        <nav
            aria-label="Pagination"
            style={{
                marginTop: 24,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: 12,
            }}
        >
            <p className="micro">
                Showing {from}–{to} of {total}
            </p>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap', justifyContent: 'center' }}>
                {current_page > 1 ? (
                    <Link href={pageHref(current_page - 1)} className="btn btn-secondary btn-xs" preserveState>
                        Previous
                    </Link>
                ) : (
                    <span className="btn btn-secondary btn-xs" style={{ opacity: 0.45, pointerEvents: 'none' }}>
                        Previous
                    </span>
                )}

                {pages.map((page, index) =>
                    page === '…' ? (
                        <span key={`gap-${index}`} className="micro" style={{ padding: '0 4px' }}>
                            …
                        </span>
                    ) : (
                        <Link
                            key={page}
                            href={pageHref(page)}
                            className={`btn btn-xs ${page === current_page ? 'btn-primary' : 'btn-secondary'}`}
                            preserveState
                            aria-current={page === current_page ? 'page' : undefined}
                        >
                            {page}
                        </Link>
                    ),
                )}

                {current_page < last_page ? (
                    <Link href={pageHref(current_page + 1)} className="btn btn-secondary btn-xs" preserveState>
                        Next
                    </Link>
                ) : (
                    <span className="btn btn-secondary btn-xs" style={{ opacity: 0.45, pointerEvents: 'none' }}>
                        Next
                    </span>
                )}
            </div>
        </nav>
    );
}
