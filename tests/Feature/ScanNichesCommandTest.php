<?php

namespace Tests\Feature;

use App\Jobs\ScanNicheJob;
use App\Support\ScrapingQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScanNichesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_per_niche_and_city(): void
    {
        Queue::fake();

        $this->travelTo(now('Europe/London')->startOfDay());

        $this->artisan('niches:scan', [
            '--cities' => 'Birmingham',
            '--niches' => 'Dental Practice,Plumber',
            '--sample' => 3,
        ])->expectsOutputToContain('Dispatched 2')
            ->assertExitCode(0);

        Queue::assertPushed(ScanNicheJob::class, 2);

        Queue::assertPushed(ScanNicheJob::class, function (ScanNicheJob $job) {
            return $job->niche === 'Dental Practice'
                && $job->city === 'Birmingham'
                && $job->sample === 3
                && $job->queue === ScrapingQueue::NAME;
        });
    }
}
