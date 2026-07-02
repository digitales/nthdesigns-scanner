import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    AnglePill,
    Button,
    Card,
    RowActions,
    ScoreBadge,
} from '@/Components/ui';

export default function OutreachEmailCard({ email, reportUrl, performanceScore, sendReadiness = null }) {
    const [copied, setCopied] = useState(false);
    const [sending, setSending] = useState(false);
    const [saving, setSaving] = useState(false);
    const [confirmingWarned, setConfirmingWarned] = useState(false);
    const [draftSubject, setDraftSubject] = useState(email.subject_line ?? '');
    const [draftBody, setDraftBody] = useState(email.email_body ?? '');
    const isSent = !!email.sent_at;
    const sendTier = sendReadiness?.tier ?? 'allowed';
    const sendReason = sendReadiness?.reason ?? '';
    const isBlocked = !isSent && sendTier === 'blocked';
    const requiresWarnConfirmation = !isSent && sendTier === 'warn';
    const sentSubject = email.sent_subject ?? email.subject_line ?? '';
    const sentBody = email.sent_body ?? email.email_body ?? '';
    const displaySubject = isSent ? sentSubject : draftSubject;
    const displayBody = isSent ? sentBody : draftBody;
    const hasGeneratedCopy = Boolean(email.generated_subject || email.generated_body);
    const sentDiffersFromGenerated = isSent
        && hasGeneratedCopy
        && (
            (email.generated_subject ?? '') !== sentSubject
            || (email.generated_body ?? '') !== sentBody
        );
    const showEditedHistory = Boolean(email.was_edited || sentDiffersFromGenerated) && hasGeneratedCopy;

    useEffect(() => {
        setDraftSubject(email.subject_line ?? '');
        setDraftBody(email.email_body ?? '');
        setConfirmingWarned(false);
    }, [email.id, email.subject_line, email.email_body]);

    const saveDraft = () => {
        if (isSent || saving) {
            return;
        }

        const nextSubject = draftSubject;
        const nextBody = draftBody;
        const unchanged = nextSubject === (email.subject_line ?? '')
            && nextBody === (email.email_body ?? '');

        if (unchanged) {
            return;
        }

        setSaving(true);
        router.patch(
            `/outreach-emails/${email.id}`,
            {
                subject_line: nextSubject,
                email_body: nextBody,
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const sendEmail = () => {
        if (isSent || sending || saving || isBlocked) {
            return;
        }

        if (requiresWarnConfirmation && !confirmingWarned) {
            setConfirmingWarned(true);
            return;
        }

        setSending(true);
        router.post(
            `/outreach-emails/${email.id}/send`,
            {
                confirm_warned: requiresWarnConfirmation ? true : undefined,
            },
            {
                preserveScroll: true,
                onFinish: () => setSending(false),
            },
        );
    };

    const markResponse = () => router.patch(`/outreach-emails/${email.id}/response`);

    const copyBody = () => {
        navigator.clipboard.writeText(displayBody);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const showSlowSite = performanceScore != null && performanceScore < 30;
    const showBlockedBanner = isBlocked && sendReason;
    const showWarnBanner = requiresWarnConfirmation && confirmingWarned && sendReason;
    const sentFooter = useMemo(() => {
        if (!email.sent_at) {
            return null;
        }

        const sentDate = new Date(email.sent_at).toLocaleDateString();
        if (email.send_source === 'app') {
            return `Sent ${sentDate} from ${email.from_mailbox_email ?? 'connected mailbox'}`;
        }

        return `Sent ${sentDate}`;
    }, [email.sent_at, email.send_source, email.from_mailbox_email]);

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
                        <Button
                            kind={showWarnBanner ? 'secondary' : 'primary'}
                            size="xs"
                            onClick={sendEmail}
                            disabled={isBlocked || sending || saving}
                        >
                            {sending ? 'Sending…' : showWarnBanner ? 'Confirm send' : 'Send'}
                        </Button>
                    )}
                    {isSent && !email.response_received && (
                        <button type="button" className="btn-ghost btn-xs" onClick={markResponse}>Got response</button>
                    )}
                </RowActions>
            </div>
            {showBlockedBanner && (
                <div className="skip-banner skip-banner--critical">
                    {sendReason}
                </div>
            )}
            {showWarnBanner && (
                <div className="skip-banner">
                    {sendReason}
                </div>
            )}
            <div className="email-card-body">
                {showEditedHistory && (
                    <div className="mb-8">
                        <span className="badge badge--queue">Edited</span>
                    </div>
                )}
                <input
                    className="email-subject"
                    value={displaySubject}
                    readOnly={isSent}
                    onChange={(e) => setDraftSubject(e.target.value)}
                    onBlur={saveDraft}
                />
                <textarea
                    className="email-body"
                    value={displayBody}
                    readOnly={isSent}
                    onChange={(e) => setDraftBody(e.target.value)}
                    onBlur={saveDraft}
                />
                {showEditedHistory && (
                    <details className="mt-8">
                        <summary className="micro">Original generated copy</summary>
                        <div className="mt-8">
                            <input
                                className="email-subject"
                                value={email.generated_subject ?? ''}
                                readOnly
                                onChange={() => {}}
                            />
                            <textarea
                                className="email-body"
                                value={email.generated_body ?? ''}
                                readOnly
                                onChange={() => {}}
                            />
                        </div>
                    </details>
                )}
            </div>
            <div className="email-card-footer">
                {reportUrl && <span>{reportUrl.replace(/^https?:\/\/[^/]+/, '')}</span>}
                {sentFooter && (
                    <span className="email-card-sent-date">{sentFooter}</span>
                )}
            </div>
        </Card>
    );
}
