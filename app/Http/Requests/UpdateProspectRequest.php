<?php

namespace App\Http\Requests;

use App\Enums\ProspectOutreachChannel;
use App\Enums\UseFormOutreach;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'contact_page_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'use_form_outreach' => ['sometimes', Rule::enum(UseFormOutreach::class)],
            'outreach_channel' => ['sometimes', Rule::enum(ProspectOutreachChannel::class)],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasAny([
                'business_name', 'phone', 'email', 'linkedin_url', 'contact_page_url',
                'use_form_outreach', 'outreach_channel', 'website_url', 'address',
            ])) {
                $validator->errors()->add('business_name', 'Provide at least one field to update.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && filled($this->input('email'))) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }

        foreach (['linkedin_url', 'contact_page_url', 'website_url'] as $field) {
            if (! $this->has($field) || ! filled($this->input($field))) {
                continue;
            }

            $url = trim((string) $this->input($field));

            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                $url = 'https://'.$url;
            }

            $this->merge([$field => $url]);
        }
    }
}
