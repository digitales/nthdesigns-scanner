<?php

namespace Tests\Feature;

use App\Models\AgencyBookingSetting;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_page_renders_tidycal_embed_from_config(): void
    {
        config(['scanner.report_booking_url' => 'https://tidycal.com/368j4y9']);

        $this->get(route('book.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Book/Index')
                ->where('embedPath', '368j4y9'));
    }

    public function test_book_page_uses_report_snapshot_when_report_query_present(): void
    {
        config(['scanner.report_booking_url' => 'https://tidycal.com/default']);

        $search = Search::factory()->create();
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        $report = ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'token' => 'test-token-abc',
            'report_data' => [
                'booking_url' => 'https://tidycal.com/from-report',
            ],
        ]);

        $this->get(route('book.index', ['report' => $report->token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Book/Index')
                ->where('embedPath', 'from-report'));
    }

    public function test_book_page_redirects_away_for_non_tidycal_urls(): void
    {
        config(['scanner.report_booking_url' => 'https://calendly.com/acme/30min']);

        $this->get(route('book.index'))
            ->assertRedirect('https://calendly.com/acme/30min');
    }

    public function test_book_page_redirects_to_native_report_picker_when_enabled(): void
    {
        AgencyBookingSetting::current()->update([
            'enabled' => true,
            'fastmail_username' => 'bookings@example.com',
            'fastmail_app_password' => 'test-app-password',
            'caldav_calendar_url' => 'https://caldav.fastmail.com/dav/calendars/user/bookings%40example.com/primary/',
        ]);

        config(['scanner.report_booking_url' => 'https://tidycal.com/legacy']);

        $search = Search::factory()->create();
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        $report = ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'token' => 'native-token',
            'report_data' => [
                'booking_url' => 'https://tidycal.com/from-report',
            ],
        ]);

        $this->get(route('book.index', ['report' => $report->token]))
            ->assertRedirect(url('/r/'.$report->token).'#book');
    }

    public function test_book_page_returns_404_when_native_booking_enabled_without_report_token(): void
    {
        AgencyBookingSetting::current()->update([
            'enabled' => true,
            'fastmail_username' => 'bookings@example.com',
            'fastmail_app_password' => 'test-app-password',
            'caldav_calendar_url' => 'https://caldav.fastmail.com/dav/calendars/user/bookings%40example.com/primary/',
        ]);

        config(['scanner.report_booking_url' => 'https://tidycal.com/legacy']);

        $this->get(route('book.index'))->assertNotFound();
    }

    public function test_book_page_returns_404_when_not_configured(): void
    {
        config(['scanner.report_booking_url' => null]);

        $this->get(route('book.index'))->assertNotFound();
    }

    public function test_public_report_exposes_on_site_book_cta_for_tidycal(): void
    {
        config(['scanner.report_booking_url' => 'https://tidycal.com/368j4y9']);

        $search = Search::factory()->create();
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        $report = ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'token' => 'cta-token',
            'report_data' => [
                'prospect' => ['business_name' => 'Acme Ltd'],
                'booking_url' => 'https://tidycal.com/368j4y9',
                'generated_at' => now()->toISOString(),
            ],
        ]);

        $this->get(route('reports.public', ['token' => $report->token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('report', fn (Assert $report) => $report
                    ->where('book_cta_external', false)
                    ->where('book_cta_url', route('book.index', ['report' => 'cta-token']))
                    ->etc()));
    }
}
