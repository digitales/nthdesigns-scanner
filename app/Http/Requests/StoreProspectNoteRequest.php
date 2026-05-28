<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProspectNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('prospect'));
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
