<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProspectValidationSignalRequest extends FormRequest
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
            'pattern' => ['required', 'string', 'min:2', 'max:100', Rule::unique('prospect_validation_signals', 'pattern')],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('pattern')) {
            $this->merge([
                'pattern' => strtolower(trim((string) $this->input('pattern'))),
            ]);
        }
    }
}
