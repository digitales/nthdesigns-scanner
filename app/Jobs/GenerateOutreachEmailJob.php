<?php

namespace App\Jobs;

use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\User;
use App\Services\Outreach\CpcBenchmarkResolver;
use App\Services\OutreachEmailGeneratorService;
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
use Illuminate\Support\Facades\Log;

#[Tries(2)]
#[Timeout(90)]
class GenerateOutreachEmailJob implements ShouldQueue
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
        OutreachEmailGeneratorService $generator,
        CpcBenchmarkResolver $cpcBenchmarks,
        ProspectUnsubscribeService $unsubscribe,
    ): void {
        ScannerJobContext::add(self::class, [
            'prospect_id' => $this->prospect->id,
            'user_id' => $this->user->id,
        ]);

        $prospect = $this->prospect->fresh(['search', 'report']);

        if (! $prospect) {
            return;
        }

        if ($unsubscribe->outreachSkipReason($this->user, $prospect) !== null) {
            return;
        }

        $pitchAngle = $generator->resolvedPitchAngle($prospect, $this->options);

        if (OutreachEmail::query()
            ->where('prospect_id', $prospect->id)
            ->where('user_id', $this->user->id)
            ->where('pitch_angle', $pitchAngle)
            ->exists()) {
            return;
        }

        $cpcMeta = $cpcBenchmarks->resolveForProspect($prospect, $this->options);
        $generationOptions = array_merge($this->options, $cpcMeta);

        try {
            $generated = $generator->generate($prospect, $prospect->report, $generationOptions);

            $emailBody = $unsubscribe->appendUnsubscribeFooter(
                $generated['email_body'],
                $this->user,
                $prospect,
                $prospect->email,
            );

            OutreachEmail::create([
                'prospect_id' => $prospect->id,
                'user_id' => $this->user->id,
                'prospect_report_id' => $prospect->report?->id,
                'pitch_angle' => $generated['pitch_angle'],
                'cpc_benchmark' => $cpcMeta['cpc_benchmark'] ?? null,
                'cpc_source' => $cpcMeta['cpc_source'] ?? null,
                'subject_line' => $generated['subject_line'],
                'email_body' => $emailBody,
                'model_used' => $generated['model_used'],
                'prompt_tokens' => $generated['prompt_tokens'],
                'completion_tokens' => $generated['completion_tokens'],
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateOutreachEmailJob failed', [
                'prospect_id' => $prospect->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
