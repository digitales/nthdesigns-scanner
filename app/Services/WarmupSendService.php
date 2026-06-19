<?php

namespace App\Services;

use App\Exceptions\WarmupTransportException;
use App\Jobs\SendWarmupEmailJob;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
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

        $transport = $this->createTransport($from);
        $mailer = new Mailer($transport);

        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $messageId = sprintf('%s@%s', (string) Str::uuid(), $host);

        $email = (new Email)
            ->from(new Address($from->email, $senderName))
            ->to($to->email)
            ->subject($subject)
            ->text($body);

        $email->getHeaders()->addIdHeader('Message-ID', $messageId);

        $this->sendEmail($mailer, $email);

        return WarmupSend::create([
            'from_mailbox_id' => $from->id,
            'to_mailbox_id' => $to->id,
            'message_id' => $messageId,
            'subject' => $subject,
            'sent_at' => now(),
            'status' => 'sent',
        ]);
    }

    public function recordBouncedSend(WarmupMailbox $from, WarmupMailbox $to): WarmupSend
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $messageId = sprintf('%s@%s', (string) Str::uuid(), $host);

        return WarmupSend::create([
            'from_mailbox_id' => $from->id,
            'to_mailbox_id' => $to->id,
            'message_id' => $messageId,
            'subject' => 'Undelivered warmup',
            'sent_at' => now(),
            'status' => 'bounced',
        ]);
    }

    public function processInbox(WarmupMailbox $mailbox): void
    {
        $client = $this->mailboxService->makeImapClient($mailbox);
        $client->connect();

        try {
            $inbox = $client->getFolder('INBOX');
            $this->processFolder($inbox, $mailbox, false);

            foreach ($client->getFolders(false) as $folder) {
                $folderName = $folder->full_name ?? $folder->name;

                if (strcasecmp($folderName, 'INBOX') === 0) {
                    continue;
                }

                if (! preg_match('/spam|junk/i', $folderName)) {
                    continue;
                }

                try {
                    $this->processFolder($folder, $mailbox, true);
                } catch (\Throwable $e) {
                    Log::warning('Warmup spam folder scan failed.', [
                        'mailbox_id' => $mailbox->id,
                        'folder' => $folderName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $mailbox->update(['last_imap_check_at' => now()]);
        } finally {
            $client->disconnect();
        }
    }

    public function replyToWarmupEmail(WarmupSend $send, WarmupMailbox $from): void
    {
        $templates = config('warmup_templates');
        $replyBody = $templates['replies'][array_rand($templates['replies'])];

        $transport = $this->createTransport($from);
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
        $email->getHeaders()->addTextHeader('References', $inReplyTo);

        $this->sendEmail($mailer, $email);

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
        $since = $this->sinceForScan($mailbox);

        $messages = $folder->messages()->since($since)->get();

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

    private function sinceForScan(WarmupMailbox $mailbox): Carbon
    {
        $since = $mailbox->last_imap_check_at ?? now()->subDays(2);

        $oldestStale = WarmupSend::query()
            ->where('to_mailbox_id', $mailbox->id)
            ->where('status', 'sent')
            ->where('sent_at', '<', now()->subMinutes(SendWarmupEmailJob::INBOX_CHECK_DELAY_MINUTES))
            ->min('sent_at');

        if ($oldestStale !== null) {
            $oldestStaleAt = Carbon::parse($oldestStale)->subHour();

            if ($oldestStaleAt->lt($since)) {
                $since = $oldestStaleAt;
            }
        }

        return $since;
    }

    protected function createTransport(WarmupMailbox $from): TransportInterface
    {
        try {
            return Transport::fromDsn($this->mailboxService->smtpDsn($from));
        } catch (\Throwable $e) {
            throw WarmupTransportException::fromThrowable($e);
        }
    }

    protected function sendEmail(Mailer $mailer, Email $email): void
    {
        try {
            $mailer->send($email);
        } catch (\Throwable $e) {
            throw WarmupTransportException::fromThrowable($e);
        }
    }
}
