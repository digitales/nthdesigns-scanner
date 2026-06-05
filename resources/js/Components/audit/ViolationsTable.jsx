import { useMemo, useState } from 'react';
import { Checkbox, SevChip } from '@/Components/ui';

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
                <label className="micro violations-filter">
                    <Checkbox
                        checked={hideModerateMinor}
                        onChange={(checked) => setHideModerateMinor(checked)}
                    />
                    Hide moderate &amp; minor
                </label>
            )}
            <table className="data-table violations-table">
                <thead>
                    <tr>
                        <th className="col-impact">Impact</th>
                        <th className="col-rule">Rule</th>
                        <th>Description</th>
                        <th className="col-wcag">WCAG</th>
                        <th className="col-nodes">Nodes</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((v) => (
                        <tr key={v.id}>
                            <td><SevChip level={v.impact === 'minor' ? 'moderate' : v.impact} /></td>
                            <td className="mono col-rule">{v.id}</td>
                            <td>{v.description}</td>
                            <td className="micro">{v.wcag ?? '—'}</td>
                            <td className="num col-nodes">{v.nodes}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
