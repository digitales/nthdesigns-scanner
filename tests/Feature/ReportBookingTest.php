<?php

namespace Tests\Feature;

use App\Contracts\Calendar\CalendarProvider;
use App\Mail\ReportBookingConfirmed;
use App\Models\AgencyBookingSetting;
use App\Models\ProspectReport;
use App\Services\Calendar\FakeCalendarProvider;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use Tests\TestCase;

class ReportBookingTest extends TestCase
{
    use RefreshDatabase;

    private FakeCalendarProvider $calendar;

    private MailManager $mailManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailManager = $this->app->make(MailManager::class);
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

    public function test_book_returns_503_when_calendar_provider_fails(): void
    {
        $report = ProspectReport::factory()->create();
        $slot = $this->getJson('/r/'.$report->token.'/slots')->json('slots.0');

        $this->calendar->failOnCreate(
            new RequestException(new HttpResponse(new PsrResponse(503)))
        );

        $this->postJson('/r/'.$report->token.'/book', [
            'starts_at' => $slot['starts_at'],
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
        ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Booking is temporarily unavailable. Please try again shortly.');

        $this->assertDatabaseMissing('report_bookings', [
            'prospect_report_id' => $report->id,
        ]);
        $this->assertCount(0, $this->calendar->createdEvents());
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

    public function test_slots_returns_empty_when_native_booking_disabled(): void
    {
        AgencyBookingSetting::current()->update(['enabled' => false]);
        $report = ProspectReport::factory()->create();

        $this->getJson('/r/'.$report->token.'/slots')
            ->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_book_rejects_slot_already_taken_on_calendar(): void
    {
        $report = ProspectReport::factory()->create();
        $slot = $this->getJson('/r/'.$report->token.'/slots')->json('slots.0');

        $otherReport = ProspectReport::factory()->create();
        $this->postJson('/r/'.$otherReport->token.'/book', [
            'starts_at' => $slot['starts_at'],
            'attendee_name' => 'First Booker',
            'attendee_email' => 'first@example.com',
        ])->assertCreated();

        $this->postJson('/r/'.$report->token.'/book', [
            'starts_at' => $slot['starts_at'],
            'attendee_name' => 'Second Booker',
            'attendee_email' => 'second@example.com',
        ])->assertStatus(409);
    }

    public function test_book_persists_when_confirmation_mail_fails(): void
    {
        Mail::swap(new class($this->mailManager) extends MailFake
        {
            protected function sendMail($view, $shouldQueue = false)
            {
                throw new \RuntimeException('SMTP unavailable');
            }
        });

        $report = ProspectReport::factory()->create();
        $slot = $this->getJson('/r/'.$report->token.'/slots')->json('slots.0');

        $this->postJson('/r/'.$report->token.'/book', [
            'starts_at' => $slot['starts_at'],
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
        ])->assertCreated();

        $booking = $report->fresh()->booking;
        $this->assertNotNull($booking);
        $this->assertSame('confirmed', $booking->status);
        $this->assertNull($booking->confirmation_sent_at);
        $this->assertCount(1, $this->calendar->createdEvents());
    }
}
