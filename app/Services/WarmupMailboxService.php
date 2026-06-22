<?php

namespace App\Services;

use App\Models\WarmupMailbox;
use App\Support\WarmupCredentialScrubber;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\Auth\PlainAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
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
        $results = $this->connectionResults($mailbox);

        if (! $results['imap']['ok']) {
            throw new RuntimeException('IMAP connection failed: '.$results['imap']['error']);
        }

        if (! $results['smtp']['ok']) {
            throw new RuntimeException('SMTP authentication failed: '.$results['smtp']['error']);
        }

        return true;
    }

    /**
     * @return array{imap: array{ok: bool, error: ?string}, smtp: array{ok: bool, error: ?string}}
     */
    public function connectionResults(WarmupMailbox $mailbox): array
    {
        $imap = ['ok' => false, 'error' => null];
        $smtp = ['ok' => false, 'error' => null];

        try {
            $this->testImap($mailbox);
            $imap['ok'] = true;
        } catch (RuntimeException $e) {
            $imap['error'] = WarmupCredentialScrubber::scrub($e->getMessage());
        }

        try {
            $this->testSmtp($mailbox);
            $smtp['ok'] = true;
        } catch (RuntimeException $e) {
            $smtp['error'] = WarmupCredentialScrubber::scrub($e->getMessage());
        }

        return compact('imap', 'smtp');
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
            throw new RuntimeException(WarmupCredentialScrubber::scrub($e->getMessage()));
        }
    }

    private function testSmtp(WarmupMailbox $mailbox): void
    {
        try {
            $transport = $this->makeSmtpTransport($mailbox);

            if ($transport instanceof SmtpTransport) {
                $transport->start();
                $transport->stop();
            }
        } catch (\Throwable $e) {
            throw new RuntimeException(WarmupCredentialScrubber::scrub($e->getMessage()));
        }
    }

    public function makeSmtpTransport(WarmupMailbox $mailbox): TransportInterface
    {
        $port = $mailbox->smtp_port;
        $implicitTls = $port === 465;

        $transport = new EsmtpTransport(
            $mailbox->smtp_host,
            $port,
            $implicitTls ? true : null,
        );

        if (! $implicitTls) {
            $transport->setAutoTls(true);
        }

        $transport->setUsername($mailbox->username);
        $transport->setPassword($mailbox->decrypted_password);
        $transport->setAuthenticators([
            new LoginAuthenticator,
            new PlainAuthenticator,
        ]);

        return $transport;
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
        $scheme = $mailbox->smtp_port === 465 ? 'smtps' : 'smtp';

        return sprintf(
            '%s://%s:%s@%s:%d',
            $scheme,
            rawurlencode($mailbox->username),
            rawurlencode($mailbox->decrypted_password),
            $mailbox->smtp_host,
            $mailbox->smtp_port,
        );
    }
}
