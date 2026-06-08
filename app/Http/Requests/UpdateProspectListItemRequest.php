<?php

namespace App\Http\Requests;

use App\Enums\ListItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProspectListItemRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(ListItemStatus::class)],
            'follow_up_at' => ['nullable', 'date'],
        ];
    }
}
