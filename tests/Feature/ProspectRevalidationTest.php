<?php

namespace Tests\Feature;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Jobs\CombineScoresJob;
use App\Jobs\QualifyProspectJob;
use App\Jobs\ValidateProspectJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\CombineScoresService;
use App\Services\ProspectValidatorService;
use App\Services\SearchStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProspectRevalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_combine_scores_dispatches_validate_job_with_qualification_chain(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => ScanType::GbpOnly]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Independent Dental',
            'gbp_score' => 70,
            'a11y_score' => 0,
            'combined_score' => 0,
            'audit_status' => AuditStatus::Pending,
        ]);

        $job = new CombineScoresJob($prospect);
        $job->handle(
            app(CombineScoresService::class),
            app(SearchStatusService::class),
        );

        Bus::assertDispatched(ValidateProspectJob::class, function (ValidateProspectJob $job): bool {
            return $job->chainQualification === true
                && $job->forceQualification === false;
        });
    }

    public function test_validate_job_skips_qualification_for_definitive_low_chance(): void
    {
        Bus::fake();

        $prospect = Prospect::factory()->create([
            'business_name' => 'Portman Dental Care Leeds',
            'qualification_status' => null,
            'combined_score' => 75,
        ]);

        $job = new ValidateProspectJob($prospect, chainQualification: true);
        $job->handle(app(ProspectValidatorService::class));

        Bus::assertNotDispatched(QualifyProspectJob::class);

        $prospect->refresh();
        $this->assertNotNull($prospect->validator_ran_at);
    }

    public function test_validate_job_chains_qualification_when_not_definitive_low_chance(): void
    {
        Bus::fake();

        $prospect = Prospect::factory()->create([
            'business_name' => 'Independent Dental',
            'qualification_status' => null,
            'combined_score' => 40,
        ]);

        $job = new ValidateProspectJob($prospect, chainQualification: true);
        $job->handle(app(ProspectValidatorService::class));

        Bus::assertDispatched(QualifyProspectJob::class);
    }

    public function test_force_qualify_bypasses_recent_qualification_cooldown(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'qualification_ran_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/prospects/{$prospect->id}/qualify", ['force' => true])
            ->assertAccepted();

        Bus::assertDispatched(QualifyProspectJob::class);
    }

    public function test_qualify_without_force_respects_recent_qualification_cooldown(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'qualification_ran_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/prospects/{$prospect->id}/qualify")
            ->assertOk();

        Bus::assertNotDispatched(QualifyProspectJob::class);
    }

    public function test_backfill_command_dry_run_lists_unvalidated_prospects(): void
    {
        Bus::fake();

        $included = Prospect::factory()->create([
            'business_name' => 'Needs Validation',
            'validator_ran_at' => null,
        ]);

        Prospect::factory()->create([
            'validator_ran_at' => now(),
        ]);

        $this->artisan('validation:backfill')
            ->expectsTable(
                ['prospect_id', 'business_name', 'search_id', 'qualification_status', 'combined_score', 'action'],
                [[
                    $included->id,
                    'Needs Validation',
                    $included->search_id,
                    $included->qualification_status ?? '—',
                    $included->combined_score ?? '—',
                    'validate',
                ]],
            )
            ->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_backfill_command_execute_dispatches_validate_jobs(): void
    {
        Bus::fake();

        $prospect = Prospect::factory()->create([
            'validator_ran_at' => null,
        ]);

        $this->artisan('validation:backfill', ['--execute' => true])
            ->assertSuccessful();

        Bus::assertDispatched(ValidateProspectJob::class, function (ValidateProspectJob $job) use ($prospect): bool {
            return $job->prospect->is($prospect) && $job->chainQualification === false;
        });
    }

    public function test_backfill_command_force_qualify_dispatches_qualify_jobs(): void
    {
        Bus::fake();

        $prospect = Prospect::factory()->create([
            'validator_ran_at' => null,
        ]);

        $this->artisan('validation:backfill', [
            '--execute' => true,
            '--force-qualify' => true,
        ])->assertSuccessful();

        Bus::assertDispatched(QualifyProspectJob::class, function (QualifyProspectJob $job) use ($prospect): bool {
            return $job->prospect->is($prospect);
        });

        Bus::assertNotDispatched(ValidateProspectJob::class);
    }

    public function test_backfill_excludes_prospects_with_validator_override(): void
    {
        Bus::fake();

        Prospect::factory()->create([
            'validator_ran_at' => null,
            'validator_override_status' => 'high_chance',
        ]);

        $this->artisan('validation:backfill')
            ->expectsOutputToContain('No unvalidated prospects found.')
            ->assertSuccessful();
    }
}
