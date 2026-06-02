import { useState } from 'react';
import { Card } from '@/Components/ui';

export default function AuditFailureSection({ auditFailure }) {
    const [open, setOpen] = useState(false);

    if (!auditFailure) {
        return null;
    }

    const { summary, full, detail_expired: detailExpired } = auditFailure;

    return (
        <Card title="Audit failed" style={{ marginBottom: 24, borderColor: 'var(--color-sev-critical)' }}>
            <p style={{ margin: 0, fontSize: 13, color: 'var(--color-sev-critical)' }}>{summary}</p>
            {detailExpired && (
                <p className="micro" style={{ marginTop: 12, marginBottom: 0, color: 'var(--color-stone-500)' }}>
                    Full diagnostic expired (retention). Summary above is still available.
                </p>
            )}
            {!detailExpired && full && (
                <div style={{ marginTop: 12 }}>
                    <button
                        type="button"
                        className="micro"
                        onClick={() => setOpen((v) => !v)}
                        style={{
                            background: 'none',
                            border: 'none',
                            padding: 0,
                            cursor: 'pointer',
                            color: 'var(--color-stone-600)',
                            textDecoration: 'underline',
                        }}
                    >
                        {open ? 'Hide full diagnostic' : 'View full diagnostic'}
                    </button>
                    {open && (
                        <pre
                            style={{
                                marginTop: 10,
                                marginBottom: 0,
                                padding: 12,
                                fontSize: 11,
                                lineHeight: 1.45,
                                overflow: 'auto',
                                maxHeight: 320,
                                background: 'var(--color-stone-100)',
                                borderRadius: 4,
                                whiteSpace: 'pre-wrap',
                                wordBreak: 'break-word',
                            }}
                        >
                            {full}
                        </pre>
                    )}
                </div>
            )}
        </Card>
    );
}
