<?php

namespace Tests\Unit;

use App\Models\AgencyBookingSetting;
use App\Services\Calendar\BookingAvailabilityService;
use App\Services\Calendar\FakeCalendarProvider;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

    public function test_slot_is_available_rejects_busy_slot_without_scanning_whole_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00', 'Europe/London'));

        $fake = new class extends FakeCalendarProvider
        {
            public ?CarbonInterface $busyFrom = null;

            public ?CarbonInterface $busyTo = null;

            public function busyIntervals(CarbonInterface $from, CarbonInterface $to): array
            {
                $this->busyFrom = $from;
                $this->busyTo = $to;

                return parent::busyIntervals($from, $to);
            }
        };

        $fake->setBusyIntervals([
            [
                'start' => Carbon::parse('2026-06-11 14:00:00', 'Europe/London')->utc(),
                'end' => Carbon::parse('2026-06-11 14:30:00', 'Europe/London')->utc(),
            ],
        ]);

        $settings = AgencyBookingSetting::current();
        $settings->enabled = true;
        $settings->min_notice_hours = 1;
        $settings->buffer_minutes = 0;
        $settings->timezone = 'Europe/London';
        $settings->event_duration_minutes = 30;
        $settings->save();

        $service = new BookingAvailabilityService($fake);
        $slotStart = Carbon::parse('2026-06-11 14:00:00', 'Europe/London');

        $this->assertFalse($service->slotIsAvailable($settings, $slotStart));
        $this->assertNotNull($fake->busyFrom);
        $this->assertNotNull($fake->busyTo);
        $this->assertLessThanOrEqual(60, $fake->busyFrom->diffInMinutes($slotStart->copy()->utc()));
        $this->assertLessThanOrEqual(60, $fake->busyTo->diffInMinutes($slotStart->copy()->utc()->addMinutes(30)));

        Carbon::setTestNow();
    }

    public function test_slot_is_available_accepts_open_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00', 'Europe/London'));

        $fake = new FakeCalendarProvider;
        $settings = AgencyBookingSetting::current();
        $settings->enabled = true;
        $settings->min_notice_hours = 1;
        $settings->timezone = 'Europe/London';
        $settings->event_duration_minutes = 30;
        $settings->save();

        $service = new BookingAvailabilityService($fake);
        $slotStart = Carbon::parse('2026-06-11 11:00:00', 'Europe/London');

        $this->assertTrue($service->slotIsAvailable($settings, $slotStart));

        Carbon::setTestNow();
    }
}
