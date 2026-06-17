<?php

namespace Tests\Unit\Outreach;

use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\Outreach\OutreachQueueLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachQueueLoaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_emails_returns_one_per_prospect(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => 'owner@example.com',
        ]);

        OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
            'subject_line' => 'Older',
            'created_at' => now()->subDay(),
        ]);

        $latest = OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
            'subject_line' => 'Latest',
        ]);

        $loader = app(OutreachQueueLoader::class);
        $map = $loader->latestEmailsByProspect($user, collect([$prospect->id]));

        $this->assertCount(1, $map[$prospect->id]);
        $this->assertSame('Latest', $map[$prospect->id][0]['subject_line']);
        $this->assertSame($latest->id, $map[$prospect->id][0]['id']);
    }
}
