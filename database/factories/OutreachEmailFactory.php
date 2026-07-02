<?php

namespace Database\Factories;

use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OutreachEmail> */
class OutreachEmailFactory extends Factory
{
    protected $model = OutreachEmail::class;

    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::factory(),
            'user_id' => User::factory(),
            'pitch_angle' => 'gbp',
            'channel' => 'email',
            'subject_line' => 'Quick question about your online presence',
            'email_body' => 'Hello, we noticed some opportunities to improve your visibility.',
            'generated_subject' => 'Quick question about your online presence',
            'generated_body' => 'Hello, we noticed some opportunities to improve your visibility.',
            'response_received' => false,
        ];
    }
}
