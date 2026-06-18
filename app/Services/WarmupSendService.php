<?php

namespace App\Services;

use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use Illuminate\Support\Carbon;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class WarmupSendService
{
    public function __construct(
        private WarmupMailboxService $mailboxService,
    ) {}

    public function sendWarmupEmail(WarmupMailbox $from, WarmupMailbox $to): WarmupSend
    {
        $templates = config('warmup_templates');
        $subject = $templates['subjects'][array_rand($templates['subjects'])];
        $body = $templates['bodies'][array_rand($templates['bodies'])];
        $senderName = $templates['sender_names'][array_rand($templates['sender_names'])];

        $transport = Transport::fromDsn($this->mailboxService->smtpDsn($from));
        $mailer = new Mailer($transport);

        $email = (new Email)
            ->from(new Address($from->email, $senderName))
            ->to($to->email)
            ->subject($subject)
            ->text($body);

        $mailer->send($email);

        $messageId = $email->getHeaders()->get('Message-ID')?->getBodyAsString() ?? uniqid('warmup-', true);

        return WarmupSend::create([
            'from_mailbox_id' => $from->id,
            'to_mailbox_id' => $to->id,
            'message_id' => trim($messageId, '<>'),
            'subject' => $subject,
            'sent_at' => now(),
            'status' => 'sent',
        ]);
    }

    public function processInbox(WarmupMailbox $mailbox): void
    {
        $client = $this->mailboxService->makeImapClient($mailbox);
        $client->connect();

        $inbox = $client->getFolder('INBOX');
        $this->processFolder($inbox, $mailbox, false);

        foreach (['Spam', 'Junk', 'INBOX.Spam', 'INBOX.Junk'] as $folderName) {
            try {
                $spamFolder = $client->getFolder($folderName);
                $this->processFolder($spamFolder, $mailbox, true);
            } catch (\Throwable) {
                // Folder doesn't exist on this provider.
            }
        }

        $client->disconnect();

        $mailbox->update(['last_imap_check_at' => now()]);
    }

    public function replyToWarmupEmail(WarmupSend $send, WarmupMailbox $from): void
    {
        $templates = config('warmup_templates');
        $replyBody = $templates['replies'][array_rand($templates['replies'])];

        $transport = Transport::fromDsn($this->mailboxService->smtpDsn($from));
        $mailer = new Mailer($transport);

        $email = (new Email)
            ->from($from->email)
            ->to($send->fromMailbox->email)
            ->subject('Re: '.$send->subject)
            ->text($replyBody);

        $inReplyTo = trim($send->message_id);
        if (! str_starts_with($inReplyTo, '<')) {
            $inReplyTo = '<'.$inReplyTo.'>';
        }

        $email->getHeaders()->addTextHeader('In-Reply-To', $inReplyTo);

        $mailer->send($email);

        $send->update([
            'replied_at' => now(),
            'status' => 'replied',
        ]);
    }

    public function calculateDeliverabilityScore(WarmupMailbox $mailbox): int
    {
        $since = now()->subDays(7);

        $total = WarmupSend::query()
            ->where('from_mailbox_id', $mailbox->id)
            ->where('sent_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return 0;
        }

        $inboxDelivered = WarmupSend::query()
            ->where('from_mailbox_id', $mailbox->id)
            ->where('sent_at', '>=', $since)
            ->whereIn('status', ['opened', 'replied'])
            ->count();

        $rescued = WarmupSend::query()
            ->where('from_mailbox_id', $mailbox->id)
            ->where('sent_at', '>=', $since)
            ->where('status', 'rescued')
            ->count();

        $score = (($inboxDelivered + ($rescued * 0.5)) / $total) * 100;

        return (int) min(100, round($score));
    }

    public function getEstimatedReadyDate(WarmupMailbox $mailbox): ?Carbon
    {
        if ($mailbox->status === 'ready') {
            return null;
        }

        if (! $mailbox->warmup_started_at) {
            return null;
        }

        $daysRemaining = max(0, $mailbox->warmup_ramp_days - $mailbox->days_warming);

        return now()->addDays($daysRemaining)->startOfDay();
    }

    private function processFolder($folder, WarmupMailbox $mailbox, bool $isSpam): void
    {
        $messages = $folder->messages()->all()->get();

        foreach ($messages as $message) {
            $messageId = trim((string) $message->getMessageId(), '<>');

            $send = WarmupSend::query()
                ->where('message_id', $messageId)
                ->where('to_mailbox_id', $mailbox->id)
                ->first();

            if (! $send) {
                continue;
            }

            if ($isSpam) {
                $message->move('INBOX');
                $send->update([
                    'rescued_from_spam_at' => now(),
                    'status' => 'rescued',
                ]);
            } else {
                $send->update([
                    'opened_at' => now(),
                    'status' => 'opened',
                ]);
            }

            $message->setFlag('Seen');
        }
    }
}
