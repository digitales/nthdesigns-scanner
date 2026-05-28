<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDirectUrlSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'website_url' => ['required', 'string', 'max:2048', 'regex:/^(https?:\/\/)?[^\s\/]+\.[^\s\/]+/i'],
        ];
    }
}
