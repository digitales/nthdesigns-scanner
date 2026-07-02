import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AnglePill,
    Button,
    Card,
    RowActions,
    ScoreBadge,
} from '@/Components/ui';
import OutreachEmailCard from '@/Components/OutreachEmailCard';

export default function OutreachChannelCard({ email, reportUrl, performanceScore, sendReadiness = null }) {
    if ((email.channel ?? 'email') === 'email') {
        return (
            <OutreachEmailCard
                email={email}
                reportUrl={reportUrl}
                performanceScore={performanceScore}
                sendReadiness={sendReadiness}
            />
        );
    }

    const [copied, setCopied] = useState(false);
    const isSent = !!email.sent_at;
    const isForm = email.channel === 'contact_form';
    const destinationUrl = isForm ? email.contact_page_url : email.linkedin_url;
    const destinationLabel = isForm
        ? (email.contact_page_url ?? 'Add contact page URL')
        : (email.linkedin_url ?? 'Add LinkedIn URL');

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
                    <div className="micro email-card-meta">
                        {email.channel_label}: {destinationLabel}
                    </div>
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
                    {destinationUrl && (
                        <a href={destinationUrl} target="_blank" rel="noopener noreferrer" className="btn-ghost btn-xs">
                            {isForm ? 'Open form' : 'Open LinkedIn'}
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
                <textarea
                    className="email-body"
                    value={email.email_body}
                    readOnly={isSent}
                    onChange={() => {}}
                />
            </div>
            <div className="email-card-footer">
                {email.sent_at && (
                    <span className="email-card-sent-date">Sent {new Date(email.sent_at).toLocaleDateString()}</span>
                )}
            </div>
        </Card>
    );
}
