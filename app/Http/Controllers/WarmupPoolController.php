<?php

namespace App\Http\Controllers;

use App\Models\WarmupAlert;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupSeedPoolService;
use Inertia\Inertia;
use Inertia\Response;

class WarmupPoolController extends Controller
{
    public function index(WarmupSeedPoolService $poolService): Response
    {
        abort_unless(auth()->user()->warmupTier() === 'white_label', 403);

        $lookbackDays = config('warmup_pool.lookback_days');
        $since = now()->subDays($lookbackDays);

        $recentExclusions = WarmupAlert::query()
            ->where('type', 'pool_excluded')
            ->where('created_at', '>=', $since)
            ->with('mailbox:id,email,user_id')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (WarmupAlert $alert) => [
                'email' => $alert->mailbox?->email,
                'excluded_at' => $alert->created_at?->toIso8601String(),
                'message' => $alert->message,
            ]);

        $topBounceSeeds = WarmupMailbox::query()
            ->where('is_seed_mailbox', true)
            ->get(['id', 'email'])
            ->map(function (WarmupMailbox $seed) use ($since) {
                $total = WarmupSend::query()
                    ->where('to_mailbox_id', $seed->id)
                    ->where('sent_at', '>=', $since)
                    ->count();

                $bounces = WarmupSend::query()
                    ->where('to_mailbox_id', $seed->id)
                    ->where('sent_at', '>=', $since)
                    ->where('status', 'bounced')
                    ->count();

                return [
                    'email' => $seed->email,
                    'total_received' => $total,
                    'bounces' => $bounces,
                    'bounce_rate' => $total > 0 ? round($bounces / $total, 2) : 0,
                ];
            })
            ->filter(fn (array $row) => $row['bounces'] > 0)
            ->sortByDesc('bounce_rate')
            ->take(10)
            ->values();

        return Inertia::render('Warmup/Admin/Pool', [
            'pool' => [
                'active_count' => $poolService->countActivePoolSeeds(),
                'min_size' => config('warmup_pool.min_size'),
                'alert_size' => config('warmup_pool.alert_size'),
                'pool_ready' => $poolService->poolReady(),
            ],
            'stats' => [
                'sends_24h' => WarmupSend::query()
                    ->whereHas('toMailbox', fn ($q) => $q->where('is_pool_participant', true))
                    ->where('sent_at', '>=', now()->subDay())
                    ->count(),
                'sends_7d' => WarmupSend::query()
                    ->whereHas('toMailbox', fn ($q) => $q->where('is_pool_participant', true))
                    ->where('sent_at', '>=', now()->subDays(7))
                    ->count(),
            ],
            'recent_exclusions' => $recentExclusions,
            'top_bounce_seeds' => $topBounceSeeds,
        ]);
    }
}
