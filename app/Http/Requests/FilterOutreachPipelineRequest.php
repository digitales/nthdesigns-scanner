<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterOutreachPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'booked' => $this->boolean('booked'),
        ]);
    }

    public function rules(): array
    {
        return [
            'booked' => ['boolean'],
            'niche' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'outreach_status' => ['nullable', 'string', Rule::in(['none', 'drafted', 'sent'])],
            'sort' => ['nullable', 'string', Rule::in(['report_age', 'score', 'name'])],
        ];
    }
}
