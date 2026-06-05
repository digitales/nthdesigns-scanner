<?php

namespace App\Http\Requests;

use App\Models\IgnoredProspect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterIgnoredProspectsRequest extends FormRequest
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
            'reason' => [
                'nullable',
                'string',
                Rule::in([
                    IgnoredProspect::REASON_ACQUIRED,
                    IgnoredProspect::REASON_COLD,
                    IgnoredProspect::REASON_OUTREACH_FAILED,
                    IgnoredProspect::REASON_OTHER,
                ]),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
