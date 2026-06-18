<?php

namespace App\Jobs;

use App\Models\WarmupMailbox;
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

    public function __construct()
    {
        $this->onQueue('warmup');
    }

    public function handle(WarmupMailboxService $mailboxService): void
    {
        $outboxes = WarmupMailbox::query()
            ->active()
            ->where('is_outreach_mailbox', true)
            ->get();

        foreach ($outboxes as $outbox) {
            if (! $outbox->send_on_weekends && now()->isWeekend()) {
                continue;
            }

            $volume = $mailboxService->getDailyVolume($outbox);
            $seeds = $mailboxService->getSeedPool($outbox);

            if ($seeds->isEmpty()) {
                continue;
            }

            $sendTimes = $this->buildSendSchedule($outbox, $volume);
            $seedCycle = $this->buildSeedCycle($seeds, $volume);

            foreach ($sendTimes as $index => $sendAt) {
                $seed = $seedCycle[$index];

                SendWarmupEmailJob::dispatch($outbox->id, $seed->id)
                    ->delay($sendAt);
            }

            $lastSendAt = end($sendTimes) ?: now();

            ProcessWarmupInboxJob::dispatch($outbox->id)
                ->delay($lastSendAt->copy()->addMinutes(120));
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
        $windowMinutes = max(1, $windowStart->diffInMinutes($windowEnd));

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
        $cursor = $windowStart->copy();

        for ($i = 0; $i < $volume; $i++) {
            $slotStart = $windowStart->copy()->addMinutes((int) round($i * $baseGap));
            $slotEnd = $windowStart->copy()->addMinutes((int) round(($i + 1) * $baseGap));
            $slotEnd = $slotEnd->greaterThan($windowEnd) ? $windowEnd->copy() : $slotEnd;

            $jitterMinutes = $slotEnd->greaterThan($slotStart)
                ? random_int(0, max(0, (int) $slotStart->diffInMinutes($slotEnd)))
                : 0;

            $sendAt = $slotStart->copy()->addMinutes($jitterMinutes);

            if ($sendAt->greaterThan($windowEnd)) {
                $sendAt = $windowEnd->copy();
            }

            $schedule[] = $sendAt;
            $cursor = $sendAt;
        }

        return $schedule;
    }

    /**
     * @return array<int, WarmupMailbox>
     */
    private function buildSeedCycle(Collection $seeds, int $volume): array
    {
        $cycle = [];
        $pool = $seeds->values();

        while (count($cycle) < $volume) {
            $shuffled = $pool->shuffle()->values();
            foreach ($shuffled as $seed) {
                $cycle[] = $seed;
                if (count($cycle) >= $volume) {
                    break;
                }
            }
        }

        return $cycle;
    }

    private function windowTimeToday(mixed $time): Carbon
    {
        $value = $time instanceof Carbon ? $time->format('H:i:s') : (string) $time;

        return Carbon::parse(today()->toDateString().' '.$value);
    }
}
