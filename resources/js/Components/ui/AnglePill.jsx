import { normalizeAngle } from './scoreBand';

const MAP = {
    gbp: { cls: 'gbp', glyph: '◐', label: 'GBP' },
    a11y: { cls: 'a11y', glyph: '◢', label: 'Accessibility' },
    both: { cls: 'both', glyph: '◆', label: 'Both' },
};

export default function AnglePill({ angle }) {
    const key = normalizeAngle(angle);
    const m = MAP[key] ?? MAP.both;

    return (
        <span className={`angle-pill ${m.cls}`}>
            <span className="glyph">{m.glyph}</span>
            {m.label}
        </span>
    );
}
