<?php

namespace App\Services\Outreach;

use App\Enums\OutreachChannel;
use App\Enums\OutreachSendSource;
use App\Models\OutreachEmail;
use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\MailboxTransportFactory;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

class OutreachSendService
{
    public function __construct(
        private ProspectUnsubscribeService $unsubscribe,
        private MailboxTransportFactory $transportFactory,
    ) {}

    public function resolveTier(User $user): OutreachSendReadiness
    {
        $mailbox = $this->resolveSendingMailbox($user);

        if ($mailbox === null) {
            return new OutreachSendReadiness('blocked', 'no_mailbox', false);
        }

        if (in_array($mailbox->status, ['failed', 'paused'], true)) {
            return new OutreachSendReadiness('blocked', 'mailbox_unavailable', false);
        }

        $score = (int) ($mailbox->deliverability_score ?? 0);

        if ($score < 60) {
            return new OutreachSendReadiness('blocked', 'score_below_60', false);
        }

        $softDailyCap = (int) config('outreach.soft_daily_cap', 20);

        if ($mailbox->status !== 'ready') {
            return new OutreachSendReadiness('warn', 'mailbox_not_ready', true);
        }

        if ($score < 80) {
            return new OutreachSendReadiness('warn', 'score_below_80', true);
        }

        if ($softDailyCap > 0 && $this->coldSendsToday($user) >= $softDailyCap) {
            return new OutreachSendReadiness('warn', 'soft_daily_cap_reached', true);
        }

        return new OutreachSendReadiness('allowed', 'ready', false);
    }

    public function validateDraft(User $user, OutreachEmail $draft): void
    {
        if ($draft->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'outreach_email' => 'This draft does not belong to the current user.',
            ]);
        }

        if ($draft->sent_at !== null) {
            throw ValidationException::withMessages([
                'outreach_email' => 'This draft has already been sent.',
            ]);
        }

        if ($draft->channel !== OutreachChannel::Email) {
            throw ValidationException::withMessages([
                'channel' => 'Only email outreach can be sent in-app.',
            ]);
        }

        if (blank($draft->subject_line)) {
            throw ValidationException::withMessages([
                'subject_line' => 'Subject is required before sending.',
            ]);
        }

        if (blank($draft->email_body)) {
            throw ValidationException::withMessages([
                'email_body' => 'Email body is required before sending.',
            ]);
        }

        $prospect = $draft->prospect;

        if ($prospect === null) {
            throw ValidationException::withMessages([
                'prospect' => 'Draft prospect could not be resolved.',
            ]);
        }

        if (blank($prospect->email)) {
            throw ValidationException::withMessages([
                'email' => 'Prospect has no contact email.',
            ]);
        }

        if (! $this->unsubscribe->bodyContainsUnsubscribeFooter(
            $draft->email_body,
            $user,
            $prospect,
            $prospect->email,
        )) {
            throw ValidationException::withMessages([
                'email_body' => 'Email body must include the unsubscribe footer.',
            ]);
        }
    }

    public function send(User $user, OutreachEmail $draft, bool $confirmWarned): OutreachEmail
    {
        $this->validateDraft($user, $draft);

        $readiness = $this->resolveTier($user);

        if ($readiness->tier === 'blocked') {
            throw ValidationException::withMessages([
                'mailbox' => 'Outreach sending is blocked: '.$readiness->reason.'.',
            ]);
        }

        if ($readiness->tier === 'warn' && ! $confirmWarned) {
            throw ValidationException::withMessages([
                'confirmation' => 'Please confirm sending from a warned mailbox state.',
            ]);
        }

        $mailbox = $this->resolveSendingMailbox($user);

        if ($mailbox === null) {
            throw ValidationException::withMessages([
                'mailbox' => 'No outreach mailbox is available for sending.',
            ]);
        }

        $transport = $this->transportFactory->make($mailbox);
        $mailer = new Mailer($transport);

        $messageId = $this->messageId();
        $toEmail = $draft->prospect?->email;

        if (blank($toEmail)) {
            throw ValidationException::withMessages([
                'email' => 'Prospect has no contact email.',
            ]);
        }

        $email = (new Email)
            ->from($mailbox->email)
            ->to($toEmail)
            ->subject((string) $draft->subject_line)
            ->text((string) $draft->email_body);

        $email->getHeaders()->addIdHeader('Message-ID', $messageId);
        $mailer->send($email);

        $draft->forceFill([
            'sent_subject' => $draft->subject_line,
            'sent_body' => $draft->email_body,
            'sent_at' => now(),
            'from_mailbox_id' => $mailbox->id,
            'smtp_message_id' => $messageId,
            'send_source' => OutreachSendSource::App,
        ])->save();

        return $draft->fresh();
    }

    private function resolveSendingMailbox(User $user): ?WarmupMailbox
    {
        $mailboxes = $user->warmupMailboxes()
            ->where('is_outreach_mailbox', true)
            ->orderBy('id')
            ->get();

        if ($mailboxes->isEmpty()) {
            return null;
        }

        $readyMailbox = $mailboxes->firstWhere('status', 'ready');

        return $readyMailbox ?? $mailboxes->first();
    }

    private function coldSendsToday(User $user): int
    {
        return OutreachEmail::query()
            ->where('user_id', $user->id)
            ->where('channel', OutreachChannel::Email->value)
            ->whereDate('sent_at', today())
            ->count();
    }

    private function messageId(): string
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return sprintf('%s@%s', (string) Str::uuid(), $host);
    }
}
