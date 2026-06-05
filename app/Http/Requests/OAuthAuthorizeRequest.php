<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OAuthAuthorizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('resource')) {
            $this->merge([
                'resource' => config('oauth-mcp.resource'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'response_type' => ['required', 'in:code'],
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
            'scope' => ['nullable', 'string'],
            'state' => ['nullable', 'string'],
            'code_challenge' => ['required', 'string'],
            'code_challenge_method' => ['required', 'in:S256'],
            'resource' => ['required', 'url'],
        ];
    }
}
