<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'pitch_angle' => ['required', 'in:auto,gbp,accessibility,combined'],
            'cpc_benchmark' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
