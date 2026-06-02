<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\DetectCmsJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\AuditRunnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CmsDetectionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrichment_dispatches_detect_cms_for_gbp_only_when_url_added(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'gbp_only']);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => null,
            'audit_status' => 'complete',
        ]);

        $this->actingAs($user)
            ->patch("/prospects/{$prospect->id}", ['website_url' => 'https://example.com'])
            ->assertRedirect();

        $prospect->refresh();
        $this->assertNull($prospect->cms_detection);

        Queue::assertPushed(DetectCmsJob::class);
        Queue::assertNotPushed(AuditSiteJob::class);
    }

    public function test_audit_site_job_stores_cms_from_payload(): void
    {
        $search = Search::factory()->create(['scan_type' => 'combined']);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => 'pending',
        ]);

        $this->mock(AuditRunnerService::class, function ($mock) {
            $mock->shouldReceive('shouldSkip')->andReturn(false);
            $mock->shouldReceive('run')->andReturn([
                'url' => 'https://example.com',
                'violations' => [],
                'pass_count' => 0,
                'incomplete_count' => 0,
                'violation_screenshots' => [],
                'lighthouse' => null,
                'cms' => [
                    'platform' => 'shopify',
                    'version' => null,
                    'confidence' => 'high',
                    'signals' => [],
                    'detected_at' => now()->toIso8601String(),
                    'url' => 'https://example.com',
                ],
            ]);
        });

        $this->mock(\App\Services\ScreenshotStorageService::class, function ($mock) {
            $mock->shouldReceive('storeViolationScreenshots')->andReturn([]);
        });

        (new AuditSiteJob($prospect))->handle(
            app(AuditRunnerService::class),
            app(\App\Services\A11yScoringService::class),
            app(\App\Services\SearchStatusService::class),
            app(\App\Services\ScreenshotStorageService::class),
            app(\App\Services\AuditErrorRecorder::class),
        );

        $prospect->refresh();
        $this->assertSame('shopify', $prospect->cms_detection['platform']);
    }
}
