<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterReportDashboardRequest extends FormRequest
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
            'niche' => ['nullable', 'string', 'max:100'],
            'viewed' => ['nullable', 'in:0,1'],
            'warm' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
