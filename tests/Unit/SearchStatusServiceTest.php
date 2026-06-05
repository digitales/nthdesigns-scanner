<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\SearchStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private SearchStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SearchStatusService::class);
    }

    public function test_marks_search_auditing_when_prospects_pending_audit(): void
    {
        $search = Search::factory()->for(User::factory())->create([
            'status' => 'pending',
            'total_found' => 2,
        ]);

        Prospect::factory()->count(2)->create([
            'search_id' => $search->id,
            'audit_status' => 'pending',
        ]);

        $this->service->refresh($search);

        $this->assertSame('auditing', $search->fresh()->status);
    }

    public function test_marks_search_complete_when_all_audits_finished(): void
    {
        $search = Search::factory()->for(User::factory())->create([
            'status' => 'auditing',
            'total_found' => 2,
        ]);

        Prospect::factory()->create([
            'search_id' => $search->id,
            'audit_status' => 'complete',
        ]);
        Prospect::factory()->create([
            'search_id' => $search->id,
            'audit_status' => 'failed',
        ]);

        $this->service->refresh($search);

        $this->assertSame('complete', $search->fresh()->status);
    }

    public function test_leaves_search_unchanged_until_all_prospects_exist(): void
    {
        $search = Search::factory()->for(User::factory())->create([
            'status' => 'pending',
            'total_found' => 2,
        ]);

        Prospect::factory()->create([
            'search_id' => $search->id,
            'audit_status' => 'complete',
        ]);

        $this->service->refresh($search);

        $this->assertSame('pending', $search->fresh()->status);
    }
}
