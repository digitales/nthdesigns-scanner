<?php

namespace App\Http\Requests;

use App\Models\IgnoredProspect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIgnoredProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('prospect'));
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                Rule::in([
                    IgnoredProspect::REASON_ACQUIRED,
                    IgnoredProspect::REASON_COLD,
                    IgnoredProspect::REASON_OUTREACH_FAILED,
                    IgnoredProspect::REASON_OTHER,
                ]),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
