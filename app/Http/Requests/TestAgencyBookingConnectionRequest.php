<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestAgencyBookingConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'fastmail_username' => ['required', 'email', 'max:255'],
            'fastmail_app_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
