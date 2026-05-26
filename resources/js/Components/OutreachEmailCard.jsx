import { router } from '@inertiajs/react';

export default function OutreachEmailCard({ email }) {
    const markSent = () => router.patch(`/outreach-emails/${email.id}/sent`);
    const markResponse = () => router.patch(`/outreach-emails/${email.id}/response`);

    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <div className="font-medium text-gray-900">{email.subject_line}</div>
                    <div className="text-xs text-gray-400 mt-0.5">
                        {email.pitch_angle} · {email.created_at}
                        {email.model_used && ` · ${email.model_used}`}
                    </div>
                </div>
                <div className="flex gap-2 shrink-0">
                    {!email.sent_at && (
                        <button
                            type="button"
                            onClick={markSent}
                            className="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1 rounded"
                        >
                            Mark sent
                        </button>
                    )}
                    {email.sent_at && !email.response_received && (
                        <button
                            type="button"
                            onClick={markResponse}
                            className="text-xs bg-green-50 hover:bg-green-100 text-green-700 px-2 py-1 rounded"
                        >
                            Got response
                        </button>
                    )}
                    {email.response_received && (
                        <span className="text-xs text-green-600 font-medium">Responded</span>
                    )}
                </div>
            </div>
            <pre className="text-sm text-gray-700 whitespace-pre-wrap font-sans bg-gray-50 rounded-lg p-4 border border-gray-100">
                {email.email_body}
            </pre>
        </div>
    );
}
