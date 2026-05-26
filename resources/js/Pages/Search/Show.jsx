import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SearchShow({ auth, search, prospects, outreachProspectIds = [] }) {
    const [selected, setSelected] = useState([]);
    const inQueue = new Set(outreachProspectIds);
    const isRunning = ['pending', 'discovering', 'auditing'].includes(search.status);
    const showA11y = search.scan_type !== 'gbp_only';

    useEffect(() => {
        if (!isRunning) return;
        const timer = setInterval(() => {
            router.reload({ only: ['search', 'prospects'] });
        }, 5000);
        return () => clearInterval(timer);
    }, [isRunning]);

    const statusLabel = {
        pending: 'Queued',
        discovering: 'Discovering businesses',
        auditing: 'Auditing websites',
        complete: 'Complete',
        failed: 'Failed',
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={`${search.niche} in ${search.city}`} />

            <div className="max-w-7xl mx-auto py-10 px-4">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">
                            {search.niche} — {search.city}
                        </h1>
                        <p className="text-sm text-gray-500 mt-0.5">
                            {search.total_found
                                ? `${prospects.length} of ${search.total_found} prospects · ${scanTypeLabel(search.scan_type)}`
                                : statusLabel[search.status] ?? 'Scanning...'}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <StatusBadge status={search.status} />
                        {isRunning && (
                            <span className="text-xs text-gray-400 animate-pulse">
                                refreshing...
                            </span>
                        )}
                    </div>
                </div>

                {prospects.length > 0 ? (
                    <>
                        {selected.length > 0 && (
                            <div className="mb-4 flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => {
                                        router.post('/outreach/selections', { prospect_ids: selected });
                                        setSelected([]);
                                    }}
                                    className="text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg"
                                >
                                    Add {selected.length} to outreach
                                </button>
                                <button type="button" onClick={() => setSelected([])} className="text-sm text-gray-500 hover:text-gray-700">
                                    Clear selection
                                </button>
                            </div>
                        )}
                        <ProspectTable
                            prospects={prospects}
                            showA11y={showA11y}
                            selected={selected}
                            setSelected={setSelected}
                            inQueue={inQueue}
                        />
                    </>
                ) : (
                    <div className="text-center py-20 text-gray-400 text-sm">
                        {isRunning ? statusLabel[search.status] + '...' : 'No prospects found.'}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function scanTypeLabel(scanType) {
    const map = {
        gbp_only: 'GBP only',
        accessibility_only: 'Accessibility only',
        combined: 'GBP + Accessibility',
    };
    return map[scanType] ?? scanType;
}

function ProspectTable({ prospects, showA11y, selected, setSelected, inQueue }) {
    const toggle = (id) => {
        setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
    };

    const addOne = (id) => {
        router.post('/outreach/selections', { prospect_ids: [id] });
    };

    return (
        <div className="overflow-x-auto rounded-xl border border-gray-200">
            <table className="w-full text-sm">
                <thead className="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th className="w-10 px-4 py-3" />
                        <th className="text-left px-4 py-3 font-medium text-gray-600">Business</th>
                        <th className="text-center px-4 py-3 font-medium text-gray-600">Score</th>
                        {showA11y && (
                            <>
                                <th className="text-center px-4 py-3 font-medium text-gray-600">GBP</th>
                                <th className="text-center px-4 py-3 font-medium text-gray-600">A11y</th>
                            </>
                        )}
                        <th className="text-center px-4 py-3 font-medium text-gray-600">Reviews</th>
                        <th className="text-center px-4 py-3 font-medium text-gray-600">Photos</th>
                        <th className="text-center px-4 py-3 font-medium text-gray-600">Rating</th>
                        <th className="text-left px-4 py-3 font-medium text-gray-600">Weaknesses</th>
                        <th className="text-left px-4 py-3 font-medium text-gray-600">Website</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {prospects.map(p => (
                        <tr key={p.id} className="hover:bg-gray-50 transition-colors">
                            <td className="px-4 py-3">
                                <input
                                    type="checkbox"
                                    checked={selected.includes(p.id)}
                                    onChange={() => toggle(p.id)}
                                    className="rounded border-gray-300"
                                />
                            </td>
                            <td className="px-4 py-3">
                                <Link
                                    href={`/prospects/${p.id}`}
                                    className="font-medium text-gray-900 hover:text-indigo-600"
                                >
                                    {p.business_name}
                                </Link>
                                {inQueue.has(p.id) && (
                                    <span className="ml-2 text-xs bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded">In outreach</span>
                                )}
                                {p.address && (
                                    <div className="text-xs text-gray-400 mt-0.5">{p.address}</div>
                                )}
                                {p.audit_status === 'pending' && (
                                    <div className="text-xs text-blue-500 mt-0.5">Auditing...</div>
                                )}
                            </td>
                            <td className="px-4 py-3 text-center">
                                <ScoreBadge score={p.combined_score} />
                            </td>
                            {showA11y && (
                                <>
                                    <td className="px-4 py-3 text-center text-gray-600">{p.gbp_score}</td>
                                    <td className="px-4 py-3 text-center text-gray-600">{p.a11y_score}</td>
                                </>
                            )}
                            <td className="px-4 py-3 text-center text-gray-700">{p.review_count}</td>
                            <td className="px-4 py-3 text-center text-gray-700">{p.photo_count}</td>
                            <td className="px-4 py-3 text-center text-gray-700">
                                {p.rating ?? <span className="text-gray-300">-</span>}
                            </td>
                            <td className="px-4 py-3">
                                <div className="flex flex-wrap gap-1">
                                    {(p.gbp_flags ?? []).map((flag, i) => (
                                        <FlagTag key={`gbp-${i}`} flag={flag} variant="gbp" />
                                    ))}
                                    {(p.a11y_flags ?? []).map((flag, i) => (
                                        <FlagTag key={`a11y-${i}`} flag={flag} variant="a11y" />
                                    ))}
                                </div>
                            </td>
                            <td className="px-4 py-3">
                                {p.website_url ? (
                                    <a
                                        href={p.website_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-indigo-600 hover:underline text-xs truncate max-w-[140px] block"
                                    >
                                        {p.website_url.replace(/^https?:\/\//, '')}
                                    </a>
                                ) : (
                                    <span className="text-xs text-red-400">No website</span>
                                )}
                                {!inQueue.has(p.id) && (
                                    <button type="button" onClick={() => addOne(p.id)} className="block mt-1 text-xs text-indigo-600 hover:underline">
                                        Add to outreach
                                    </button>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function FlagTag({ flag, variant }) {
    const styles =
        variant === 'a11y'
            ? 'bg-violet-50 text-violet-700 border-violet-200'
            : 'bg-amber-50 text-amber-700 border-amber-200';
    return (
        <span className={`text-xs border rounded px-1.5 py-0.5 ${styles}`}>
            {flag}
        </span>
    );
}

function ScoreBadge({ score }) {
    const colour =
        score >= 70 ? 'bg-red-100 text-red-700 ring-1 ring-red-200' :
        score >= 40 ? 'bg-amber-100 text-amber-700 ring-1 ring-amber-200' :
                     'bg-green-100 text-green-700 ring-1 ring-green-200';
    return (
        <span className={`inline-flex items-center justify-center w-10 h-7 rounded-md font-semibold text-xs ${colour}`}>
            {score}
        </span>
    );
}

function StatusBadge({ status }) {
    const map = {
        pending:     'bg-gray-100 text-gray-600',
        discovering: 'bg-blue-100 text-blue-700',
        auditing:    'bg-yellow-100 text-yellow-700',
        complete:    'bg-green-100 text-green-700',
        failed:      'bg-red-100 text-red-700',
    };
    return (
        <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${map[status] ?? map.pending}`}>
            {status}
        </span>
    );
}
