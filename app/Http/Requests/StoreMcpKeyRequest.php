<?php

namespace App\Http\Requests;

use App\Models\UserMcpKey;
use Illuminate\Foundation\Http\FormRequest;

class StoreMcpKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', UserMcpKey::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:64'],
        ];
    }
}
