<?php

namespace Tests\Unit;

use App\Models\WarmupMailbox;
use App\Services\WarmupMailboxService;
use App\Services\WarmupSeedPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\Auth\PlainAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;

class WarmupMailboxServiceTest extends TestCase
{
    use RefreshDatabase;

    private WarmupMailboxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WarmupMailboxService::class);
    }

    public function test_get_daily_volume_before_warmup_starts(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => null,
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(5, $this->service->getDailyVolume($mailbox));
    }

    public function test_get_daily_volume_on_day_one(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => now()->subDay(),
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(8, $this->service->getDailyVolume($mailbox));
    }

    public function test_get_daily_volume_on_day_seven(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => now()->subDays(7),
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(28, $this->service->getDailyVolume($mailbox));
    }

    public function test_get_daily_volume_at_ramp_completion(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => now()->subDays(14),
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(50, $this->service->getDailyVolume($mailbox));
    }

    public function test_get_daily_volume_after_ramp(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => now()->subDays(20),
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(50, $this->service->getDailyVolume($mailbox));
    }

    public function test_make_smtp_transport_uses_login_and_plain_authenticators_only(): void
    {
        $mailbox = WarmupMailbox::factory()->make([
            'username' => 'user@example.com',
            'password_encrypted' => 'app-password',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
        ]);

        $transport = $this->service->makeSmtpTransport($mailbox);

        $this->assertInstanceOf(EsmtpTransport::class, $transport);

        $authenticators = (new \ReflectionProperty(EsmtpTransport::class, 'authenticators'))
            ->getValue($transport);

        $this->assertCount(2, $authenticators);
        $this->assertInstanceOf(LoginAuthenticator::class, $authenticators[0]);
        $this->assertInstanceOf(PlainAuthenticator::class, $authenticators[1]);
    }

    public function test_verify_connection_starts_and_stops_smtp_transport(): void
    {
        $mailbox = WarmupMailbox::factory()->make([
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'username' => 'user@example.com',
            'password_encrypted' => 'secret',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
        ]);

        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('start')->once();
        $transport->shouldReceive('stop')->once();

        $service = Mockery::mock(WarmupMailboxService::class, [$this->app->make(WarmupSeedPoolService::class)])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('makeImapClient')->once()->andReturnUsing(function () {
            $client = Mockery::mock(Client::class);
            $client->shouldReceive('connect')->once();
            $client->shouldReceive('disconnect')->once();

            return $client;
        });
        $service->shouldReceive('makeSmtpTransport')->once()->andReturn($transport);

        $service->verifyConnection($mailbox);
    }

    public function test_verify_connection_surfaces_smtp_authentication_failures(): void
    {
        $mailbox = WarmupMailbox::factory()->make([
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'username' => 'user@example.com',
            'password_encrypted' => 'secret',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
        ]);

        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('start')->once()->andThrow(new RuntimeException('535 bad credentials'));

        $service = Mockery::mock(WarmupMailboxService::class, [$this->app->make(WarmupSeedPoolService::class)])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('makeImapClient')->once()->andReturnUsing(function () {
            $client = Mockery::mock(Client::class);
            $client->shouldReceive('connect')->once();
            $client->shouldReceive('disconnect')->once();

            return $client;
        });
        $service->shouldReceive('makeSmtpTransport')->once()->andReturn($transport);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMTP authentication failed:');

        $service->verifyConnection($mailbox);
    }
}
