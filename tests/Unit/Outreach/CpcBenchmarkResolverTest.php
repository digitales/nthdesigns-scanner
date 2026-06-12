<?php

namespace Tests\Unit\Outreach;

use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\Search;
use App\Services\Outreach\CpcBenchmarkResolver;
use Tests\TestCase;

class CpcBenchmarkResolverTest extends TestCase
{
    public function test_form_defaults_empty_when_no_search_cpc(): void
    {
        $search = new Search(['cpc_benchmark' => null]);
        $prospect = new Prospect;
        $prospect->setRelation('search', $search);
        $selection = new OutreachSelection;
        $selection->setRelation('prospect', $prospect);

        $defaults = app(CpcBenchmarkResolver::class)->formDefaults(collect([$selection]));

        $this->assertSame('', $defaults['value']);
        $this->assertFalse($defaults['mixed']);
        $this->assertFalse($defaults['from_search']);
    }

    public function test_form_defaults_prefills_when_all_searches_share_cpc(): void
    {
        $search = new Search(['cpc_benchmark' => 8.50, 'cpc_source' => 'manual']);
        $prospect = new Prospect;
        $prospect->setRelation('search', $search);
        $selection = new OutreachSelection;
        $selection->setRelation('prospect', $prospect);

        $defaults = app(CpcBenchmarkResolver::class)->formDefaults(collect([$selection]));

        $this->assertSame('8.50', $defaults['value']);
        $this->assertFalse($defaults['mixed']);
        $this->assertTrue($defaults['from_search']);
    }

    public function test_form_defaults_mixed_when_searches_differ(): void
    {
        $searchA = new Search(['cpc_benchmark' => 8.50]);
        $prospectA = new Prospect;
        $prospectA->setRelation('search', $searchA);
        $selectionA = new OutreachSelection;
        $selectionA->setRelation('prospect', $prospectA);

        $searchB = new Search(['cpc_benchmark' => 12.00]);
        $prospectB = new Prospect;
        $prospectB->setRelation('search', $searchB);
        $selectionB = new OutreachSelection;
        $selectionB->setRelation('prospect', $prospectB);

        $defaults = app(CpcBenchmarkResolver::class)->formDefaults(collect([$selectionA, $selectionB]));

        $this->assertSame('', $defaults['value']);
        $this->assertTrue($defaults['mixed']);
        $this->assertTrue($defaults['from_search']);
    }

    public function test_resolve_uses_search_cpc_when_no_override(): void
    {
        $search = new Search([
            'cpc_benchmark' => 9.25,
            'cpc_source' => 'manual',
        ]);
        $prospect = new Prospect;
        $prospect->setRelation('search', $search);

        $resolved = app(CpcBenchmarkResolver::class)->resolveForProspect($prospect, []);

        $this->assertSame(9.25, $resolved['cpc_benchmark']);
        $this->assertSame('manual', $resolved['cpc_source']);
    }

    public function test_resolve_prefers_outreach_override(): void
    {
        $search = new Search(['cpc_benchmark' => 9.25, 'cpc_source' => 'manual']);
        $prospect = new Prospect;
        $prospect->setRelation('search', $search);

        $resolved = app(CpcBenchmarkResolver::class)->resolveForProspect($prospect, [
            'cpc_benchmark' => 11.00,
            'cpc_source' => 'outreach_override',
        ]);

        $this->assertSame(11.0, $resolved['cpc_benchmark']);
        $this->assertSame('outreach_override', $resolved['cpc_source']);
    }
}
