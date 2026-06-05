<?php

namespace App\Http\Resources;

use App\Models\ProspectReport;

class ReportDashboardResource
{
    /**
     * @return array<string, mixed>
     */
    public static function format(ProspectReport $report): array
    {
        $latest = $report->prospect->outreachEmails->first();
        $data = $report->report_data ?? [];

        return [
            'id' => $report->id,
            'prospect_id' => $report->prospect_id,
            'business_name' => $data['prospect']['business_name'] ?? $report->prospect->business_name,
            'niche' => $data['niche'] ?? $report->prospect->search->niche,
            'city' => $data['city'] ?? $report->prospect->search->city,
            'token' => $report->token,
            'public_url' => url('/r/'.$report->token),
            'view_count' => $report->view_count,
            'viewed_at' => $report->viewed_at?->toISOString(),
            'viewer_ip' => $report->viewer_ip,
            'created_at' => $report->created_at->diffForHumans(),
            'is_engaged_badge' => $report->viewed_at?->gte(now()->subDays(7)) ?? false,
            'has_outreach_sent' => $latest?->sent_at !== null,
            'response_received' => (bool) ($latest?->response_received ?? false),
        ];
    }
}
