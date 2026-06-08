<?php

namespace Tests\Unit\Enums;

use App\Enums\ScanType;
use App\Enums\SearchSource;
use App\Enums\SearchStatus;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchModelCastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_status_and_scan_type_cast_to_enums(): void
    {
        $search = Search::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'auditing',
            'scan_type' => 'combined',
            'source' => 'discovery',
        ]);

        $search->refresh();

        $this->assertInstanceOf(SearchStatus::class, $search->status);
        $this->assertSame(SearchStatus::Auditing, $search->status);
        $this->assertInstanceOf(ScanType::class, $search->scan_type);
        $this->assertSame(ScanType::Combined, $search->scan_type);
        $this->assertInstanceOf(SearchSource::class, $search->source);
        $this->assertSame(SearchSource::Discovery, $search->source);
    }
}
