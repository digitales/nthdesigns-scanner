<?php

namespace App\Http\Resources;

use App\Models\ProspectReport;
use App\Services\AgencyBookingService;
use App\Services\ReportBuilderService;
use App\Support\TidyCalEmbed;
use Illuminate\Http\Request;

class PublicReportResource
{
    /**
     * @return array<string, mixed>
     */
    public static function format(
        ProspectReport $report,
        Request $request,
        AgencyBookingService $agencyBooking,
        ReportBuilderService $reportBuilder,
    ): array {
        $data = $report->report_data ?? [];
        $nativeBooking = $agencyBooking->nativeBookingActive();
        $bookingUrl = $data['booking_url'] ?? config('scanner.report_booking_url');
        $bookCtaUrl = $nativeBooking
            ? null
            : (TidyCalEmbed::bookPageUrl($bookingUrl, ['report' => $report->token]) ?? $bookingUrl);
        $booking = $report->booking;
        $combinedScore = (int) $report->prospect->combined_score;
        $grade = $reportBuilder->combinedToGrade($combinedScore);

        return [
            'business_name' => $data['prospect']['business_name'] ?? $report->prospect->business_name,
            'address' => $data['prospect']['address'] ?? $report->prospect->address,
            'niche' => $data['niche'] ?? $report->prospect->search->niche,
            'city' => $data['city'] ?? $report->prospect->search->city,
            'website_url' => $data['website_url'] ?? $report->prospect->website_url,
            'prospect' => $data['prospect'] ?? [],
            'benchmark' => $data['benchmark'] ?? null,
            'comparison' => $data['comparison'] ?? [],
            'grade' => $grade,
            'grade_label' => $reportBuilder->gradeLabel($grade),
            'combined_score' => $combinedScore,
            'performance_score' => $report->prospect->performance_score,
            'violation_summary' => $data['violation_summary'] ?? ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0, 'total' => 0],
            'top_violations' => $data['top_violations'] ?? [],
            'lighthouse' => $data['lighthouse'] ?? [],
            'booking_url' => $nativeBooking ? null : $bookingUrl,
            'book_cta_url' => $bookCtaUrl,
            'book_cta_external' => $bookingUrl && ! TidyCalEmbed::isEmbeddable($bookingUrl),
            'native_booking' => $nativeBooking,
            'booking' => $booking ? [
                'starts_at' => $booking->starts_at->toIso8601String(),
                'label' => $booking->starts_at
                    ->timezone($agencyBooking->settings()->timezone)
                    ->format('l j F Y, g:i A'),
                'attendee_name' => $booking->attendee_name,
            ] : null,
            'generated_at' => $data['generated_at'] ?? $report->created_at->toISOString(),
            'expires_at' => $report->expires_at?->toISOString(),
            'token' => $report->token,
            'screenshot_paths' => $report->screenshot_paths ?? [],
        ];
    }
}
