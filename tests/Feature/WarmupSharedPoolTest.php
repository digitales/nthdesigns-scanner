<?php

namespace Tests\Feature;

use App\Jobs\WarmupPoolHealthJob;
use App\Models\User;
use App\Models\WarmupAlert;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupSeedPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarmupSharedPoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['warmup_pool.min_size' => 10]);
    }

    public function test_agency_outbox_uses_pool_seed_when_own_seeds_exhausted(): void
    {
        $userA = User::factory()->create(['subscription_tier' => 'agency']);
        $userB = User::factory()->create(['subscription_tier' => 'agency']);

        $outbox = WarmupMailbox::factory()->outreach()->create([
            'user_id' => $userA->id,
            'email' => 'out@agency-a.com',
        ]);

        WarmupMailbox::factory()->create([
            'user_id' => $userA->id,
            'email' => 'own@gmail.com',
        ]);

        $poolSeed = WarmupMailbox::factory()->create([
            'user_id' => $userB->id,
            'email' => 'pool@gmail.com',
            'is_pool_participant' => true,
        ]);

        WarmupSend::factory()->create([
            'from_mailbox_id' => $outbox->id,
            'to_mailbox_id' => WarmupMailbox::factory()->create(['user_id' => $userA->id])->id,
            'sent_at' => now()->subHours(2),
        ]);

        $groups = app(WarmupSeedPoolService::class)->seedGroupsForOutbox($outbox);

        $this->assertCount(1, $groups['own']);
        $this->assertCount(1, $groups['pool']);
        $this->assertSame($poolSeed->id, $groups['pool']->first()->id);
    }

    public function test_send_history_masks_network_seed_email(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $outbox = WarmupMailbox::factory()->outreach()->create(['user_id' => $userA->id]);
        $poolSeed = WarmupMailbox::factory()->create(['user_id' => $userB->id, 'email' => 'secret@gmail.com']);

        WarmupSend::factory()->create([
            'from_mailbox_id' => $outbox->id,
            'to_mailbox_id' => $poolSeed->id,
        ]);

        $this->actingAs($userA)
            ->get("/warmup/{$outbox->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sends', 1)
                ->where('sends.0.recipient', 'Network seed')
            );
    }

    public function test_bounce_exclusion_removes_seed_from_pool(): void
    {
        $seed = WarmupMailbox::factory()->create([
            'is_pool_participant' => true,
        ]);

        WarmupSend::factory()->count(5)->create([
            'to_mailbox_id' => $seed->id,
            'status' => 'bounced',
            'sent_at' => now()->subDay(),
        ]);

        (new WarmupPoolHealthJob)->handle(app(WarmupSeedPoolService::class));

        $this->assertFalse($seed->fresh()->is_pool_participant);
        $this->assertDatabaseHas('warmup_alerts', [
            'warmup_mailbox_id' => $seed->id,
            'type' => 'pool_excluded',
        ]);
    }

    public function test_white_label_user_can_access_admin_pool_page(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'white_label']);

        $this->actingAs($user)
            ->get('/admin/warmup-pool')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Warmup/Admin/Pool'));
    }

    public function test_agency_user_cannot_access_admin_pool_page(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'agency']);

        $this->actingAs($user)
            ->get('/admin/warmup-pool')
            ->assertForbidden();
    }

    public function test_connect_requires_pool_consent_when_joining_network(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'agency']);

        $this->mock(\App\Services\WarmupMailboxService::class, function ($mock) {
            $mock->shouldReceive('connect')->never();
        });

        $this->actingAs($user)
            ->post('/warmup', [
                'email' => 'seed@gmail.com',
                'provider' => 'gmail',
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'username' => 'seed@gmail.com',
                'password' => 'secret',
                'is_outreach_mailbox' => false,
                'is_seed_mailbox' => true,
                'is_pool_participant' => true,
                'pool_consent_acknowledged' => false,
            ])
            ->assertSessionHasErrors('connection');
    }

    public function test_index_includes_pool_stats_for_agency_user(): void
    {
        WarmupMailbox::factory()->count(10)->create(['is_pool_participant' => true]);

        $user = User::factory()->create(['subscription_tier' => 'agency']);
        WarmupMailbox::factory()->outreach()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/warmup')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pool.active_count', 10)
                ->where('pool.pool_ready', true)
                ->where('can_start_warmup', true)
            );
    }
}
