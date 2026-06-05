<?php

namespace Tests\Feature;

use App\Contracts\Calendar\CalendarProvider;
use App\Mail\ReportBookingConfirmed;
use App\Models\AgencyBookingSetting;
use App\Models\ProspectReport;
use App\Services\Calendar\FakeCalendarProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReportBookingTest extends TestCase
{
    use RefreshDatabase;

    private FakeCalendarProvider $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-10 10:00:00', 'Europe/London'));

        $this->calendar = new FakeCalendarProvider;
        $this->app->instance(CalendarProvider::class, $this->calendar);

        AgencyBookingSetting::current()->update([
            'enabled' => true,
            'fastmail_username' => 'bookings@example.com',
            'fastmail_app_password' => 'test-app-password',
            'caldav_calendar_url' => 'https://caldav.fastmail.com/dav/calendars/user/bookings%40example.com/primary/',
            'timezone' => 'Europe/London',
            'event_duration_minutes' => 30,
            'min_notice_hours' => 1,
            'buffer_minutes' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_slots_returns_available_times_for_enabled_booking(): void
    {
        $report = ProspectReport::factory()->create();

        $response = $this->getJson('/r/'.$report->token.'/slots');

        $response->assertOk();
        $response->assertJsonStructure(['slots', 'booking']);
        $this->assertNotEmpty($response->json('slots'));
    }

    public function test_book_creates_record_calendar_event_and_sends_mail(): void
    {
        $report = ProspectReport::factory()->create();

        $slots = $this->getJson('/r/'.$report->token.'/slots')->json('slots');
        $slot = $slots[0];

        $response = $this->postJson('/r/'.$report->token.'/book', [
            'starts_at' => $slot['starts_at'],
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('booking.attendee_name', 'Jane Smith');

        $this->assertDatabaseHas('report_bookings', [
            'prospect_report_id' => $report->id,
            'attendee_email' => 'jane@example.com',
            'status' => 'confirmed',
        ]);

        $this->assertCount(1, $this->calendar->createdEvents());
        Mail::assertSent(ReportBookingConfirmed::class);
    }

    public function test_book_rejects_double_booking_on_same_report(): void
    {
        $report = ProspectReport::factory()->create();
        $slot = $this->getJson('/r/'.$report->token.'/slots')->json('slots.0');

        $this->postJson('/r/'.$report->token.'/book', [
            'starts_at' => $slot['starts_at'],
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
        ])->assertCreated();

        $this->postJson('/r/'.$report->token.'/book', [
            'starts_at' => $slot['starts_at'],
            'attendee_name' => 'Other Person',
            'attendee_email' => 'other@example.com',
        ])->assertStatus(409);
    }

    public function test_public_report_shows_native_booking_when_enabled(): void
    {
        $report = ProspectReport::factory()->create();

        $this->get('/r/'.$report->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Report/Public')
                ->where('report.native_booking', true)
                ->where('report.booking_url', null));
    }
}
