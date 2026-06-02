<?php

namespace Tests\Feature;

use App\Jobs\DetectCmsJob;
use App\Models\Prospect;
use App\Services\CmsDetectionRunnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectCmsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_redetects_when_cms_already_stored_for_url(): void
    {
        $prospect = Prospect::factory()->create([
            'website_url' => 'https://example.com',
            'cms_detection' => [
                'platform' => 'unknown',
                'url' => 'https://example.com',
            ],
        ]);

        $this->mock(CmsDetectionRunnerService::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->with('https://example.com')
                ->andReturn([
                    'platform' => 'wix',
                    'version' => null,
                    'confidence' => 'high',
                    'signals' => [],
                    'detected_at' => now()->toIso8601String(),
                    'url' => 'https://example.com/',
                ]);
        });

        (new DetectCmsJob($prospect, force: true))->handle(app(CmsDetectionRunnerService::class));

        $prospect->refresh();
        $this->assertSame('wix', $prospect->cms_detection['platform']);
    }

    public function test_persists_cms_detection_on_prospect(): void
    {
        $prospect = Prospect::factory()->create([
            'website_url' => 'https://example.com',
            'cms_detection' => null,
        ]);

        $this->mock(CmsDetectionRunnerService::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->with('https://example.com')
                ->andReturn([
                    'platform' => 'wordpress',
                    'version' => '6.4.2',
                    'confidence' => 'high',
                    'signals' => [],
                    'detected_at' => now()->toIso8601String(),
                    'url' => 'https://example.com',
                ]);
        });

        (new DetectCmsJob($prospect))->handle(app(CmsDetectionRunnerService::class));

        $prospect->refresh();
        $this->assertSame('wordpress', $prospect->cms_detection['platform']);
    }

    public function test_skips_when_cms_already_matches_url(): void
    {
        $prospect = Prospect::factory()->create([
            'website_url' => 'https://example.com',
            'cms_detection' => [
                'platform' => 'wordpress',
                'url' => 'https://example.com/',
            ],
        ]);

        $this->mock(CmsDetectionRunnerService::class, function ($mock) {
            $mock->shouldNotReceive('run');
        });

        (new DetectCmsJob($prospect))->handle(app(CmsDetectionRunnerService::class));
    }

    public function test_survives_queue_round_trip_when_force_is_false(): void
    {
        $prospect = Prospect::factory()->create([
            'website_url' => 'https://example.com',
            'cms_detection' => null,
        ]);

        $this->mock(CmsDetectionRunnerService::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->with('https://example.com')
                ->andReturn([
                    'platform' => 'wordpress',
                    'version' => null,
                    'confidence' => 'high',
                    'signals' => [],
                    'detected_at' => now()->toIso8601String(),
                    'url' => 'https://example.com',
                ]);
        });

        $job = unserialize(serialize(new DetectCmsJob($prospect)));

        $job->handle(app(CmsDetectionRunnerService::class));

        $prospect->refresh();
        $this->assertSame('wordpress', $prospect->cms_detection['platform']);
    }
}
