<?php

namespace App\Http\Requests;

use App\Enums\ProspectValidatorStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProspectValidatorOverrideRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ProspectValidatorStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
