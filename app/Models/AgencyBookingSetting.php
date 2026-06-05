<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyBookingSetting extends Model
{
    protected $fillable = [
        'enabled',
        'fastmail_username',
        'fastmail_app_password',
        'caldav_calendar_url',
        'timezone',
        'event_duration_minutes',
        'min_notice_hours',
        'buffer_minutes',
        'working_hours',
        'confirmation_from_email',
        'confirmation_from_name',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'fastmail_app_password' => 'encrypted',
            'working_hours' => 'array',
            'event_duration_minutes' => 'integer',
            'min_notice_hours' => 'integer',
            'buffer_minutes' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'timezone' => config('booking.default_timezone'),
                'event_duration_minutes' => config('booking.default_duration_minutes'),
                'min_notice_hours' => config('booking.default_min_notice_hours'),
                'buffer_minutes' => config('booking.default_buffer_minutes'),
                'working_hours' => config('booking.default_working_hours'),
            ],
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->fastmail_username)
            && filled($this->fastmail_app_password)
            && filled($this->caldav_calendar_url);
    }

    public function nativeBookingActive(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    /**
     * @return array<string, array{enabled: bool, start: string, end: string}>
     */
    public function workingHoursSchedule(): array
    {
        $hours = $this->working_hours;

        if (! is_array($hours) || $hours === []) {
            return config('booking.default_working_hours');
        }

        return $hours;
    }
}
