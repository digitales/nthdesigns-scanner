<?php

namespace Tests\Feature;

use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Queries\ProspectListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectListQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_warm_scope_requires_viewed_report_sent_outreach_and_no_response(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        $warm = Prospect::factory()->create(['search_id' => $search->id, 'business_name' => 'Warm Co']);
        ProspectReport::factory()->create([
            'prospect_id' => $warm->id,
            'viewed_at' => now(),
        ]);
        OutreachEmail::factory()->create([
            'prospect_id' => $warm->id,
            'user_id' => $user->id,
            'sent_at' => now(),
            'response_received' => false,
        ]);

        $cold = Prospect::factory()->create(['search_id' => $search->id, 'business_name' => 'Cold Co']);
        ProspectReport::factory()->create(['prospect_id' => $cold->id, 'viewed_at' => now()]);

        $responded = Prospect::factory()->create(['search_id' => $search->id, 'business_name' => 'Done Co']);
        ProspectReport::factory()->create(['prospect_id' => $responded->id, 'viewed_at' => now()]);
        OutreachEmail::factory()->create([
            'prospect_id' => $responded->id,
            'user_id' => $user->id,
            'sent_at' => now(),
            'response_received' => true,
        ]);

        $ids = (new ProspectListQuery($user))
            ->apply(['warm' => true])
            ->query()
            ->pluck('business_name')
            ->all();

        $this->assertSame(['Warm Co'], $ids);
    }

    public function test_min_score_filter(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        Prospect::factory()->create(['search_id' => $search->id, 'combined_score' => 90]);
        Prospect::factory()->create(['search_id' => $search->id, 'combined_score' => 40]);

        $count = (new ProspectListQuery($user))
            ->apply(['min_score' => 80])
            ->query()
            ->count();

        $this->assertSame(1, $count);
    }
}
