<?php

namespace Tests\Feature;

use App\Enums\ReportBookingStatus;
use App\Models\AgencyBookingSetting;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_view_upcoming_bookings(): void
    {
        $user = User::factory()->create();
        $report = ProspectReport::factory()->create();
        $report->prospect->search->update(['user_id' => $user->id]);

        ReportBooking::query()->create([
            'prospect_report_id' => $report->id,
            'prospect_id' => $report->prospect_id,
            'starts_at' => Carbon::parse('2026-06-20 14:00:00', 'Europe/London'),
            'ends_at' => Carbon::parse('2026-06-20 14:30:00', 'Europe/London'),
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
            'calendar_event_uid' => 'scanner-test@nthdesigns.co.uk',
            'status' => ReportBookingStatus::Confirmed,
        ]);

        AgencyBookingSetting::current()->update(['enabled' => true]);

        $this->actingAs($user)
            ->get('/bookings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Bookings/Index')
                ->has('bookings', 1)
                ->where('bookings.0.business_name', $report->prospect->business_name));
    }

    public function test_operator_can_queue_confirmation_resend(): void
    {
        $user = User::factory()->create();
        $report = ProspectReport::factory()->create();
        $report->prospect->search->update(['user_id' => $user->id]);

        ReportBooking::query()->create([
            'prospect_report_id' => $report->id,
            'prospect_id' => $report->prospect_id,
            'starts_at' => Carbon::parse('2026-06-20 14:00:00', 'Europe/London'),
            'ends_at' => Carbon::parse('2026-06-20 14:30:00', 'Europe/London'),
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
            'calendar_event_uid' => 'scanner-test@nthdesigns.co.uk',
            'status' => ReportBookingStatus::Confirmed,
            'confirmation_sent_at' => null,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$report->prospect_id}/booking/resend-confirmation")
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}
