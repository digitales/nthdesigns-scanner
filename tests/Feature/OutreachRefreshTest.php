<?php

namespace Tests\Feature;

use App\Jobs\GenerateOutreachEmailJob;
use App\Jobs\GenerateProspectReportJob;
use App\Jobs\RegenerateOutreachForProspectJob;
use App\Models\OutreachEmail;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class OutreachRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_queues_report_and_outreach_chain_for_eligible_prospect(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => 'owner@example.com',
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $prospect->id]);

        OutreachEmail::factory()->create([
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'sent_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/outreach/refresh', [
            'prospect_ids' => [$prospect->id],
            'pitch_angle' => 'auto',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(0, OutreachEmail::where('prospect_id', $prospect->id)->count());

        Bus::assertChained([
            GenerateProspectReportJob::class,
            RegenerateOutreachForProspectJob::class,
        ]);
    }

    public function test_refresh_skips_prospect_not_in_queue(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $response = $this->actingAs($user)->post('/outreach/refresh', [
            'prospect_ids' => [$prospect->id],
            'pitch_angle' => 'auto',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('success');
        $this->assertCount(1, session('skipped'));

        Bus::assertNothingDispatched();
    }

    public function test_refresh_skips_prospect_with_sent_outreach(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $prospect->id]);

        OutreachEmail::factory()->create([
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/outreach/refresh', [
            'prospect_ids' => [$prospect->id],
            'pitch_angle' => 'auto',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('success');
        $this->assertStringContainsString('already sent', session('skipped')[0]);

        Bus::assertNothingDispatched();
    }

    public function test_refresh_skips_prospect_without_report(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $prospect->id]);

        $response = $this->actingAs($user)->post('/outreach/refresh', [
            'prospect_ids' => [$prospect->id],
            'pitch_angle' => 'auto',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('no report', session('skipped')[0]);

        Bus::assertNothingDispatched();
    }

    public function test_refresh_dispatches_valid_and_skips_invalid_in_mixed_batch(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        $eligible = Prospect::factory()->create(['search_id' => $search->id, 'email' => 'a@example.com']);
        ProspectReport::factory()->create(['prospect_id' => $eligible->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $eligible->id]);

        $sent = Prospect::factory()->create(['search_id' => $search->id]);
        ProspectReport::factory()->create(['prospect_id' => $sent->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $sent->id]);
        OutreachEmail::factory()->create([
            'prospect_id' => $sent->id,
            'user_id' => $user->id,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/outreach/refresh', [
            'prospect_ids' => [$eligible->id, $sent->id],
            'pitch_angle' => 'auto',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertCount(1, session('skipped'));

        Bus::assertChained([
            GenerateProspectReportJob::class,
            RegenerateOutreachForProspectJob::class,
        ]);
    }
}
