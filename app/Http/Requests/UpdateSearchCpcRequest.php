<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSearchCpcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cpc_benchmark' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'cpc_source' => ['nullable', 'string', 'max:32'],
            'cpc_keywords' => ['nullable', 'array'],
            'cpc_keywords.*' => ['string', 'max:120'],
            'save_market_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<string>
     */
    public function normalizedKeywords(): array
    {
        $keywords = $this->input('cpc_keywords', []);

        if (! is_array($keywords)) {
            return [];
        }

        return collect($keywords)
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
