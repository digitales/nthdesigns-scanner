<?php

namespace App\Services;

use App\Models\WarmupMailbox;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MailboxTransportFactory
{
    public function __construct(
        private WarmupMailboxService $mailboxService,
    ) {}

    public function make(WarmupMailbox $mailbox): TransportInterface
    {
        return $this->mailboxService->makeSmtpTransport($mailbox);
    }
}
