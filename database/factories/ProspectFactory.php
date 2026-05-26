<?php

namespace Database\Factories;

use App\Models\Prospect;
use App\Models\Search;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Prospect> */
class ProspectFactory extends Factory
{
    protected $model = Prospect::class;

    public function definition(): array
    {
        return [
            'search_id'      => Search::factory(),
            'place_id'       => fake()->uuid(),
            'business_name'  => fake()->company(),
            'combined_score' => 75,
            'gbp_score'      => 60,
            'a11y_score'     => 80,
            'dominant_angle' => 'accessibility',
            'audit_status'   => 'complete',
            'review_count'   => 5,
            'photo_count'    => 2,
        ];
    }
}
