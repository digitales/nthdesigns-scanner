<?php

namespace App\Http\Resources;

use App\Models\OutreachSelection;

class OutreachSelectionResource
{
    public static function format(OutreachSelection $selection): array
    {
        $prospect = $selection->prospect;
        $report = $prospect->report;

        return [
            'id' => $selection->id,
            'prospect_id' => $selection->prospect_id,
            'business_name' => $prospect->business_name,
            'dominant_angle' => $prospect->dominant_angle,
            'combined_score' => $prospect->combined_score,
            'performance_score' => $prospect->performance_score,
            'report_ready' => $report !== null,
            'report_url' => $report ? url('/r/'.$report->token.'#book') : null,
            'booked_label' => $report?->booking
                ? 'Booked · '.$report->booking->starts_at->format('j M g:ia')
                : null,
        ];
    }
}
