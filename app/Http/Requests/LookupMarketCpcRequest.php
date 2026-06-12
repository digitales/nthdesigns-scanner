<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupMarketCpcRequest extends FormRequest
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
        ];
    }
}
