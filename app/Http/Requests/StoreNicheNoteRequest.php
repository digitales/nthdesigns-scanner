<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNicheNoteRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
