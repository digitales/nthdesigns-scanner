<?php

namespace Tests\Unit;

use App\Support\ScannerJobContext;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

class ScannerJobContextTest extends TestCase
{
    public function test_add_merges_job_name_and_filters_empty_values(): void
    {
        Context::flush();

        ScannerJobContext::add('App\\Jobs\\AuditSiteJob', [
            'prospect_id' => 42,
            'search_id' => null,
        ]);

        $this->assertSame('App\\Jobs\\AuditSiteJob', Context::get('job'));
        $this->assertSame(42, Context::get('prospect_id'));
        $this->assertNull(Context::get('search_id'));
    }
}
