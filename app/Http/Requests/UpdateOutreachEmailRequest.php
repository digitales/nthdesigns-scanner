<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOutreachEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('outreachEmail')) ?? false;
    }

    public function rules(): array
    {
        return [
            'subject_line' => ['required', 'string', 'max:255'],
            'email_body' => ['required', 'string'],
        ];
    }
}
