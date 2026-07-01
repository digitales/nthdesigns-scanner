<?php

namespace App\Http\Resources;

use App\Models\OutreachSelection;
use App\Services\Outreach\OutreachQueueLoader;

class OutreachSelectionResource
{
    public static function format(OutreachSelection $selection, ?OutreachQueueLoader $loader = null): array
    {
        $loader ??= app(OutreachQueueLoader::class);

        $prospect = $selection->prospect;
        $report = $prospect->report;
        $userEmails = $prospect->relationLoaded('outreachEmails')
            ? $prospect->outreachEmails
            : collect();
        $outreachStatus = $loader->outreachStatus($userEmails);
        $generatedAt = $loader->reportGeneratedAt($report);

        return [
            'id' => $selection->id,
            'prospect_id' => $selection->prospect_id,
            'business_name' => $prospect->business_name,
            'niche' => $prospect->search->niche,
            'city' => $prospect->search->city,
            'dominant_angle' => $prospect->dominant_angle,
            'combined_score' => $prospect->combined_score,
            'performance_score' => $prospect->performance_score,
            'report_ready' => $report !== null,
            'report_url' => $report ? url('/r/'.$report->token.'#book') : null,
            'report_generated_at' => $generatedAt?->toISOString(),
            'report_age_label' => $generatedAt?->format('j M'),
            'report_stale' => $loader->reportIsStale($generatedAt),
            'outreach_status' => $outreachStatus,
            'outreach_status_label' => $loader->outreachStatusLabel($outreachStatus),
            'refresh_eligible' => $loader->refreshEligible($report, $outreachStatus),
            'booked' => $report?->booking !== null,
            'booked_label' => $report?->booking
                ? 'Booked · '.$report->booking->starts_at->format('j M g:ia').' · '.$report->booking->attendee_name
                : null,
            'booked_note' => $report?->booking?->note,
            'booked_confirmation_sent' => $report?->booking?->confirmation_sent_at !== null,
        ];
    }
}
