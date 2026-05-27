<?php

namespace App\Jobs;

use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\User;
use App\Services\OutreachEmailGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateOutreachEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(
        public Prospect $prospect,
        public User $user,
        public array $options = [],
    ) {
        $this->onQueue('auditing');
    }

    public function handle(OutreachEmailGeneratorService $generator): void
    {
        $prospect = $this->prospect->fresh(['search', 'report']);

        if (!$prospect) {
            return;
        }

        try {
            $generated = $generator->generate($prospect, $prospect->report, $this->options);

            OutreachEmail::create([
                'prospect_id'         => $prospect->id,
                'user_id'             => $this->user->id,
                'prospect_report_id'  => $prospect->report?->id,
                'pitch_angle'         => $generated['pitch_angle'],
                'subject_line'        => $generated['subject_line'],
                'email_body'          => $generated['email_body'],
                'model_used'          => $generated['model_used'],
                'prompt_tokens'       => $generated['prompt_tokens'],
                'completion_tokens'   => $generated['completion_tokens'],
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateOutreachEmailJob failed', [
                'prospect_id' => $prospect->id,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

}
