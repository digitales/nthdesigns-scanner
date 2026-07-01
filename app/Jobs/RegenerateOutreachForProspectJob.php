<?php

namespace App\Jobs;

use App\Enums\OutreachChannel;
use App\Models\Prospect;
use App\Models\User;
use App\Services\Outreach\OutreachChannelResolver;
use App\Services\ProspectUnsubscribeService;
use App\Support\ScannerJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Tries(2)]
#[Timeout(60)]
class RegenerateOutreachForProspectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        #[WithoutRelations]
        public Prospect $prospect,
        #[WithoutRelations]
        public User $user,
        public array $options = [],
    ) {}

    public function handle(
        OutreachChannelResolver $channels,
        ProspectUnsubscribeService $unsubscribe,
    ): void {
        ScannerJobContext::add(self::class, [
            'prospect_id' => $this->prospect->id,
            'user_id' => $this->user->id,
        ]);

        $prospect = $this->prospect->fresh(['search', 'report']);

        if (! $prospect?->report) {
            return;
        }

        $generationOptions = array_merge($this->options, ['force' => true]);

        foreach ($channels->channelsFor($prospect) as $channel) {
            if ($channel === OutreachChannel::Email
                && $unsubscribe->outreachSkipReason($this->user, $prospect) !== null) {
                continue;
            }

            GenerateOutreachEmailJob::dispatch(
                $prospect,
                $this->user,
                $generationOptions,
                $channel,
            );
        }
    }
}
