<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('prospect'));
    }

    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'website_url'   => ['sometimes', 'nullable', 'url', 'max:500'],
            'address'       => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasAny(['business_name', 'phone', 'website_url', 'address'])) {
                $validator->errors()->add('business_name', 'Provide at least one field to update.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('website_url') && filled($this->input('website_url'))) {
            $url = trim((string) $this->input('website_url'));
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                $url = 'https://'.$url;
            }
            $this->merge(['website_url' => $url]);
        }
    }
}
