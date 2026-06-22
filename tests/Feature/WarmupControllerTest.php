<?php

namespace Tests\Feature;

use App\Jobs\ProcessWarmupJob;
use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\WarmupMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WarmupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mailboxPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'test@example.com',
            'provider' => 'fastmail',
            'imap_host' => 'imap.fastmail.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.fastmail.com',
            'smtp_port' => 587,
            'username' => 'test@example.com',
            'password' => 'secret',
            'is_outreach_mailbox' => true,
            'is_seed_mailbox' => false,
        ], $overrides);
    }

    public function test_warmup_index_requires_auth(): void
    {
        $this->get('/warmup')->assertRedirect('/login');
    }

    public function test_warmup_index_lists_user_mailboxes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
            'email' => 'ross@nthdesign.co.uk',
        ]);

        WarmupMailbox::factory()->create([
            'user_id' => $other->id,
            'email' => 'other@example.com',
        ]);

        $this->actingAs($user)
            ->get('/warmup')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Warmup/Index')
                ->has('mailboxes', 1)
                ->where('mailboxes.0.email', 'ross@nthdesign.co.uk')
            );
    }

    public function test_user_cannot_view_another_users_mailbox(): void
    {
        $user = User::factory()->create();
        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'user_id' => User::factory(),
        ]);

        $this->actingAs($user)
            ->get("/warmup/{$mailbox->id}")
            ->assertForbidden();
    }

    public function test_solo_tier_allows_first_outreach_mailbox(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'solo']);

        $this->mock(WarmupMailboxService::class, function ($mock) {
            $mock->shouldReceive('connect')->once()->andReturn(WarmupMailbox::factory()->make());
        });

        $this->actingAs($user)
            ->post('/warmup', $this->mailboxPayload())
            ->assertRedirect(route('warmup.index'));
    }

    public function test_solo_tier_rejects_second_outreach_mailbox(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'solo']);

        WarmupMailbox::factory()->outreach()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/warmup', $this->mailboxPayload(['email' => 'second@example.com']))
            ->assertSessionHasErrors('connection');
    }

    public function test_agency_tier_allows_up_to_three_outreach_mailboxes(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'agency']);

        WarmupMailbox::factory()->outreach()->count(2)->create(['user_id' => $user->id]);

        $this->mock(WarmupMailboxService::class, function ($mock) {
            $mock->shouldReceive('connect')->once()->andReturn(WarmupMailbox::factory()->make());
        });

        $this->actingAs($user)
            ->post('/warmup', $this->mailboxPayload(['email' => 'third@example.com']))
            ->assertRedirect(route('warmup.index'));
    }

    public function test_agency_tier_rejects_fourth_outreach_mailbox(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'agency']);

        WarmupMailbox::factory()->outreach()->count(3)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/warmup', $this->mailboxPayload(['email' => 'fourth@example.com']))
            ->assertSessionHasErrors('connection');
    }

    public function test_solo_tier_rejects_fourth_seed_mailbox(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'solo']);

        WarmupMailbox::factory()->count(3)->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        $this->actingAs($user)
            ->post('/warmup', $this->mailboxPayload([
                'email' => 'seed4@example.com',
                'is_outreach_mailbox' => false,
                'is_seed_mailbox' => true,
            ]))
            ->assertSessionHasErrors('connection');
    }

    public function test_solo_tier_resets_send_window_fields_to_defaults(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'solo']);

        $mailbox = WarmupMailbox::factory()->make();

        $this->mock(WarmupMailboxService::class, function ($mock) use ($mailbox) {
            $mock->shouldReceive('connect')->once()->with(\Mockery::on(function (array $data) {
                return $data['send_window_start'] === '08:00:00'
                    && $data['send_window_end'] === '18:00:00'
                    && $data['send_on_weekends'] === true;

            }))->andReturn($mailbox);
        });

        $this->actingAs($user)
            ->post('/warmup', $this->mailboxPayload([
                'send_window_start' => '06:00',
                'send_window_end' => '22:00',
                'send_on_weekends' => false,
            ]))
            ->assertRedirect(route('warmup.index'));
    }

    public function test_test_connection_returns_smtp_authentication_error(): void
    {
        $user = User::factory()->create();

        $this->mock(WarmupMailboxService::class, function ($mock) {
            $mock->shouldReceive('verifyConnection')
                ->once()
                ->andThrow(new \RuntimeException('SMTP authentication failed: Username and Password not accepted'));
        });

        $this->actingAs($user)
            ->postJson('/warmup/test-connection', [
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'username' => 'user@gmail.com',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'SMTP authentication failed: Username and Password not accepted');
    }

    public function test_mailbox_test_connection_uses_stored_credentials(): void
    {
        $user = User::factory()->create();
        $mailbox = WarmupMailbox::factory()->create(['user_id' => $user->id]);

        $this->mock(WarmupMailboxService::class, function ($mock) {
            $mock->shouldReceive('verifyConnection')->once()->andReturn(true);
        });

        $this->actingAs($user)
            ->postJson("/warmup/{$mailbox->id}/test")
            ->assertOk()
            ->assertJsonPath('imap', true)
            ->assertJsonPath('smtp', true);
    }

    public function test_mailbox_test_connection_rejects_other_users_mailbox(): void
    {
        $mailbox = WarmupMailbox::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/warmup/{$mailbox->id}/test")
            ->assertForbidden();
    }

    public function test_update_mailbox_credentials_verifies_before_saving(): void
    {
        $user = User::factory()->create();
        $mailbox = WarmupMailbox::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed',
            'consecutive_failures' => 3,
        ]);

        $this->mock(WarmupMailboxService::class, function ($mock) {
            $mock->shouldReceive('verifyConnection')->once()->andReturn(true);
        });

        $this->actingAs($user)
            ->patch("/warmup/{$mailbox->id}/credentials", ['password' => 'new-app-password'])
            ->assertRedirect();

        $mailbox->refresh();
        $this->assertSame('pending', $mailbox->status);
        $this->assertSame(0, $mailbox->consecutive_failures);
        $this->assertSame('new-app-password', $mailbox->decrypted_password);
    }

    public function test_show_seed_mailbox_includes_connection_details(): void
    {
        $user = User::factory()->create();
        $mailbox = WarmupMailbox::factory()->create([
            'user_id' => $user->id,
            'email' => 'seed@example.com',
            'is_outreach_mailbox' => false,
            'is_seed_mailbox' => true,
            'imap_host' => 'imap.gmail.com',
            'smtp_host' => 'smtp.gmail.com',
        ]);

        $this->actingAs($user)
            ->get("/warmup/{$mailbox->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Warmup/Show')
                ->where('mailbox.email', 'seed@example.com')
                ->where('mailbox.imap_host', 'imap.gmail.com')
                ->where('mailbox.is_seed_mailbox', true)
                ->where('mailbox.is_outreach_mailbox', false)
            );
    }

    public function test_start_warmup_dispatches_process_job_for_outreach_mailbox(): void
    {
        Bus::fake([ProcessWarmupJob::class]);

        $user = User::factory()->create();
        WarmupMailbox::factory()->count(2)->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);
        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'warmup_enabled' => false,
        ]);

        $this->actingAs($user)
            ->post("/warmup/{$mailbox->id}/start")
            ->assertRedirect();

        $mailbox->refresh();
        $this->assertTrue($mailbox->warmup_enabled);
        $this->assertSame('warming', $mailbox->status);
        $this->assertNotNull($mailbox->warmup_started_at);

        Bus::assertDispatched(
            ProcessWarmupJob::class,
            fn (ProcessWarmupJob $job) => $job->outboxId === $mailbox->id,
        );
    }
}
