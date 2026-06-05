<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OAuthTokenRequest extends FormRequest
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
        $grantType = (string) $this->input('grant_type');

        return match ($grantType) {
            'authorization_code' => [
                'grant_type' => ['required', 'in:authorization_code'],
                'code' => ['required', 'string'],
                'redirect_uri' => ['required', 'url'],
                'client_id' => ['required', 'string'],
                'code_verifier' => ['required', 'string'],
                'resource' => ['required', 'url'],
            ],
            'refresh_token' => [
                'grant_type' => ['required', 'in:refresh_token'],
                'refresh_token' => ['required', 'string'],
                'client_id' => ['required', 'string'],
                'resource' => ['required', 'url'],
                'scope' => ['nullable', 'string'],
            ],
            default => [
                'grant_type' => ['required', Rule::in(['authorization_code', 'refresh_token'])],
            ],
        };
    }
}
