<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\CombineScoresJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\A11yScoringService;
use App\Services\AuditErrorRecorder;
use App\Services\AuditRunnerService;
use App\Services\CmsDetectionRunnerService;
use App\Services\ScreenshotStorageService;
use App\Services\SearchStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AuditSiteJobTest extends TestCase
{
    use RefreshDatabase;

    private function pendingProspect(): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
        ]);

        return Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => 'pending',
        ]);
    }

    public function test_defers_failed_status_until_tries_exhausted(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(AuditRunnerService::class, function ($mock) {
            $mock->shouldReceive('shouldSkip')->andReturn(false);
            $mock->shouldReceive('run')->andThrow(new RuntimeException('timeout'));
        });

        $job = $this->getMockBuilder(AuditSiteJob::class)
            ->setConstructorArgs([$prospect])
            ->onlyMethods(['attempts'])
            ->getMock();
        $job->method('attempts')->willReturn(1);

        try {
            $job->handle(
                app(AuditRunnerService::class),
                app(A11yScoringService::class),
                app(SearchStatusService::class),
                app(ScreenshotStorageService::class),
                app(AuditErrorRecorder::class),
                app(CmsDetectionRunnerService::class),
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('timeout', $e->getMessage());
        }

        $this->assertSame('pending', $prospect->fresh()->audit_status);
    }

    public function test_marks_failed_on_final_attempt(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(AuditRunnerService::class, function ($mock) {
            $mock->shouldReceive('shouldSkip')->andReturn(false);
            $mock->shouldReceive('run')->andThrow(new RuntimeException('timeout'));
        });

        $job = $this->getMockBuilder(AuditSiteJob::class)
            ->setConstructorArgs([$prospect])
            ->onlyMethods(['attempts'])
            ->getMock();
        $job->method('attempts')->willReturn(2);

        try {
            $job->handle(
                app(AuditRunnerService::class),
                app(A11yScoringService::class),
                app(SearchStatusService::class),
                app(ScreenshotStorageService::class),
                app(AuditErrorRecorder::class),
                app(CmsDetectionRunnerService::class),
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('failed', $prospect->fresh()->audit_status);
    }
}
