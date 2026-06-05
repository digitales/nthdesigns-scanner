<?php

namespace Tests\Unit;

use App\Models\AgencyBookingSetting;
use App\Services\Calendar\BookingAvailabilityService;
use App\Services\Calendar\FakeCalendarProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_busy_intervals_remove_overlapping_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00', 'Europe/London'));

        $fake = new FakeCalendarProvider;
        $fake->setBusyIntervals([
            [
                'start' => Carbon::parse('2026-06-11 10:00:00', 'Europe/London')->utc(),
                'end' => Carbon::parse('2026-06-11 11:00:00', 'Europe/London')->utc(),
            ],
        ]);

        $settings = AgencyBookingSetting::current();
        $settings->enabled = true;
        $settings->min_notice_hours = 1;
        $settings->timezone = 'Europe/London';
        $settings->event_duration_minutes = 30;
        $settings->save();

        $service = new BookingAvailabilityService($fake);
        $day = Carbon::parse('2026-06-11', 'Europe/London');
        $slots = $service->availableSlots($settings, $day, $day);

        foreach ($slots as $slot) {
            $start = Carbon::parse($slot['starts_at'])->timezone('Europe/London');
            $this->assertFalse(
                $start->betweenIncluded(
                    Carbon::parse('2026-06-11 10:00:00', 'Europe/London'),
                    Carbon::parse('2026-06-11 10:29:00', 'Europe/London'),
                ),
                'Slot should not overlap busy block: '.$slot['label'],
            );
        }

        Carbon::setTestNow();
    }
}
