<?php

namespace App\Jobs;

use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupMailboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessWarmupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?int $outboxId = null)
    {
        $this->onQueue('warmup');
    }

    public function handle(WarmupMailboxService $mailboxService): void
    {
        $outboxes = WarmupMailbox::query()
            ->active()
            ->where('is_outreach_mailbox', true)
            ->when($this->outboxId, fn ($query) => $query->whereKey($this->outboxId))
            ->get();

        foreach ($outboxes as $outbox) {
            if (! $outbox->send_on_weekends && now()->isWeekend()) {
                continue;
            }

            $alreadySentToday = WarmupSend::query()
                ->where('from_mailbox_id', $outbox->id)
                ->whereDate('sent_at', today())
                ->exists();

            if ($alreadySentToday) {
                continue;
            }

            $volume = $mailboxService->getDailyVolume($outbox);
            $seedGroups = $mailboxService->getSeedGroups($outbox);

            if ($seedGroups['own']->isEmpty() && $seedGroups['pool']->isEmpty()) {
                continue;
            }

            $sendTimes = $this->buildSendSchedule($outbox, $volume);
            $seedCycle = $this->buildSeedCycle($seedGroups['own'], $seedGroups['pool'], $volume);

            foreach ($sendTimes as $index => $sendAt) {
                $seed = $seedCycle[$index];

                SendWarmupEmailJob::dispatch($outbox->id, $seed->id)
                    ->delay($sendAt);
            }
        }
    }

    /**
     * @return array<int, Carbon>
     */
    private function buildSendSchedule(WarmupMailbox $outbox, int $volume): array
    {
        if ($volume === 0) {
            return [];
        }

        $windowStart = $this->windowTimeToday($outbox->send_window_start);
        $windowEnd = $this->windowTimeToday($outbox->send_window_end);
        $now = now();

        if ($now->greaterThanOrEqualTo($windowEnd)) {
            return [];
        }

        $effectiveStart = $now->greaterThan($windowStart) ? $now->copy() : $windowStart;
        $windowMinutes = max(1, $effectiveStart->diffInMinutes($windowEnd));

        $baseGap = $windowMinutes / $volume;
        if ($baseGap < 1) {
            Log::warning('Warmup send volume exceeds send window; compressing schedule.', [
                'mailbox_id' => $outbox->id,
                'volume' => $volume,
                'window_minutes' => $windowMinutes,
            ]);
            $baseGap = 1;
        }

        $schedule = [];

        for ($i = 0; $i < $volume; $i++) {
            $slotStart = $effectiveStart->copy()->addMinutes((int) round($i * $baseGap));
            $slotEnd = $effectiveStart->copy()->addMinutes((int) round(($i + 1) * $baseGap));
            $slotEnd = $slotEnd->greaterThan($windowEnd) ? $windowEnd->copy() : $slotEnd;

            $jitterMinutes = $slotEnd->greaterThan($slotStart)
                ? random_int(0, max(0, (int) $slotStart->diffInMinutes($slotEnd)))
                : 0;

            $sendAt = $slotStart->copy()->addMinutes($jitterMinutes);

            if ($sendAt->greaterThan($windowEnd)) {
                $sendAt = $windowEnd->copy();
            }

            $schedule[] = $sendAt;
        }

        return $schedule;
    }

    /**
     * @return array<int, WarmupMailbox>
     */
    private function buildSeedCycle(Collection $ownSeeds, Collection $poolSeeds, int $volume): array
    {
        $own = $ownSeeds->shuffle()->values();
        $pool = $poolSeeds->shuffle()->values();
        $cycle = [];
        $ownPass = 0;

        while (count($cycle) < $volume) {
            if ($ownPass < $own->count()) {
                $cycle[] = $own[$ownPass];
                $ownPass++;

                continue;
            }

            if ($pool->isNotEmpty()) {
                $poolOffset = count($cycle) - $own->count();
                $cycle[] = $pool[$poolOffset % $pool->count()];

                continue;
            }

            if ($own->isNotEmpty()) {
                $cycle[] = $own[count($cycle) % $own->count()];

                continue;
            }

            break;
        }

        return $cycle;
    }

    private function windowTimeToday(mixed $time): Carbon
    {
        $value = $time instanceof Carbon ? $time->format('H:i:s') : (string) $time;

        return Carbon::parse(today()->toDateString().' '.$value);
    }
}
