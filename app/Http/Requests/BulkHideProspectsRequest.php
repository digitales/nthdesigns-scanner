<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkHideProspectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prospect_ids' => ['required', 'array', 'min:1'],
            'prospect_ids.*' => ['integer', 'exists:prospects,id'],
        ];
    }
}
