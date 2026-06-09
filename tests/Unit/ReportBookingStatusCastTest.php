<?php

namespace Tests\Unit;

use App\Enums\ReportBookingStatus;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportBookingStatusCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_casts_to_enum_and_persists_string_value(): void
    {
        $report = ProspectReport::factory()->create();

        $booking = ReportBooking::query()->create([
            'prospect_report_id' => $report->id,
            'prospect_id' => $report->prospect_id,
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addMinutes(30),
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
            'calendar_event_uid' => 'scanner-test@nthdesigns.co.uk',
            'status' => ReportBookingStatus::Confirmed,
        ]);

        $this->assertSame(ReportBookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertDatabaseHas('report_bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }
}
