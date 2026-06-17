<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExportRequest;
use App\Models\Export;
use App\Queries\ProspectListQuery;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function store(StoreExportRequest $request): StreamedResponse|RedirectResponse
    {
        $this->authorize('create', Export::class);

        $filters = $request->validated();

        $prospects = (new ProspectListQuery($request->user()))
            ->apply($filters)
            ->query()
            ->with(['search', 'report', 'outreachEmails' => fn ($q) => $q->latest()])
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
                'business_name', 'niche', 'city', 'country', 'phone', 'email', 'website_url',
                'contact_page_url', 'linkedin_url', 'outreach_channel',
                'combined_score', 'gbp_score', 'a11y_score', 'dominant_angle',
                'gbp_flags', 'a11y_flags', 'report_url',
                'outreach_subject', 'outreach_form_message', 'outreach_linkedin_message',
                'outreach_sent_at', 'response_received',
            ]);

            foreach ($prospects as $prospect) {
                $latestByChannel = $prospect->outreachEmails
                    ->groupBy(fn ($email) => $email->channel instanceof \App\Enums\OutreachChannel
                        ? $email->channel->value
                        : ($email->getAttributes()['channel'] ?? 'email'))
                    ->map(fn ($group) => $group->first());
                $emailOutreach = $latestByChannel->get('email');
                $formOutreach = $latestByChannel->get('contact_form');
                $linkedInOutreach = $latestByChannel->get('linkedin');

                fputcsv($out, [
                    $prospect->business_name,
                    $prospect->search->niche,
                    $prospect->search->city,
                    $prospect->search->country,
                    $prospect->phone,
                    $prospect->email,
                    $prospect->website_url,
                    $prospect->contact_page_url,
                    $prospect->linkedin_url,
                    $prospect->outreach_channel?->value ?? 'auto',
                    $prospect->combined_score,
                    $prospect->gbp_score,
                    $prospect->a11y_score,
                    $prospect->dominant_angle instanceof \BackedEnum
                        ? $prospect->dominant_angle->value
                        : $prospect->dominant_angle,
                    implode('; ', $prospect->gbp_flags ?? []),
                    implode('; ', $prospect->a11y_flags ?? []),
                    $prospect->report ? url('/r/'.$prospect->report->token) : '',
                    $emailOutreach?->subject_line ?? '',
                    $formOutreach?->email_body ?? '',
                    $linkedInOutreach?->email_body ?? '',
                    $emailOutreach?->sent_at?->toDateTimeString()
                        ?? $formOutreach?->sent_at?->toDateTimeString()
                        ?? $linkedInOutreach?->sent_at?->toDateTimeString()
                        ?? '',
                    ($emailOutreach?->response_received
                        || $formOutreach?->response_received
                        || $linkedInOutreach?->response_received) ? '1' : '0',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
