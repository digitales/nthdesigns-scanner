<?php

namespace Database\Factories;

use App\Models\Prospect;
use App\Models\ProspectReport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProspectReport> */
class ProspectReportFactory extends Factory
{
    protected $model = ProspectReport::class;

    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::factory(),
            'token'       => (string) Str::uuid(),
            'view_count'  => 0,
        ];
    }
}
