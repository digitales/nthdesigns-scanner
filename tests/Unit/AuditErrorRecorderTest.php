<?php

namespace Tests\Unit;

use App\Models\AuditJob;
use App\Models\AuditJobErrorDetail;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\AuditErrorRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditErrorRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_uses_first_line_and_caps_length(): void
    {
        $recorder = app(AuditErrorRecorder::class);

        $this->assertSame('Timeout exceeded', $recorder->summarize("Timeout exceeded\nCall log:\n  - navigating"));
        $this->assertSame('Audit failed', $recorder->summarize(''));
        $this->assertSame(255, strlen($recorder->summarize(str_repeat('x', 300))));
    }

    public function test_record_failure_stores_summary_and_detail(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);
        $job = AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        $full = "page.goto: Timeout\nCall log:\n  - waiting";

        app(AuditErrorRecorder::class)->recordFailure($job, $full);

        $job->refresh();
        $this->assertSame('page.goto: Timeout', $job->error_message);
        $this->assertDatabaseHas('audit_job_error_details', [
            'audit_job_id' => $job->id,
            'body' => $full,
        ]);
    }

    public function test_format_throwable_includes_previous_messages(): void
    {
        $inner = new RuntimeException('Connection reset');
        $outer = new RuntimeException('Audit service failed', 0, $inner);

        $formatted = app(AuditErrorRecorder::class)->formatThrowable($outer);

        $this->assertStringContainsString('Audit service failed', $formatted);
        $this->assertStringContainsString('Connection reset', $formatted);
    }

    public function test_format_process_output_combines_stderr_and_stdout(): void
    {
        $formatted = app(AuditErrorRecorder::class)->formatProcessOutput(
            'stderr line',
            'stdout json',
        );

        $this->assertStringContainsString('stderr line', $formatted);
        $this->assertStringContainsString('stdout json', $formatted);
    }

    public function test_record_failure_truncates_oversized_body(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);
        $job = AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'failed',
        ]);

        app(AuditErrorRecorder::class)->recordFailure($job, str_repeat('z', 40_000));

        $detail = AuditJobErrorDetail::query()->where('audit_job_id', $job->id)->first();
        $this->assertNotNull($detail);
        $this->assertSame(32_768, strlen($detail->body));
    }
}
