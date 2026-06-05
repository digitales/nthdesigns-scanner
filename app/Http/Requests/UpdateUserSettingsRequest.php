<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'default_country' => ['required', 'string', 'size:2'],
            'agency_name' => ['nullable', 'string', 'max:100'],
            'booking_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
