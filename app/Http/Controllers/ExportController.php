<?php

namespace App\Http\Controllers;

use App\Models\Export;
use App\Queries\ProspectListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function store(Request $request): StreamedResponse|RedirectResponse
    {
        $this->authorize('create', Export::class);

        $filters = $request->only([
            'from', 'to', 'niche', 'city', 'scan_type', 'min_score', 'dominant_angle', 'warm',
        ]);

        $prospects = (new ProspectListQuery($request->user()))
            ->apply($filters)
            ->query()
            ->with(['search', 'report', 'outreachEmails' => fn ($q) => $q->latest()->limit(1)])
            ->get();

        if ($prospects->isEmpty()) {
            return back()->withErrors(['export' => 'No prospects match filters.']);
        }

        $filename = 'prospects-'.now()->format('Y-m-d-His').'.csv';

        Export::create([
            'user_id' => $request->user()->id,
            'search_id' => null,
            'filename' => $filename,
            'row_count' => $prospects->count(),
        ]);

        return response()->streamDownload(function () use ($prospects) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'business_name', 'niche', 'city', 'country', 'phone', 'website_url',
                'combined_score', 'gbp_score', 'a11y_score', 'dominant_angle',
                'gbp_flags', 'a11y_flags', 'report_url',
                'outreach_subject', 'outreach_sent_at', 'response_received',
            ]);

            foreach ($prospects as $prospect) {
                $email = $prospect->outreachEmails->first();
                fputcsv($out, [
                    $prospect->business_name,
                    $prospect->search->niche,
                    $prospect->search->city,
                    $prospect->search->country,
                    $prospect->phone,
                    $prospect->website_url,
                    $prospect->combined_score,
                    $prospect->gbp_score,
                    $prospect->a11y_score,
                    $prospect->dominant_angle,
                    implode('; ', $prospect->gbp_flags ?? []),
                    implode('; ', $prospect->a11y_flags ?? []),
                    $prospect->report ? url('/r/'.$prospect->report->token) : '',
                    $email?->subject_line ?? '',
                    $email?->sent_at?->toDateTimeString() ?? '',
                    $email?->response_received ? '1' : '0',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
