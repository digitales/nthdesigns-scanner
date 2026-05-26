<?php

namespace App\Http\Resources;

use App\Models\Prospect;

class ProspectListResource
{
    public static function format(Prospect $prospect): array
    {
        $latest = $prospect->outreachEmails->first();

        return [
            'id'                  => $prospect->id,
            'business_name'       => $prospect->business_name,
            'niche'               => $prospect->search->niche,
            'city'                => $prospect->search->city,
            'country'             => $prospect->search->country,
            'phone'               => $prospect->phone,
            'website_url'         => $prospect->website_url,
            'combined_score'      => $prospect->combined_score,
            'gbp_score'           => $prospect->gbp_score,
            'a11y_score'          => $prospect->a11y_score,
            'dominant_angle'      => $prospect->dominant_angle,
            'gbp_flags'           => $prospect->gbp_flags ?? [],
            'a11y_flags'          => $prospect->a11y_flags ?? [],
            'report_url'          => $prospect->report ? url('/r/'.$prospect->report->token) : null,
            'report_ready'        => $prospect->report !== null,
            'outreach_sent'       => $latest?->sent_at?->toISOString(),
            'response_received'   => (bool) ($latest?->response_received ?? false),
        ];
    }
}
