import { useMemo, useState } from 'react';
import { SevChip } from '@/Components/ui';

const IMPACT_ORDER = { critical: 0, serious: 1, moderate: 2, minor: 3 };

export default function ViolationsTable({ violations = [] }) {
    const [hideModerateMinor, setHideModerateMinor] = useState(false);
    const showFilter = violations.length > 15;

    const rows = useMemo(() => {
        const sorted = [...violations].sort(
            (a, b) => (IMPACT_ORDER[a.impact] ?? 4) - (IMPACT_ORDER[b.impact] ?? 4),
        );
        if (!hideModerateMinor) {
            return sorted;
        }
        return sorted.filter((v) => v.impact === 'critical' || v.impact === 'serious');
    }, [violations, hideModerateMinor]);

    if (violations.length === 0) {
        return <p className="micro">No violations recorded.</p>;
    }

    return (
        <div>
            {showFilter && (
                <label className="micro" style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
                    <input
                        type="checkbox"
                        checked={hideModerateMinor}
                        onChange={(e) => setHideModerateMinor(e.target.checked)}
                    />
                    Hide moderate &amp; minor
                </label>
            )}
            <table className="data-table" style={{ width: '100%', fontSize: 13 }}>
                <thead>
                    <tr>
                        <th style={{ width: 100 }}>Impact</th>
                        <th style={{ width: 140 }}>Rule</th>
                        <th>Description</th>
                        <th style={{ width: 80 }}>WCAG</th>
                        <th style={{ width: 56, textAlign: 'right' }}>Nodes</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((v) => (
                        <tr key={v.id}>
                            <td><SevChip level={v.impact === 'minor' ? 'moderate' : v.impact} /></td>
                            <td className="mono" style={{ fontSize: 11 }}>{v.id}</td>
                            <td>{v.description}</td>
                            <td className="micro">{v.wcag ?? '—'}</td>
                            <td style={{ textAlign: 'right' }} className="num">{v.nodes}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
