<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProspectValidationSignalRequest extends FormRequest
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
        $signal = $this->route('prospectValidationSignal');

        return [
            'pattern' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('prospect_validation_signals', 'pattern')->ignore($signal?->id),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
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
