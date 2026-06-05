<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OAuthRevokeTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'client_id' => ['required', 'string'],
            'token_type_hint' => ['nullable', 'in:refresh_token,access_token'],
        ];
    }
}
