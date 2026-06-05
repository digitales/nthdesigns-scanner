<?php

namespace App\Http\Requests;

use App\Services\ApiUsage\ApiQuotaSettingsService;
use App\Support\ApiUsage\ApiOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateApiQuotaSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [];

        foreach (ApiOperation::all() as $operation) {
            foreach (['daily', 'monthly'] as $periodType) {
                $column = ApiOperation::settingsColumn(
                    $operation['provider'],
                    $operation['operation'],
                    $periodType,
                );
                $rules[$column] = ['nullable', 'integer', 'min:0'];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ApiQuotaSettingsService $quotas */
            $quotas = app(ApiQuotaSettingsService::class);

            foreach (ApiOperation::all() as $operation) {
                foreach (['daily', 'monthly'] as $periodType) {
                    $column = ApiOperation::settingsColumn(
                        $operation['provider'],
                        $operation['operation'],
                        $periodType,
                    );

                    if (! $this->filled($column)) {
                        continue;
                    }

                    $value = $this->input($column);

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $ceiling = $quotas->envLimit(
                        $operation['provider'],
                        $operation['operation'],
                        $periodType,
                    );

                    if ((int) $value > $ceiling) {
                        $validator->errors()->add(
                            $column,
                            "Cannot exceed env ceiling ({$ceiling}).",
                        );
                    }
                }
            }
        });
    }

    /**
     * @return array<string, int|null>
     */
    public function normalizedOverrides(): array
    {
        $attributes = [];

        foreach (ApiOperation::all() as $operation) {
            foreach (['daily', 'monthly'] as $periodType) {
                $column = ApiOperation::settingsColumn(
                    $operation['provider'],
                    $operation['operation'],
                    $periodType,
                );

                if (! $this->has($column)) {
                    continue;
                }

                $value = $this->input($column);
                $attributes[$column] = ($value === null || $value === '') ? null : (int) $value;
            }
        }

        return $attributes;
    }
}
