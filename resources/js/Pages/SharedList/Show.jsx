import { Head } from '@inertiajs/react';
import { ScoreBadge } from '@/Components/ui';

export default function SharedListShow({ listName, sharedAt, rows = [] }) {
    const sharedDate = sharedAt ? new Date(sharedAt).toLocaleDateString() : '';

    return (
        <>
            <Head title={listName}>
                <meta name="robots" content="noindex,nofollow" />
            </Head>

            <main className="page page-narrow public-sheet">
                <header className="public-sheet-header">
                    <div className="micro text-stone">Shared prospect sheet</div>
                    <h1 className="page-title">{listName}</h1>
                    {sharedDate && <div className="micro">Shared {sharedDate}</div>}
                </header>

                <table className="data-table shared-sheet-table">
                    <thead>
                        <tr>
                            <th>Business</th>
                            <th>Market</th>
                            <th>Score</th>
                            <th>Flags</th>
                            <th>Tags</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, i) => (
                            <tr key={i}>
                                <td className="biz">{row.business_name}</td>
                                <td className="micro">{row.niche} · {row.city}</td>
                                <td>
                                    <ScoreBadge value={row.combined_score} withBar={false} />
                                </td>
                                <td className="micro">{(row.flags ?? []).join(' · ') || '—'}</td>
                                <td className="micro">{(row.tags ?? []).join(', ') || '—'}</td>
                                <td className="micro">{row.note || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <p className="micro text-stone mt-24">
                    Contact details and audit report links are not included on shared sheets.
                </p>
            </main>
        </>
    );
}
