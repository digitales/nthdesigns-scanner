<?php

namespace App\Jobs;

use App\Enums\OutreachChannel;
use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\User;
use App\Services\Outreach\CpcBenchmarkResolver;
use App\Services\Outreach\OutreachFormMessageGeneratorService;
use App\Services\Outreach\OutreachLinkedInTemplateService;
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
        public OutreachChannel $channel = OutreachChannel::Email,
    ) {}

    public function handle(
        OutreachEmailGeneratorService $generator,
        OutreachFormMessageGeneratorService $formGenerator,
        OutreachLinkedInTemplateService $linkedInTemplate,
        CpcBenchmarkResolver $cpcBenchmarks,
        ProspectUnsubscribeService $unsubscribe,
    ): void {
        ScannerJobContext::add(self::class, [
            'prospect_id' => $this->prospect->id,
            'user_id' => $this->user->id,
            'channel' => $this->channel->value,
        ]);

        $prospect = $this->prospect->fresh(['search', 'report']);

        if (! $prospect) {
            return;
        }

        if ($this->channel === OutreachChannel::Email) {
            if ($unsubscribe->outreachSkipReason($this->user, $prospect) !== null) {
                return;
            }
        }

        $pitchAngle = $generator->resolvedPitchAngle($prospect, $this->options);

        if (! ($this->options['force'] ?? false)
            && OutreachEmail::query()
                ->where('prospect_id', $prospect->id)
                ->where('user_id', $this->user->id)
                ->where('pitch_angle', $pitchAngle)
                ->where('channel', $this->channel->value)
                ->exists()) {
            return;
        }

        $cpcMeta = $cpcBenchmarks->resolveForProspect($prospect, $this->options);
        $generationOptions = array_merge($this->options, $cpcMeta);

        try {
            $generated = match ($this->channel) {
                OutreachChannel::ContactForm => $this->generateFormMessage($formGenerator, $prospect, $generationOptions),
                OutreachChannel::Linkedin => $this->generateLinkedInMessage($linkedInTemplate, $prospect, $generationOptions, $pitchAngle),
                default => $this->generateEmailMessage($generator, $unsubscribe, $prospect, $generationOptions),
            };

            OutreachEmail::create([
                'prospect_id' => $prospect->id,
                'user_id' => $this->user->id,
                'prospect_report_id' => $prospect->report?->id,
                'pitch_angle' => $generated['pitch_angle'],
                'channel' => $this->channel->value,
                'cpc_benchmark' => $cpcMeta['cpc_benchmark'] ?? null,
                'cpc_source' => $cpcMeta['cpc_source'] ?? null,
                'subject_line' => $generated['subject_line'] ?? null,
                'email_body' => $generated['email_body'],
                'model_used' => $generated['model_used'] ?? null,
                'prompt_tokens' => $generated['prompt_tokens'] ?? 0,
                'completion_tokens' => $generated['completion_tokens'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateOutreachEmailJob failed', [
                'prospect_id' => $prospect->id,
                'channel' => $this->channel->value,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generateEmailMessage(
        OutreachEmailGeneratorService $generator,
        ProspectUnsubscribeService $unsubscribe,
        Prospect $prospect,
        array $generationOptions,
    ): array {
        $generated = $generator->generate($prospect, $prospect->report, $generationOptions);

        return [
            'pitch_angle' => $generated['pitch_angle'],
            'subject_line' => $generated['subject_line'],
            'email_body' => $unsubscribe->appendUnsubscribeFooter(
                $generated['email_body'],
                $this->user,
                $prospect,
                $prospect->email,
            ),
            'model_used' => $generated['model_used'],
            'prompt_tokens' => $generated['prompt_tokens'],
            'completion_tokens' => $generated['completion_tokens'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateFormMessage(
        OutreachFormMessageGeneratorService $formGenerator,
        Prospect $prospect,
        array $generationOptions,
    ): array {
        $generated = $formGenerator->generate($prospect, $prospect->report, $generationOptions);

        return [
            'pitch_angle' => $generated['pitch_angle'],
            'subject_line' => null,
            'email_body' => $generated['email_body'],
            'model_used' => $generated['model_used'],
            'prompt_tokens' => $generated['prompt_tokens'],
            'completion_tokens' => $generated['completion_tokens'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateLinkedInMessage(
        OutreachLinkedInTemplateService $linkedInTemplate,
        Prospect $prospect,
        array $generationOptions,
        string $pitchAngle,
    ): array {
        $generated = $linkedInTemplate->render($prospect, $prospect->report, $generationOptions);

        return [
            'pitch_angle' => $generated['pitch_angle'] ?? $pitchAngle,
            'subject_line' => null,
            'email_body' => $generated['email_body'],
            'model_used' => 'template',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
        ];
    }
}
