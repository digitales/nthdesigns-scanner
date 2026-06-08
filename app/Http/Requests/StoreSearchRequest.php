<?php

namespace App\Http\Requests;

use App\Enums\ScanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'niche' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'scan_type' => ['required', Rule::enum(ScanType::class)],
        ];
    }
}
