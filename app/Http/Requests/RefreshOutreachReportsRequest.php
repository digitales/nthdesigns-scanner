<?php

namespace App\Http\Requests;

use App\Enums\PitchAngleOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefreshOutreachReportsRequest extends FormRequest
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
            'agency_name' => ['nullable', 'string', 'max:100'],
            'pitch_angle' => ['required', Rule::enum(PitchAngleOption::class)],
            'cpc_benchmark' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
