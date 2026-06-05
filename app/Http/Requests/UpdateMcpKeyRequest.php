<?php

namespace App\Http\Requests;

use App\Models\UserMcpKey;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMcpKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mcpKey = $this->route('mcpKey');

        return $mcpKey instanceof UserMcpKey
            && $this->user()?->can('update', $mcpKey);
    }

    public function rules(): array
    {
        $mcpKey = $this->route('mcpKey');

        return [
            'label_'.$mcpKey->id => ['nullable', 'string', 'max:64'],
        ];
    }

    public function label(): ?string
    {
        $mcpKey = $this->route('mcpKey');

        return $this->input('label_'.$mcpKey->id);
    }
}
