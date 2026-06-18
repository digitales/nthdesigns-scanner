<?php

namespace Tests\Unit;

use App\Jobs\ProcessWarmupJob;
use App\Jobs\SendWarmupEmailJob;
use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\WarmupMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessWarmupJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_send_jobs_for_daily_volume(): void
    {
        Bus::fake([SendWarmupEmailJob::class]);

        $user = User::factory()->create();

        WarmupMailbox::factory()->outreach()->warming()->create([
            'user_id' => $user->id,
            'warmup_enabled' => true,
            'warmup_started_at' => now()->subDays(14),
            'warmup_target_volume' => 10,
            'warmup_ramp_days' => 14,
            'status' => 'warming',
        ]);

        WarmupMailbox::factory()->count(3)->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        (new ProcessWarmupJob)->handle(app(WarmupMailboxService::class));

        Bus::assertDispatched(SendWarmupEmailJob::class, 10);
    }
}
