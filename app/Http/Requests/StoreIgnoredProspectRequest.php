<?php

namespace App\Http\Requests;

use App\Enums\IgnoredProspectReason;
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
                Rule::enum(IgnoredProspectReason::class),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
