<?php

namespace Database\Factories;

use App\Models\Search;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Search> */
class SearchFactory extends Factory
{
    protected $model = Search::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'niche'       => 'dental practice',
            'city'        => 'Birmingham',
            'country'     => 'GB',
            'scan_type'   => 'combined',
            'status'      => 'complete',
            'total_found' => 1,
        ];
    }
}
