<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProspectListRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'filter' => ['sometimes', 'array'],
            'filter.niche' => ['nullable', 'string', 'max:100'],
            'filter.city' => ['nullable', 'string', 'max:100'],
            'filter.min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'filter.warm' => ['nullable', 'boolean'],
            'filter.tags' => ['nullable', 'array'],
            'filter.tags.*' => ['string', 'max:50'],
            'filter.has_note' => ['nullable', 'boolean'],
        ];
    }
}
