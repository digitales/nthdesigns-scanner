<?php

namespace Tests\Feature;

use App\Jobs\CombineScoresJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AutoGenerateReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_combine_scores_dispatches_report_generation(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'gbp_only']);
        $prospect = Prospect::factory()->create([
            'search_id'      => $search->id,
            'gbp_score'      => 70,
            'a11y_score'     => 0,
            'combined_score' => 0,
            'audit_status'   => 'pending',
        ]);

        $job = new CombineScoresJob($prospect);
        $job->handle(
            app(\App\Services\CombineScoresService::class),
            app(\App\Services\SearchStatusService::class),
        );

        Bus::assertDispatched(GenerateProspectReportJob::class, 1);

        $prospect->refresh();
        $this->assertSame('complete', $prospect->audit_status);
        $this->assertSame(70, $prospect->combined_score);
    }
}
