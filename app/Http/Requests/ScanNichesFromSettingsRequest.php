<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanNichesFromSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
