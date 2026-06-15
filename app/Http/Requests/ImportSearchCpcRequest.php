<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportSearchCpcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a CSV file from Keyword Planner (max 2 MB).',
            'file.file' => 'Please upload a CSV file from Keyword Planner (max 2 MB).',
            'file.mimes' => 'Please upload a CSV file from Keyword Planner (max 2 MB).',
            'file.max' => 'Please upload a CSV file from Keyword Planner (max 2 MB).',
        ];
    }
}
