<?php

return [

    'default_timezone' => env('BOOKING_TIMEZONE', 'Europe/London'),

    'default_duration_minutes' => (int) env('BOOKING_DURATION_MINUTES', 30),

    'default_min_notice_hours' => (int) env('BOOKING_MIN_NOTICE_HOURS', 24),

    'default_buffer_minutes' => (int) env('BOOKING_BUFFER_MINUTES', 0),

    'slot_horizon_days' => (int) env('BOOKING_SLOT_HORIZON_DAYS', 21),

    'slots_per_day_max' => (int) env('BOOKING_SLOTS_PER_DAY_MAX', 8),

    /**
     * Default working hours when agency settings row has none (Europe/London, Mon–Fri 09:00–17:00).
     *
     * @var array<string, array{enabled: bool, start: string, end: string}>
     */
    'default_working_hours' => [
        'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
        'tuesday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
        'wednesday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
        'thursday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
        'friday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
        'saturday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
        'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
    ],

];
