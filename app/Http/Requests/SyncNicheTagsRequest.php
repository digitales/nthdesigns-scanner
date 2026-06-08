<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncNicheTagsRequest extends FormRequest
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
            'niche_label' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:100'],
            'action' => ['required', Rule::in(['attach', 'detach'])],
            'tag_name' => ['required', 'string', 'max:50'],
        ];
    }
}
