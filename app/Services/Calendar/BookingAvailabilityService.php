<?php

namespace App\Services\Calendar;

use App\Contracts\Calendar\CalendarProvider;
use App\Contracts\Calendar\TimeInterval;
use App\Models\AgencyBookingSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BookingAvailabilityService
{
    public function __construct(
        private CalendarProvider $calendar,
    ) {}

    /**
     * @return list<array{starts_at: string, ends_at: string, label: string}>
     */
    public function availableSlots(
        AgencyBookingSetting $settings,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): array {
        $tz = $settings->timezone ?: config('booking.default_timezone');
        $from = ($from ?? Carbon::now($tz))->copy()->timezone($tz)->startOfDay();
        $to = ($to ?? $from->copy()->addDays(config('booking.slot_horizon_days')))->copy()->timezone($tz)->endOfDay();

        $duration = $settings->event_duration_minutes;
        $buffer = $settings->buffer_minutes;
        $minNotice = Carbon::now($tz)->addHours($settings->min_notice_hours);
        $schedule = $settings->workingHoursSchedule();

        $busy = array_map(
            fn (array $interval) => new TimeInterval(
                $interval['start']->copy()->utc(),
                $interval['end']->copy()->utc(),
            ),
            $this->calendar->busyIntervals($from->copy()->utc(), $to->copy()->utc()),
        );

        $slots = [];
        $cursor = $from->copy();

        while ($cursor <= $to) {
            $dayKey = strtolower($cursor->englishDayOfWeek);
            $day = $schedule[$dayKey] ?? ['enabled' => false];

            if (! ($day['enabled'] ?? false)) {
                $cursor->addDay()->startOfDay();

                continue;
            }

            $dayStart = $cursor->copy()->setTimeFromTimeString($day['start'] ?? '09:00');
            $dayEnd = $cursor->copy()->setTimeFromTimeString($day['end'] ?? '17:00');
            $slotAt = $dayStart->copy();
            $daySlots = 0;

            while ($slotAt->copy()->addMinutes($duration) <= $dayEnd) {
                $slotEnd = $slotAt->copy()->addMinutes($duration);
                $candidate = new TimeInterval($slotAt->copy()->utc(), $slotEnd->copy()->utc());

                if ($slotAt >= $minNotice && ! $this->overlapsBusy($candidate, $busy, $buffer) && $daySlots < config('booking.slots_per_day_max')) {
                    $slots[] = [
                        'starts_at' => $slotAt->toIso8601String(),
                        'ends_at' => $slotEnd->toIso8601String(),
                        'label' => $slotAt->format('D j M, g:i A'),
                    ];
                    $daySlots++;
                }

                $slotAt->addMinutes($duration);
            }

            $cursor->addDay()->startOfDay();
        }

        return $slots;
    }

    public function slotIsAvailable(AgencyBookingSetting $settings, CarbonInterface $startsAt): bool
    {
        $tz = $settings->timezone ?: config('booking.default_timezone');
        $localStart = $startsAt->copy()->timezone($tz);
        $localEnd = $localStart->copy()->addMinutes($settings->event_duration_minutes);

        foreach ($this->availableSlots($settings, $localStart->copy()->startOfDay(), $localEnd->copy()->endOfDay()) as $slot) {
            if (Carbon::parse($slot['starts_at'])->utc()->equalTo($localStart->copy()->utc())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<TimeInterval>  $busy
     */
    private function overlapsBusy(TimeInterval $candidate, array $busy, int $bufferMinutes): bool
    {
        $padded = new TimeInterval(
            $candidate->start->copy()->subMinutes($bufferMinutes),
            $candidate->end->copy()->addMinutes($bufferMinutes),
        );

        foreach ($busy as $interval) {
            if ($padded->overlaps($interval)) {
                return true;
            }
        }

        return false;
    }
}
