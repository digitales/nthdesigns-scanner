<?php

namespace App\Http\Requests;

use App\Enums\PitchAngleOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateOutreachEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'agency_name' => ['nullable', 'string', 'max:100'],
            'pitch_angle' => ['required', Rule::enum(PitchAngleOption::class)],
            'cpc_benchmark' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
