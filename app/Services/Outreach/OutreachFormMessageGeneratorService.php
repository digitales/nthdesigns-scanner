<?php

namespace App\Services\Outreach;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Services\AgencyBookingService;
use App\Services\OpenRouterService;
use App\Services\OutreachEmailGeneratorService;

class OutreachFormMessageGeneratorService
{
    public function __construct(
        private OpenRouterService $openRouter,
        private AgencyBookingService $agencyBooking,
        private OutreachEmailGeneratorService $emailGenerator,
    ) {}

    /**
     * @return array{email_body: string, model_used: string, prompt_tokens: int, completion_tokens: int, pitch_angle: string}
     */
    public function generate(Prospect $prospect, ?ProspectReport $report = null, array $options = []): array
    {
        $pitchAngle = $this->emailGenerator->resolvedPitchAngle($prospect, $options);
        $reportUrl = $report
            ? url('/r/'.$report->token)
            : null;

        $system = <<<'PROMPT'
You write concise contact-form messages for nthdesigns, a UK agency helping local businesses improve their Google Business Profile and website accessibility.

Rules:
- British English spelling
- No hype or spam phrases
- Under 120 words
- Message-box tone — do not assume a named recipient; you may address the business by name
- One clear call-to-action
- When an audit report link is provided, steer booking to the report's Next step section only — never include separate scheduling links
- Return ONLY valid JSON with key: email_body
PROMPT;

        $user = $this->buildUserPrompt($prospect, $pitchAngle, $reportUrl, $options);

        $result = $this->openRouter->complete($system, $user);
        $parsed = $this->parseResponse($result['content']);

        return [
            'email_body' => $parsed['email_body'],
            'model_used' => $result['model'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'pitch_angle' => $pitchAngle,
        ];
    }

    private function buildUserPrompt(Prospect $prospect, string $pitchAngle, ?string $reportUrl, array $options = []): string
    {
        $search = $prospect->search;
        $flags = array_merge($prospect->gbp_flags ?? [], $prospect->a11y_flags ?? []);

        $lines = [
            'Write a contact-form message for this prospect.',
            "Business: {$prospect->business_name}",
            "Location: {$search->city}, {$search->country}",
            "Niche: {$search->niche}",
            "Pitch angle: {$pitchAngle}",
            "Combined weakness score (higher = more opportunity): {$prospect->combined_score}",
            'Key issues: '.(count($flags) ? implode('; ', $flags) : 'General improvement opportunity'),
        ];

        if (! empty($options['agency_name'])) {
            $lines[] = "Sign off from: {$options['agency_name']}";
        }

        if (isset($options['cpc_benchmark'])) {
            $lines[] = 'GBP CPC benchmark for this niche: £'.$options['cpc_benchmark'].' per click';
        }

        if ($reportUrl) {
            $lines[] = "Include this audit report link naturally: {$reportUrl}";
            $lines[] = $this->emailGenerator->reportBookingInstruction();
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{email_body: string}
     */
    private function parseResponse(string $content): array
    {
        $decoded = json_decode(trim($content), true);

        if (is_array($decoded) && isset($decoded['email_body'])) {
            return ['email_body' => trim((string) $decoded['email_body'])];
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded) && isset($decoded['email_body'])) {
                return ['email_body' => trim((string) $decoded['email_body'])];
            }
        }

        return ['email_body' => trim($content)];
    }
}
