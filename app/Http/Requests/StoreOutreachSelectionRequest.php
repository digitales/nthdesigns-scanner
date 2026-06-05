<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutreachSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prospect_ids' => ['required', 'array'],
            'prospect_ids.*' => ['integer', 'exists:prospects,id'],
        ];
    }
}
