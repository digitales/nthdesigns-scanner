<?php

namespace Tests\Unit\Outreach;

use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Services\Outreach\OutreachQueueLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OutreachQueueLoaderStatusTest extends TestCase
{
    use RefreshDatabase;

    private OutreachQueueLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = app(OutreachQueueLoader::class);
    }

    public function test_outreach_status_none_when_no_emails(): void
    {
        $this->assertSame('none', $this->loader->outreachStatus(collect()));
    }

    public function test_outreach_status_drafted_when_unsent_emails_exist(): void
    {
        $email = OutreachEmail::factory()->make(['sent_at' => null]);

        $this->assertSame('drafted', $this->loader->outreachStatus(collect([$email])));
    }

    public function test_outreach_status_sent_when_any_email_sent(): void
    {
        $draft = OutreachEmail::factory()->make(['sent_at' => null]);
        $sent = OutreachEmail::factory()->make(['sent_at' => now()]);

        $this->assertSame('sent', $this->loader->outreachStatus(collect([$draft, $sent])));
    }

    public function test_refresh_eligible_requires_report_and_unsent_status(): void
    {
        $report = ProspectReport::factory()->make();

        $this->assertTrue($this->loader->refreshEligible($report, 'none'));
        $this->assertTrue($this->loader->refreshEligible($report, 'drafted'));
        $this->assertFalse($this->loader->refreshEligible($report, 'sent'));
        $this->assertFalse($this->loader->refreshEligible(null, 'none'));
    }

    public function test_report_is_stale_after_thirty_days(): void
    {
        $this->assertTrue($this->loader->reportIsStale(Carbon::now()->subDays(31)));
        $this->assertFalse($this->loader->reportIsStale(Carbon::now()->subDays(10)));
    }

    public function test_report_generated_at_prefers_report_data_timestamp(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        $report = ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'report_data' => ['generated_at' => '2026-01-15T10:00:00+00:00'],
            'created_at' => now()->subYear(),
        ]);

        $generatedAt = $this->loader->reportGeneratedAt($report);

        $this->assertSame('15 Jan', $generatedAt?->format('j M'));
    }
}
