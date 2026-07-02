<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WarmupMailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarmupMailbox>
 */
class WarmupMailboxFactory extends Factory
{
    protected $model = WarmupMailbox::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'provider' => 'fastmail',
            'imap_host' => 'imap.fastmail.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.fastmail.com',
            'smtp_port' => 587,
            'username' => fake()->userName(),
            'password_encrypted' => 'test-password',
            'is_outreach_mailbox' => false,
            'is_seed_mailbox' => true,
            'is_pool_participant' => true,
            'warmup_enabled' => false,
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
            'send_window_start' => '08:00:00',
            'send_window_end' => '18:00:00',
            'send_on_weekends' => true,
            'status' => 'pending',
        ];
    }

    public function outreach(): static
    {
        return $this->state(fn () => [
            'is_outreach_mailbox' => true,
            'is_seed_mailbox' => false,
        ]);
    }

    public function warming(): static
    {
        return $this->state(fn () => [
            'warmup_enabled' => true,
            'warmup_started_at' => now()->subDays(3),
            'status' => 'warming',
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'is_outreach_mailbox' => true,
            'is_seed_mailbox' => false,
            'warmup_enabled' => true,
            'warmup_started_at' => now()->subDays(14),
            'warmup_ramp_days' => 14,
            'status' => 'ready',
            'deliverability_score' => 85,
        ]);
    }
}
