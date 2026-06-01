export default function CmsBadge({ badge, pending }) {
    if (pending) {
        return <span className="badge cms-badge cms-badge--pending">…</span>;
    }

    if (!badge) {
        return <span className="micro">—</span>;
    }

    return <span className="badge cms-badge">{badge}</span>;
}
