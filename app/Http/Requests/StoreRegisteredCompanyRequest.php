<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRegisteredCompanyRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{8}$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $name = trim((string) $this->input('name'));
            $number = trim((string) $this->input('number'));

            if ($name === '' && $number === '') {
                $validator->errors()->add('name', 'Enter a registered company name or number.');
                $validator->errors()->add('number', 'Enter a registered company name or number.');
            }
        });
    }

    /**
     * @return array{name: ?string, number: ?string, note: ?string}
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        $name = trim((string) ($validated['name'] ?? ''));
        $number = strtoupper(trim((string) ($validated['number'] ?? '')));
        $note = trim((string) ($validated['note'] ?? ''));

        return [
            'name' => $name !== '' ? $name : null,
            'number' => $number !== '' ? $number : null,
            'note' => $note !== '' ? $note : null,
        ];
    }
}
