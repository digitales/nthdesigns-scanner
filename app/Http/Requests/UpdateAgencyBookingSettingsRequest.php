<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgencyBookingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'fastmail_username' => ['nullable', 'email', 'max:255'],
            'fastmail_app_password' => ['nullable', 'string', 'max:255'],
            'caldav_calendar_url' => ['nullable', 'url', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'event_duration_minutes' => ['nullable', 'integer', 'in:30'],
            'min_notice_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'confirmation_from_email' => ['nullable', 'email', 'max:255'],
            'confirmation_from_name' => ['nullable', 'string', 'max:100'],
            'working_hours' => ['nullable', 'array'],
            'working_hours.*.enabled' => ['boolean'],
            'working_hours.*.start' => ['nullable', 'string', 'max:5'],
            'working_hours.*.end' => ['nullable', 'string', 'max:5'],
        ];
    }
}
