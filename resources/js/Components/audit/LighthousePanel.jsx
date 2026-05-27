import { Card } from '@/Components/ui';
import LighthouseDial from '@/Components/audit/LighthouseDial';

const METRICS = [
    ['Performance', 'performance'],
    ['Accessibility', 'accessibility'],
    ['SEO', 'seo'],
    ['Best practices', 'best_practices'],
];

export default function LighthousePanel({ lighthouse, style }) {
    const lh = lighthouse ?? {};
    const visible = METRICS.filter(([, key]) => lh[key] != null);

    if (visible.length === 0) {
        return null;
    }

    return (
        <Card title="Lighthouse" style={style}>
            <p className="micro" style={{ marginBottom: 16, lineHeight: 1.5 }}>
                Google Lighthouse (0–100). On combined scans, performance contributes 15% to the combined score.
            </p>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(100px, 1fr))', gap: 16 }}>
                {visible.map(([label, key]) => (
                    <LighthouseDial key={key} label={label} score={lh[key]} />
                ))}
            </div>
        </Card>
    );
}
