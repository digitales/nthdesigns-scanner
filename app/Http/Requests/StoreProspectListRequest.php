<?php

namespace App\Http\Requests;

use App\Enums\ProspectListType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProspectListRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(ProspectListType::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'filter' => ['nullable', 'array'],
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
