<?php

namespace Tests\Unit;

use App\Exceptions\WarmupTransportException;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupMailboxService;
use App\Services\WarmupSendService;
use App\Support\WarmupCredentialScrubber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Transport;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\FolderCollection;
use Webklex\PHPIMAP\Support\MessageCollection;

class WarmupSendServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_calculate_deliverability_score_all_opened(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create();

        WarmupSend::factory()->count(4)->replied()->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
        ]);

        $this->assertSame(100, $this->service()->calculateDeliverabilityScore($mailbox));
    }

    public function test_calculate_deliverability_score_none_delivered(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create();

        WarmupSend::factory()->count(3)->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
            'status' => 'sent',
        ]);

        $this->assertSame(0, $this->service()->calculateDeliverabilityScore($mailbox));
    }

    public function test_calculate_deliverability_score_mixed_results(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create();

        WarmupSend::factory()->count(3)->opened()->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
        ]);

        WarmupSend::factory()->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
            'status' => 'sent',
        ]);

        $this->assertSame(75, $this->service()->calculateDeliverabilityScore($mailbox));
    }

    public function test_get_estimated_ready_date_when_warming(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->warming()->create([
            'warmup_ramp_days' => 14,
            'warmup_started_at' => now()->subDays(5),
            'status' => 'warming',
        ]);

        $this->assertSame(
            '2026-06-26',
            $this->service()->getEstimatedReadyDate($mailbox)?->toDateString(),
        );
    }

    public function test_get_estimated_ready_date_null_when_ready(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'status' => 'ready',
            'warmup_started_at' => now()->subDays(20),
        ]);

        $this->assertNull($this->service()->getEstimatedReadyDate($mailbox));
    }

    private function service(): WarmupSendService
    {
        return app(WarmupSendService::class);
    }

    public function test_send_warmup_email_stores_pre_generated_message_id(): void
    {
        config(['app.url' => 'https://example.com']);

        $uuid = Str::uuid();
        Str::createUuidsUsing(fn () => $uuid);

        $from = WarmupMailbox::factory()->outreach()->create();
        $to = WarmupMailbox::factory()->create();

        $this->mock(WarmupMailboxService::class, function ($mock) {
            $mock->shouldReceive('makeSmtpTransport')->andReturn(
                Transport::fromDsn('null://null'),
            );
        });

        $send = $this->service()->sendWarmupEmail($from, $to);

        $this->assertSame($uuid.'@example.com', $send->message_id);

        Str::createUuidsNormally();
    }

    #[DataProvider('spamFolderNameProvider')]
    public function test_process_inbox_detects_spam_folders(string $folderName): void
    {
        $mailbox = WarmupMailbox::factory()->create();
        $send = WarmupSend::factory()->create([
            'to_mailbox_id' => $mailbox->id,
            'message_id' => 'matched-message-id',
            'status' => 'sent',
        ]);

        $message = Mockery::mock();
        $message->shouldReceive('getMessageId')->andReturn('<matched-message-id>');
        $message->shouldReceive('move')->with('INBOX');
        $message->shouldReceive('setFlag')->with('Seen');

        $spamFolder = $this->mockFolder($folderName, [$message]);
        $inboxFolder = $this->mockFolder('INBOX', []);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('connect');
        $client->shouldReceive('disconnect');
        $client->shouldReceive('getFolder')->with('INBOX')->andReturn($inboxFolder);
        $client->shouldReceive('getFolders')->with(false)->andReturn(new FolderCollection([$inboxFolder, $spamFolder]));

        $this->mock(WarmupMailboxService::class, function ($mock) use ($client) {
            $mock->shouldReceive('makeImapClient')->andReturn($client);
        });

        $this->service()->processInbox($mailbox);

        $send->refresh();
        $this->assertSame('rescued', $send->status);
        $this->assertNotNull($send->rescued_from_spam_at);
    }

    public function test_process_inbox_returns_actionable_send_ids_for_newly_rescued_messages(): void
    {
        $mailbox = WarmupMailbox::factory()->create();
        $send = WarmupSend::factory()->create([
            'to_mailbox_id' => $mailbox->id,
            'message_id' => 'matched-message-id',
            'status' => 'sent',
        ]);

        $message = Mockery::mock();
        $message->shouldReceive('getMessageId')->andReturn('<matched-message-id>');
        $message->shouldReceive('move')->with('INBOX');
        $message->shouldReceive('setFlag')->with('Seen');

        $spamFolder = $this->mockFolder('[Gmail]/Spam', [$message]);
        $inboxFolder = $this->mockFolder('INBOX', []);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('connect');
        $client->shouldReceive('disconnect');
        $client->shouldReceive('getFolder')->with('INBOX')->andReturn($inboxFolder);
        $client->shouldReceive('getFolders')->with(false)->andReturn(new FolderCollection([$inboxFolder, $spamFolder]));

        $this->mock(WarmupMailboxService::class, function ($mock) use ($client) {
            $mock->shouldReceive('makeImapClient')->andReturn($client);
        });

        $actionableSendIds = $this->service()->processInbox($mailbox);

        $this->assertSame([$send->id], $actionableSendIds);
    }

    public function test_process_inbox_does_not_reopen_replied_messages(): void
    {
        $mailbox = WarmupMailbox::factory()->create();
        $send = WarmupSend::factory()->replied()->create([
            'to_mailbox_id' => $mailbox->id,
            'message_id' => 'matched-message-id',
        ]);

        $message = Mockery::mock();
        $message->shouldReceive('getMessageId')->andReturn('<matched-message-id>');
        $message->shouldReceive('setFlag')->with('Seen');

        $inboxFolder = $this->mockFolder('INBOX', [$message]);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('connect');
        $client->shouldReceive('disconnect');
        $client->shouldReceive('getFolder')->with('INBOX')->andReturn($inboxFolder);
        $client->shouldReceive('getFolders')->with(false)->andReturn(new FolderCollection([$inboxFolder]));

        $this->mock(WarmupMailboxService::class, function ($mock) use ($client) {
            $mock->shouldReceive('makeImapClient')->andReturn($client);
        });

        $actionableSendIds = $this->service()->processInbox($mailbox);

        $this->assertSame([], $actionableSendIds);
        $this->assertSame('replied', $send->fresh()->status);
    }

    public function test_reply_to_warmup_email_skips_already_replied_sends(): void
    {
        $from = WarmupMailbox::factory()->create();
        $outreach = WarmupMailbox::factory()->outreach()->create();
        $send = WarmupSend::factory()->replied()->create([
            'from_mailbox_id' => $outreach->id,
        ]);

        $service = Mockery::mock(WarmupSendService::class, [app(WarmupMailboxService::class)])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldNotReceive('sendEmail');

        $service->replyToWarmupEmail($send, $from);
    }

    public static function spamFolderNameProvider(): array
    {
        return [
            'gmail spam' => ['[Gmail]/Spam'],
            'outlook junk email' => ['Junk Email'],
            'fastmail inbox junk' => ['INBOX.Junk'],
        ];
    }

    public function test_process_inbox_handles_account_with_no_spam_folder(): void
    {
        $mailbox = WarmupMailbox::factory()->create();

        $inboxFolder = $this->mockFolder('INBOX', []);
        $archiveFolder = $this->mockFolder('Archive', []);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('connect');
        $client->shouldReceive('disconnect');
        $client->shouldReceive('getFolder')->with('INBOX')->andReturn($inboxFolder);
        $client->shouldReceive('getFolders')->with(false)->andReturn(new FolderCollection([$inboxFolder, $archiveFolder]));

        $this->mock(WarmupMailboxService::class, function ($mock) use ($client) {
            $mock->shouldReceive('makeImapClient')->andReturn($client);
        });

        $this->service()->processInbox($mailbox);

        $this->assertNotNull($mailbox->fresh()->last_imap_check_at);
    }

    public function test_process_inbox_uses_since_filter_from_last_imap_check_at(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        $mailbox = WarmupMailbox::factory()->create([
            'last_imap_check_at' => Carbon::parse('2026-06-17 08:00:00'),
        ]);

        $sinceMatcher = Mockery::on(fn ($value) => Carbon::parse($value)->equalTo($mailbox->last_imap_check_at));

        $query = Mockery::mock(WhereQuery::class);
        $query->shouldReceive('since')->once()->with($sinceMatcher)->andReturnSelf();
        $query->shouldReceive('get')->andReturn(new MessageCollection);

        $inboxFolder = Mockery::mock(Folder::class);
        $inboxFolder->name = 'INBOX';
        $inboxFolder->full_name = 'INBOX';
        $inboxFolder->shouldReceive('messages')->andReturn($query);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('connect');
        $client->shouldReceive('disconnect');
        $client->shouldReceive('getFolder')->with('INBOX')->andReturn($inboxFolder);
        $client->shouldReceive('getFolders')->with(false)->andReturn(new FolderCollection([$inboxFolder]));

        $this->mock(WarmupMailboxService::class, function ($mock) use ($client) {
            $mock->shouldReceive('makeImapClient')->andReturn($client);
        });

        $this->service()->processInbox($mailbox);
    }

    public function test_process_inbox_widens_since_filter_when_stale_sends_exist(): void
    {
        Carbon::setTestNow('2026-06-19 10:00:00');

        $mailbox = WarmupMailbox::factory()->create([
            'last_imap_check_at' => Carbon::parse('2026-06-19 09:00:00'),
        ]);

        WarmupSend::factory()->create([
            'to_mailbox_id' => $mailbox->id,
            'status' => 'sent',
            'sent_at' => Carbon::parse('2026-06-18 14:31:25'),
        ]);

        $expectedSince = Carbon::parse('2026-06-18 13:31:25');
        $sinceMatcher = Mockery::on(fn ($value) => Carbon::parse($value)->equalTo($expectedSince));

        $query = Mockery::mock(WhereQuery::class);
        $query->shouldReceive('since')->once()->with($sinceMatcher)->andReturnSelf();
        $query->shouldReceive('get')->andReturn(new MessageCollection);

        $inboxFolder = Mockery::mock(Folder::class);
        $inboxFolder->name = 'INBOX';
        $inboxFolder->full_name = 'INBOX';
        $inboxFolder->shouldReceive('messages')->andReturn($query);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('connect');
        $client->shouldReceive('disconnect');
        $client->shouldReceive('getFolder')->with('INBOX')->andReturn($inboxFolder);
        $client->shouldReceive('getFolders')->with(false)->andReturn(new FolderCollection([$inboxFolder]));

        $this->mock(WarmupMailboxService::class, function ($mock) use ($client) {
            $mock->shouldReceive('makeImapClient')->andReturn($client);
        });

        $this->service()->processInbox($mailbox);
    }

    public function test_process_inbox_does_not_update_last_imap_check_at_on_failure(): void
    {
        $mailbox = WarmupMailbox::factory()->create([
            'last_imap_check_at' => Carbon::parse('2026-06-17 08:00:00'),
        ]);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('connect');
        $client->shouldReceive('disconnect');
        $client->shouldReceive('getFolder')->with('INBOX')->andThrow(new \RuntimeException('IMAP failed'));

        $this->mock(WarmupMailboxService::class, function ($mock) use ($client) {
            $mock->shouldReceive('makeImapClient')->andReturn($client);
        });

        try {
            $this->service()->processInbox($mailbox);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($mailbox->fresh()->last_imap_check_at->equalTo(Carbon::parse('2026-06-17 08:00:00')));
    }

    public function test_reply_to_warmup_email_sets_references_header(): void
    {
        $from = WarmupMailbox::factory()->create();
        $outreach = WarmupMailbox::factory()->outreach()->create();
        $send = WarmupSend::factory()->opened()->create([
            'from_mailbox_id' => $outreach->id,
            'message_id' => 'abc-123@example.com',
        ]);

        $capturedEmail = null;

        $service = Mockery::mock(WarmupSendService::class, [app(WarmupMailboxService::class)])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('sendEmail')->once()->andReturnUsing(function ($mailer, $email) use (&$capturedEmail) {
            $capturedEmail = $email;
        });

        $service->replyToWarmupEmail($send, $from);

        $this->assertSame('<abc-123@example.com>', $capturedEmail->getHeaders()->get('In-Reply-To')->getBodyAsString());
        $this->assertSame('<abc-123@example.com>', $capturedEmail->getHeaders()->get('References')->getBodyAsString());
    }

    public function test_transport_exception_scrubs_credentials_from_message(): void
    {
        $password = 'SuperSecretPassword123';
        $from = WarmupMailbox::factory()->outreach()->create([
            'username' => 'user@example.com',
            'password_encrypted' => $password,
        ]);
        $to = WarmupMailbox::factory()->create();

        $this->mock(WarmupMailboxService::class, function ($mock) use ($password) {
            $mock->shouldReceive('makeSmtpTransport')
                ->andReturn(Transport::fromDsn(
                    'smtp://user%40example.com:'.urlencode($password).'@127.0.0.1:1',
                ));
        });

        $this->expectException(WarmupTransportException::class);

        try {
            $this->service()->sendWarmupEmail($from, $to);
        } catch (WarmupTransportException $e) {
            $this->assertStringNotContainsString($password, $e->getMessage());
            $this->assertStringNotContainsString(urlencode($password), $e->getMessage());

            throw $e;
        }
    }

    public function test_credential_scrubber_removes_dsn_passwords(): void
    {
        $scrubbed = WarmupCredentialScrubber::scrub(
            'Connection failed: smtp://user:SecretPass@mail.example.com:587'
        );

        $this->assertStringNotContainsString('SecretPass', $scrubbed);
        $this->assertStringContainsString('[credentials redacted]', $scrubbed);
    }

    private function mockFolder(string $name, array $messages): Folder
    {
        $query = Mockery::mock(WhereQuery::class);
        $query->shouldReceive('since')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(new MessageCollection($messages));

        $folder = Mockery::mock(Folder::class);
        $folder->name = $name;
        $folder->full_name = $name;
        $folder->shouldReceive('messages')->andReturn($query);

        return $folder;
    }
}
