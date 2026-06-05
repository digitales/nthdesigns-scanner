import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AnglePill,
    Button,
    Card,
    RowActions,
    ScoreBadge,
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
        <Card pad={false} className={`email-card${isSent ? ' sent' : ''}`}>
            <div className="email-card-header">
                <div>
                    <div className="micro email-card-meta">To: {email.to_email ?? '—'}</div>
                    <div className="email-card-scores">
                        <ScoreBadge value={email.combined_score} withBar={false} />
                        <div>
                            <AnglePill angle={email.pitch_angle} />
                            {showSlowSite && (
                                <div className="email-card-slow-tag">
                                    <span className="slow-site-tag">+ slow site</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
                <RowActions>
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
                </RowActions>
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
                    <span className="email-card-sent-date">Sent {new Date(email.sent_at).toLocaleDateString()}</span>
                )}
            </div>
        </Card>
    );
}
