import { useState } from 'react';
import { Card } from '@/Components/ui';

export default function AuditFailureSection({ auditFailure }) {
    const [open, setOpen] = useState(false);

    if (!auditFailure) {
        return null;
    }

    const { summary, full, detail_expired: detailExpired } = auditFailure;

    return (
        <Card title="Audit failed" className="audit-section-card audit-section-card--critical">
            <p className="audit-failure-summary">{summary}</p>
            {detailExpired && (
                <p className="micro audit-failure-note">
                    Full diagnostic expired (retention). Summary above is still available.
                </p>
            )}
            {!detailExpired && full && (
                <div className="audit-failure-toggle">
                    <button
                        type="button"
                        className="micro btn-link-inline"
                        onClick={() => setOpen((v) => !v)}
                    >
                        {open ? 'Hide full diagnostic' : 'View full diagnostic'}
                    </button>
                    {open && (
                        <pre className="audit-failure-pre">
                            {full}
                        </pre>
                    )}
                </div>
            )}
        </Card>
    );
}
