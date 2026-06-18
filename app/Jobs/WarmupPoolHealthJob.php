<?php

namespace App\Jobs;

use App\Models\WarmupAlert;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupSeedPoolService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarmupPoolHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('warmup');
    }

    public function handle(WarmupSeedPoolService $poolService): void
    {
        $lookbackDays = config('warmup_pool.lookback_days');
        $since = now()->subDays($lookbackDays);
        $bounceThreshold = config('warmup_pool.bounce_count_threshold');
        $rateThreshold = config('warmup_pool.bounce_rate_threshold');
        $rateMinSends = config('warmup_pool.bounce_rate_min_sends');

        WarmupMailbox::query()
            ->where('is_seed_mailbox', true)
            ->where('is_pool_participant', true)
            ->where('status', '!=', 'failed')
            ->each(function (WarmupMailbox $seed) use ($since, $bounceThreshold, $rateThreshold, $rateMinSends) {
                $totalReceived = WarmupSend::query()
                    ->where('to_mailbox_id', $seed->id)
                    ->where('sent_at', '>=', $since)
                    ->count();

                $bounceCount = WarmupSend::query()
                    ->where('to_mailbox_id', $seed->id)
                    ->where('sent_at', '>=', $since)
                    ->where('status', 'bounced')
                    ->count();

                $rateExceeded = $totalReceived >= $rateMinSends
                    && ($bounceCount / $totalReceived) >= $rateThreshold;

                if ($bounceCount < $bounceThreshold && ! $rateExceeded) {
                    return;
                }

                $seed->update(['is_pool_participant' => false]);

                WarmupAlert::create([
                    'warmup_mailbox_id' => $seed->id,
                    'type' => 'pool_excluded',
                    'message' => 'Removed from shared network due to high bounce rate. Check your mailbox is active and receiving mail.',
                    'created_at' => now(),
                ]);
            });

        $activeCount = $poolService->countActivePoolSeeds();

        if ($activeCount < config('warmup_pool.alert_size')) {
            Log::warning('Warmup seed pool below alert threshold.', [
                'active_count' => $activeCount,
                'alert_size' => config('warmup_pool.alert_size'),
            ]);
        }
    }
}
