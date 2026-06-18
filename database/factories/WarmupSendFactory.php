<?php

namespace Database\Factories;

use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarmupSend>
 */
class WarmupSendFactory extends Factory
{
    protected $model = WarmupSend::class;

    public function definition(): array
    {
        return [
            'from_mailbox_id' => WarmupMailbox::factory(),
            'to_mailbox_id' => WarmupMailbox::factory(),
            'message_id' => fake()->uuid().'@warmup.test',
            'subject' => fake()->sentence(3),
            'sent_at' => now(),
            'status' => 'sent',
        ];
    }

    public function opened(): static
    {
        return $this->state(fn () => [
            'opened_at' => now(),
            'status' => 'opened',
        ]);
    }

    public function replied(): static
    {
        return $this->state(fn () => [
            'opened_at' => now()->subHour(),
            'replied_at' => now(),
            'status' => 'replied',
        ]);
    }

    public function rescued(): static
    {
        return $this->state(fn () => [
            'rescued_from_spam_at' => now(),
            'status' => 'rescued',
        ]);
    }
}
