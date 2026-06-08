<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Support\TidyCalEmbed;

class OutreachEmailGeneratorService
{
    public function __construct(
        private OpenRouterService $openRouter,
        private AgencyBookingService $agencyBooking,
    ) {}

    public function resolvedPitchAngle(Prospect $prospect, array $options = []): string
    {
        $pitchAngle = $options['pitch_angle'] ?? 'auto';

        if ($pitchAngle === 'auto') {
            return $this->resolvePitchAngle($prospect);
        }

        return $pitchAngle;
    }

    /**
     * @return array{subject_line: string, email_body: string, model_used: string, prompt_tokens: int, completion_tokens: int, pitch_angle: string}
     */
    public function generate(Prospect $prospect, ?ProspectReport $report = null, array $options = []): array
    {
        $pitchAngle = $this->resolvedPitchAngle($prospect, $options);
        $reportUrl = $report
            ? url('/r/'.$report->token)
            : null;

        $system = <<<'PROMPT'
You write concise, professional cold outreach emails for nthdesigns, a UK agency helping local businesses improve their Google Business Profile and website accessibility.

Rules:
- British English spelling
- No hype or spam phrases
- Under 180 words for the body
- One clear call-to-action
- Return ONLY valid JSON with keys: subject_line, email_body
PROMPT;

        $user = $this->buildUserPrompt($prospect, $pitchAngle, $reportUrl, $options);

        $result = $this->openRouter->complete($system, $user);
        $parsed = $this->parseResponse($result['content']);

        return [
            'subject_line' => $parsed['subject_line'],
            'email_body' => $parsed['email_body'],
            'model_used' => $result['model'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'pitch_angle' => $pitchAngle,
        ];
    }

    private function resolvePitchAngle(Prospect $prospect): string
    {
        return match ($prospect->dominant_angle) {
            'accessibility' => 'accessibility',
            'both' => 'combined',
            default => 'gbp',
        };
    }

    private function buildUserPrompt(Prospect $prospect, string $pitchAngle, ?string $reportUrl, array $options = []): string
    {
        $search = $prospect->search;
        $flags = array_merge($prospect->gbp_flags ?? [], $prospect->a11y_flags ?? []);

        $lines = [
            'Write an outreach email for this prospect.',
            "Business: {$prospect->business_name}",
            "Location: {$search->city}, {$search->country}",
            "Niche: {$search->niche}",
            "Pitch angle: {$pitchAngle}",
            "Combined weakness score (higher = more opportunity): {$prospect->combined_score}",
            'Key issues: '.(count($flags) ? implode('; ', $flags) : 'General improvement opportunity'),
        ];

        if (! empty($options['agency_name'])) {
            $lines[] = "Sign the email from: {$options['agency_name']}";
        }

        if (isset($options['cpc_benchmark'])) {
            $lines[] = 'GBP CPC benchmark for this niche: £'.$options['cpc_benchmark'].' per click';
        }

        if ($reportUrl) {
            $lines[] = "Include this audit report link naturally: {$reportUrl}";

            if ($this->agencyBooking->nativeBookingActive()) {
                $lines[] = 'The report includes inline booking — encourage them to pick a time on the report (Next step section).';
            }
        }

        if (! $this->agencyBooking->nativeBookingActive()) {
            $bookingUrl = config('scanner.report_booking_url');

            if ($bookingUrl) {
                $lines[] = 'Booking link for a call: '.(TidyCalEmbed::bookPageUrl($bookingUrl) ?? $bookingUrl);
            }
        }

        if ($instruction = $this->performancePromptInstruction($prospect)) {
            $lines[] = $instruction;
        }

        return implode("\n", $lines);
    }

    public function performancePromptInstruction(Prospect $prospect): ?string
    {
        $score = (int) $prospect->performance_score;

        if ($score <= 0 || $score >= 30) {
            return null;
        }

        return "Add exactly one secondary sentence (not the opening) noting their site scored {$score}/100 on Google's performance benchmark and that slow load times affect rankings and bounce rate.";
    }

    /**
     * @return array{subject_line: string, email_body: string}
     */
    private function parseResponse(string $content): array
    {
        $json = json_decode($content, true);

        if (is_array($json) && isset($json['subject_line'], $json['email_body'])) {
            return [
                'subject_line' => $json['subject_line'],
                'email_body' => $json['email_body'],
            ];
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = json_decode($matches[0], true);

            if (is_array($json) && isset($json['subject_line'], $json['email_body'])) {
                return [
                    'subject_line' => $json['subject_line'],
                    'email_body' => $json['email_body'],
                ];
            }
        }

        return [
            'subject_line' => 'Quick question about your online presence',
            'email_body' => trim($content),
        ];
    }
}
