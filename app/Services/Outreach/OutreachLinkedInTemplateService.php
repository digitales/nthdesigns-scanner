<?php

namespace App\Services\Outreach;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Services\OutreachEmailGeneratorService;

class OutreachLinkedInTemplateService
{
    public function __construct(
        private OutreachEmailGeneratorService $emailGenerator,
    ) {}

    /**
     * @return array{email_body: string, pitch_angle: string}
     */
    public function render(Prospect $prospect, ?ProspectReport $report, array $options = []): array
    {
        $pitchAngle = $this->emailGenerator->resolvedPitchAngle($prospect, $options);
        $agencyName = $options['agency_name'] ?? 'nthdesigns';
        $reportUrl = $report ? url('/r/'.$report->token) : null;
        $angleContext = $this->angleContext($pitchAngle);

        $lines = [
            "Hi — I put together a quick audit of {$prospect->business_name}'s online presence ({$angleContext}).",
        ];

        if ($reportUrl) {
            $lines[] = "Worth a look if you're reviewing your digital setup: {$reportUrl}";
        } else {
            $lines[] = 'Worth a look if you are reviewing your digital setup.';
        }

        $lines[] = '';
        $lines[] = "— {$agencyName}";

        $body = implode("\n", $lines);

        if (strlen($body) > 300) {
            $body = substr($body, 0, 297).'...';
        }

        return [
            'email_body' => $body,
            'pitch_angle' => $pitchAngle,
        ];
    }

    private function angleContext(string $pitchAngle): string
    {
        return match ($pitchAngle) {
            'accessibility' => 'website accessibility',
            'combined' => 'GBP visibility and website accessibility',
            default => 'Google Business Profile visibility',
        };
    }
}
