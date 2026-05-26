import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AnglePill,
    Button,
    Field,
    Icon,
    Icons,
    ScoreBadge,
    Segmented,
} from '@/Components/ui';

export default function OutreachEmailCard({ email, reportUrl, performanceScore }) {
    const [copied, setCopied] = useState(false);
    const isSent = !!email.sent_at;

    const markSent = () => router.patch(`/outreach-emails/${email.id}/sent`);
    const markResponse = () => router.patch(`/outreach-emails/${email.id}/response`);

    const copyBody = () => {
        navigator.clipboard.writeText(email.email_body);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const showSlowSite = performanceScore != null && performanceScore < 30;

    return (
        <div className={`email-card${isSent ? ' sent' : ''}`}>
            <div className="email-card-header">
                <div>
                    <div className="micro" style={{ marginBottom: 4 }}>To: {email.to_email ?? '—'}</div>
                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12, flexWrap: 'wrap' }}>
                        <ScoreBadge value={email.combined_score} withBar={false} />
                        <div>
                            <AnglePill angle={email.pitch_angle} />
                            {showSlowSite && <div style={{ marginTop: 6 }}><span className="slow-site-tag">+ slow site</span></div>}
                        </div>
                    </div>
                </div>
                <div className="row-actions">
                    {reportUrl && (
                        <a href={reportUrl} target="_blank" rel="noopener noreferrer" className="btn-ghost btn-xs">
                            Preview report
                        </a>
                    )}
                    <button type="button" className="btn-ghost btn-xs" onClick={copyBody}>
                        {copied ? 'Copied' : 'Copy'}
                    </button>
                    {!isSent && (
                        <Button kind="primary" size="xs" onClick={markSent}>Mark sent</Button>
                    )}
                    {isSent && !email.response_received && (
                        <button type="button" className="btn-ghost btn-xs" onClick={markResponse}>Got response</button>
                    )}
                </div>
            </div>
            <div className="email-card-body">
                <input
                    className="email-subject"
                    value={email.subject_line}
                    readOnly={isSent}
                    onChange={() => {}}
                />
                <textarea
                    className="email-body"
                    value={email.email_body}
                    readOnly={isSent}
                    onChange={() => {}}
                />
            </div>
            <div className="email-card-footer">
                {reportUrl && <span>{reportUrl.replace(/^https?:\/\/[^/]+/, '')}</span>}
                {email.sent_at && (
                    <span style={{ marginLeft: 12 }}>Sent {new Date(email.sent_at).toLocaleDateString()}</span>
                )}
            </div>
        </div>
    );
}
