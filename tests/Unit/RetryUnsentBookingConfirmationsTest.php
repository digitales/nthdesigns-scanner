<?php

namespace Tests\Unit;

use App\Jobs\SendReportBookingConfirmationJob;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RetryUnsentBookingConfirmationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_unsent_confirmations(): void
    {
        Bus::fake();

        $report = ProspectReport::factory()->create();

        $booking = ReportBooking::query()->create([
            'prospect_report_id' => $report->id,
            'prospect_id' => $report->prospect_id,
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addMinutes(30),
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
            'calendar_event_uid' => 'scanner-test@nthdesigns.co.uk',
            'status' => 'confirmed',
        ]);
        $booking->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $this->artisan('booking:retry-unsent-confirmations')->assertSuccessful();

        Bus::assertDispatched(SendReportBookingConfirmationJob::class, fn ($job) => $job->booking->id === $booking->id);
    }
}
