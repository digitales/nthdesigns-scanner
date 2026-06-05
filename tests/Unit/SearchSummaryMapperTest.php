<?php

namespace Tests\Unit;

use App\Http\Resources\SearchSummaryMapper;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSummaryMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_uses_relative_created_at_by_default(): void
    {
        $search = Search::factory()->for(User::factory())->create();

        $summary = SearchSummaryMapper::format($search);

        $this->assertSame($search->id, $summary['id']);
        $this->assertSame($search->niche, $summary['niche']);
        $this->assertSame($search->created_at->diffForHumans(), $summary['created_at']);
    }

    public function test_format_uses_iso_created_at_for_mcp_surface(): void
    {
        $search = Search::factory()->for(User::factory())->create();

        $summary = SearchSummaryMapper::format($search, 'iso');

        $this->assertSame($search->created_at->toIso8601String(), $summary['created_at']);
    }

    public function test_detail_includes_country_and_scan_type(): void
    {
        $search = Search::factory()->for(User::factory())->create([
            'country' => 'GB',
            'scan_type' => 'combined',
        ]);

        $detail = SearchSummaryMapper::detail($search);

        $this->assertSame('GB', $detail['country']);
        $this->assertSame('combined', $detail['scan_type']);
        $this->assertSame($search->created_at->toIso8601String(), $detail['created_at']);
    }
}
