<?php

namespace Tests\Feature;

use App\Jobs\GenerateOutreachEmailJob;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Support\SearchQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class OutreachGenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_dispatches_only_for_prospects_with_reports(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        $withReport = Prospect::factory()->create(['search_id' => $search->id]);
        ProspectReport::factory()->for($withReport)->create();
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $withReport->id]);

        $without = Prospect::factory()->create(['search_id' => $search->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $without->id]);

        $response = $this->actingAs($user)->post('/outreach/generate', [
            'pitch_angle' => 'auto',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $this->assertCount(1, session('skipped'));

        Bus::assertDispatched(GenerateOutreachEmailJob::class, fn (GenerateOutreachEmailJob $job) => $job->queue === SearchQueue::NAME);
    }
}
