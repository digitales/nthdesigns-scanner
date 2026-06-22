<?php

namespace App\Services;

use App\Models\WarmupMailbox;
use App\Support\WarmupCredentialScrubber;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Mailer\Transport;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class WarmupMailboxService
{
    public function __construct(
        private WarmupSeedPoolService $seedPoolService,
    ) {}

    public function connect(array $data): WarmupMailbox
    {
        $testMailbox = new WarmupMailbox($data);
        $this->verifyConnection($testMailbox);

        return WarmupMailbox::create($data);
    }

    public function verifyConnection(WarmupMailbox $mailbox): bool
    {
        $this->testImap($mailbox);
        $this->testSmtp($mailbox);

        return true;
    }

    public function getDailyVolume(WarmupMailbox $mailbox): int
    {
        if (! $mailbox->warmup_started_at) {
            return 5;
        }

        $daysWarming = $mailbox->days_warming;
        $rampDays = $mailbox->warmup_ramp_days;
        $targetVolume = $mailbox->warmup_target_volume;
        $startVolume = 5;

        if ($daysWarming >= $rampDays) {
            return $targetVolume;
        }

        return (int) round($startVolume + ($targetVolume - $startVolume) * ($daysWarming / $rampDays));
    }

    public function getSeedPool(WarmupMailbox $outbox): Collection
    {
        return $this->seedPoolService->eligibleSeedsForOutbox($outbox);
    }

    /**
     * @return array{own: Collection<int, WarmupMailbox>, pool: Collection<int, WarmupMailbox>}
     */
    public function getSeedGroups(WarmupMailbox $outbox): array
    {
        return $this->seedPoolService->seedGroupsForOutbox($outbox);
    }

    private function testImap(WarmupMailbox $mailbox): void
    {
        try {
            $client = $this->makeImapClient($mailbox);
            $client->connect();
            $client->disconnect();
        } catch (\Throwable $e) {
            throw new RuntimeException('IMAP connection failed: '.WarmupCredentialScrubber::scrub($e->getMessage()));
        }
    }

    private function testSmtp(WarmupMailbox $mailbox): void
    {
        try {
            Transport::fromDsn($this->smtpDsn($mailbox));
        } catch (\Throwable $e) {
            throw new RuntimeException('SMTP configuration invalid: '.WarmupCredentialScrubber::scrub($e->getMessage()));
        }
    }

    public function makeImapClient(WarmupMailbox $mailbox): Client
    {
        $cm = new ClientManager;

        return $cm->make([
            'host' => $mailbox->imap_host,
            'port' => $mailbox->imap_port,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $mailbox->username,
            'password' => $mailbox->decrypted_password,
            'protocol' => 'imap',
        ]);
    }

    public function smtpDsn(WarmupMailbox $mailbox): string
    {
        $dsn = sprintf(
            'smtp://%s:%s@%s:%d',
            urlencode($mailbox->username),
            urlencode($mailbox->decrypted_password),
            $mailbox->smtp_host,
            $mailbox->smtp_port,
        );

        if ($mailbox->smtp_port === 587) {
            $dsn .= '?encryption=starttls';
        }

        return $dsn;
    }
}
