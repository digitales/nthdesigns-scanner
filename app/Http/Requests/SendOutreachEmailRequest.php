<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendOutreachEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('outreachEmail')) ?? false;
    }

    public function rules(): array
    {
        return [
            'confirm_warned' => ['sometimes', 'boolean'],
        ];
    }

    public function confirmWarned(): bool
    {
        return $this->boolean('confirm_warned');
    }
}
