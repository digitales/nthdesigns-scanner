<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OAuthRegisterClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'redirect_uris' => ['required', 'array'],
            'redirect_uris.*' => ['required', 'url'],
        ];
    }
}
