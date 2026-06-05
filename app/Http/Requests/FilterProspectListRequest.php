<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterProspectListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'niche' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'scan_type' => ['nullable', 'in:gbp_only,accessibility_only,combined'],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'dominant_angle' => ['nullable', 'in:gbp,accessibility,both'],
            'warm' => ['nullable', 'boolean'],
        ];
    }
}
