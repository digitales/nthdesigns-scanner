<?php

namespace App\Http\Requests;

use App\Enums\IgnoredProspectReason;
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
                Rule::enum(IgnoredProspectReason::class),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
