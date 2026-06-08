<?php

namespace App\Http\Requests;

use App\Enums\DominantAngle;
use App\Enums\ScanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'scan_type' => ['nullable', Rule::enum(ScanType::class)],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'dominant_angle' => ['nullable', Rule::enum(DominantAngle::class)],
            'warm' => ['nullable', 'boolean'],
        ];
    }
}
