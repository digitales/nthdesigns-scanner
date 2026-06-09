<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReportSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_report_uses_stored_grade_after_prospect_rescore(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'combined_score' => 85,
        ]);

        $report = ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'report_data' => [
                'grade' => 'C',
                'grade_label' => 'Several gaps to address',
                'combined_score' => 55,
                'performance_score' => 42,
                'prospect' => ['business_name' => $prospect->business_name],
                'violation_summary' => ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0, 'total' => 0],
                'top_violations' => [],
                'lighthouse' => [],
            ],
        ]);

        $prospect->update(['combined_score' => 95]);

        $this->get('/r/'.$report->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Report/Public')
                ->where('report.grade', 'C')
                ->where('report.grade_label', 'Several gaps to address')
                ->where('report.combined_score', 55)
                ->where('report.performance_score', 42)
                ->where('report.report_context', null));
    }

    public function test_public_report_includes_report_context_when_present(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'combined_score' => 80,
        ]);

        $report = ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'report_data' => [
                'grade' => 'C',
                'grade_label' => 'Several gaps to address',
                'combined_score' => 80,
                'performance_score' => 42,
                'prospect' => ['business_name' => $prospect->business_name],
                'violation_summary' => ['critical' => 2, 'serious' => 3, 'moderate' => 0, 'minor' => 0, 'total' => 5],
                'top_violations' => [],
                'lighthouse' => [],
                'report_context' => [
                    'headline' => 'Your site has 2 issues that may stop visitors from completing a booking or enquiry',
                    'severity_labels' => [
                        ['level' => 'critical', 'count' => 2, 'label' => '2 likely blocking enquiries'],
                    ],
                    'dimensions' => [],
                    'lighthouse_captions' => [],
                ],
            ],
        ]);

        $this->get('/r/'.$report->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Report/Public')
                ->where('report.report_context.headline', 'Your site has 2 issues that may stop visitors from completing a booking or enquiry'));
    }
}
