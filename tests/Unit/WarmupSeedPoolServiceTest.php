<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupSeedPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarmupSeedPoolServiceTest extends TestCase
{
    use RefreshDatabase;

    private WarmupSeedPoolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WarmupSeedPoolService::class);
        config(['warmup_pool.min_size' => 10]);
    }

    public function test_returns_own_seeds_before_pool_seeds(): void
    {
        $userA = User::factory()->create(['subscription_tier' => 'agency']);
        $userB = User::factory()->create(['subscription_tier' => 'agency']);

        $outbox = WarmupMailbox::factory()->outreach()->create(['user_id' => $userA->id, 'email' => 'out@agency-a.com']);
        $ownSeed = WarmupMailbox::factory()->create(['user_id' => $userA->id, 'email' => 'own@gmail.com']);
        WarmupMailbox::factory()->create([
            'user_id' => $userB->id,
            'email' => 'pool@gmail.com',
            'is_pool_participant' => true,
        ]);

        $groups = $this->service->seedGroupsForOutbox($outbox);

        $this->assertCount(1, $groups['own']);
        $this->assertSame($ownSeed->id, $groups['own']->first()->id);
        $this->assertCount(1, $groups['pool']);
    }

    public function test_excludes_pool_seeds_on_same_user(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'agency']);

        $outbox = WarmupMailbox::factory()->outreach()->create(['user_id' => $user->id, 'email' => 'out@agency.com']);
        WarmupMailbox::factory()->create([
            'user_id' => $user->id,
            'email' => 'other-seed@gmail.com',
            'is_pool_participant' => true,
        ]);

        $groups = $this->service->seedGroupsForOutbox($outbox);

        $this->assertCount(1, $groups['own']);
        $this->assertCount(0, $groups['pool']);
    }

    public function test_excludes_pool_seeds_on_same_domain_as_outreach(): void
    {
        $userA = User::factory()->create(['subscription_tier' => 'agency']);
        $userB = User::factory()->create(['subscription_tier' => 'agency']);

        $outbox = WarmupMailbox::factory()->outreach()->create(['user_id' => $userA->id, 'email' => 'out@client.com']);
        WarmupMailbox::factory()->create(['user_id' => $userA->id, 'email' => 'seed@gmail.com']);
        WarmupMailbox::factory()->create([
            'user_id' => $userB->id,
            'email' => 'other@client.com',
            'is_pool_participant' => true,
        ]);
        WarmupMailbox::factory()->create([
            'user_id' => $userB->id,
            'email' => 'good@gmail.com',
            'is_pool_participant' => true,
        ]);

        $groups = $this->service->seedGroupsForOutbox($outbox);

        $this->assertCount(1, $groups['pool']);
        $this->assertSame('good@gmail.com', $groups['pool']->first()->email);
    }

    public function test_excludes_recently_used_seeds_when_fresh_alternatives_exist(): void
    {
        $userA = User::factory()->create(['subscription_tier' => 'agency']);
        $userB = User::factory()->create(['subscription_tier' => 'agency']);

        $outbox = WarmupMailbox::factory()->outreach()->create(['user_id' => $userA->id]);
        $usedPoolSeed = WarmupMailbox::factory()->create([
            'user_id' => $userB->id,
            'is_pool_participant' => true,
        ]);
        $freshPoolSeed = WarmupMailbox::factory()->create([
            'user_id' => $userB->id,
            'email' => 'fresh@gmail.com',
            'is_pool_participant' => true,
        ]);

        WarmupSend::factory()->create([
            'from_mailbox_id' => $outbox->id,
            'to_mailbox_id' => $usedPoolSeed->id,
            'sent_at' => now()->subHours(2),
        ]);

        $groups = $this->service->seedGroupsForOutbox($outbox);

        $this->assertCount(1, $groups['pool']);
        $this->assertSame($freshPoolSeed->id, $groups['pool']->first()->id);
    }

    public function test_falls_back_to_recently_used_seeds_when_all_were_used_in_last_24_hours(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'solo']);

        $outbox = WarmupMailbox::factory()->outreach()->create(['user_id' => $user->id]);
        $seeds = WarmupMailbox::factory()->count(3)->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        foreach ($seeds as $seed) {
            WarmupSend::factory()->create([
                'from_mailbox_id' => $outbox->id,
                'to_mailbox_id' => $seed->id,
                'sent_at' => now()->subHours(12),
            ]);
        }

        $groups = $this->service->seedGroupsForOutbox($outbox);

        $this->assertCount(3, $groups['own']);
        $this->assertCount(0, $groups['pool']);
    }

    public function test_solo_user_never_receives_pool_seeds(): void
    {
        $solo = User::factory()->create(['subscription_tier' => 'solo']);
        $agency = User::factory()->create(['subscription_tier' => 'agency']);

        $outbox = WarmupMailbox::factory()->outreach()->create(['user_id' => $solo->id]);
        WarmupMailbox::factory()->create([
            'user_id' => $agency->id,
            'is_pool_participant' => true,
        ]);

        $groups = $this->service->seedGroupsForOutbox($outbox);

        $this->assertCount(0, $groups['pool']);
    }

    public function test_can_start_warmup_for_agency_with_pool_ready_and_no_own_seeds(): void
    {
        WarmupMailbox::factory()->count(10)->create(['is_pool_participant' => true]);

        $user = User::factory()->create(['subscription_tier' => 'agency']);
        WarmupMailbox::factory()->outreach()->create(['user_id' => $user->id]);

        $this->assertTrue($this->service->canStartWarmup($user));
    }

    public function test_cannot_start_warmup_for_agency_when_pool_below_min_size_and_few_seeds(): void
    {
        WarmupMailbox::factory()->count(5)->create(['is_pool_participant' => true]);

        $user = User::factory()->create(['subscription_tier' => 'agency']);
        WarmupMailbox::factory()->outreach()->create(['user_id' => $user->id]);

        $this->assertFalse($this->service->canStartWarmup($user));
    }

    public function test_count_active_pool_seeds(): void
    {
        WarmupMailbox::factory()->count(3)->create(['is_pool_participant' => true, 'status' => 'pending']);
        WarmupMailbox::factory()->create(['is_pool_participant' => true, 'status' => 'failed']);
        WarmupMailbox::factory()->create(['is_pool_participant' => false]);

        $this->assertSame(3, $this->service->countActivePoolSeeds());
    }
}
