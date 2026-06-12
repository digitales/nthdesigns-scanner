<?php

namespace Tests\Feature;

use App\Jobs\GenerateOutreachEmailJob;
use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Services\OutreachEmailGeneratorService;
use App\Support\SearchQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateOutreachEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_to_searches_queue(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $job = new GenerateOutreachEmailJob($prospect, $user);

        $this->assertSame(SearchQueue::NAME, Queue::resolveQueueFromQueueRoute($job));
        $this->assertSame(SearchQueue::connection(), Queue::resolveConnectionFromQueueRoute($job));
    }

    public function test_skips_when_matching_email_already_exists(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'dominant_angle' => 'gbp',
            'email' => 'owner@example.com',
        ]);
        $report = ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        OutreachEmail::factory()->create([
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'pitch_angle' => 'gbp',
        ]);

        $this->mock(OutreachEmailGeneratorService::class, function ($mock) {
            $mock->shouldReceive('resolvedPitchAngle')->andReturn('gbp');
            $mock->shouldNotReceive('generate');
        });

        (new GenerateOutreachEmailJob($prospect, $user, ['pitch_angle' => 'auto']))->handle(
            app(OutreachEmailGeneratorService::class),
            app(\App\Services\Outreach\CpcBenchmarkResolver::class),
            app(\App\Services\ProspectUnsubscribeService::class),
        );

        $this->assertSame(1, OutreachEmail::where('prospect_id', $prospect->id)->count());
        $this->assertNotNull($report->id);
    }

    public function test_creates_email_when_no_match_exists(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => 'owner@example.com',
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $this->mock(OutreachEmailGeneratorService::class, function ($mock) {
            $mock->shouldReceive('resolvedPitchAngle')->andReturn('gbp');
            $mock->shouldReceive('generate')->once()->andReturn([
                'subject_line' => 'Quick question',
                'email_body' => 'Hello there',
                'model_used' => 'claude-test',
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'pitch_angle' => 'gbp',
            ]);
        });

        (new GenerateOutreachEmailJob($prospect, $user, ['pitch_angle' => 'gbp']))->handle(
            app(OutreachEmailGeneratorService::class),
            app(\App\Services\Outreach\CpcBenchmarkResolver::class),
            app(\App\Services\ProspectUnsubscribeService::class),
        );

        $this->assertDatabaseHas('outreach_emails', [
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'pitch_angle' => 'gbp',
            'subject_line' => 'Quick question',
        ]);
    }

    public function test_persists_cpc_from_search_when_no_override(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'cpc_benchmark' => 8.50,
            'cpc_source' => 'manual',
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => 'owner@example.com',
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $this->mock(OutreachEmailGeneratorService::class, function ($mock) {
            $mock->shouldReceive('resolvedPitchAngle')->andReturn('gbp');
            $mock->shouldReceive('generate')->once()->andReturn([
                'subject_line' => 'Quick question',
                'email_body' => 'Hello there',
                'model_used' => 'claude-test',
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'pitch_angle' => 'gbp',
            ]);
        });

        (new GenerateOutreachEmailJob($prospect, $user, ['pitch_angle' => 'gbp']))->handle(
            app(OutreachEmailGeneratorService::class),
            app(\App\Services\Outreach\CpcBenchmarkResolver::class),
            app(\App\Services\ProspectUnsubscribeService::class),
        );

        $this->assertDatabaseHas('outreach_emails', [
            'prospect_id' => $prospect->id,
            'cpc_benchmark' => '8.50',
            'cpc_source' => 'manual',
        ]);
    }

    public function test_skips_when_prospect_has_no_email(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => null,
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $this->mock(OutreachEmailGeneratorService::class, function ($mock) {
            $mock->shouldNotReceive('generate');
        });

        (new GenerateOutreachEmailJob($prospect, $user))->handle(
            app(OutreachEmailGeneratorService::class),
            app(\App\Services\Outreach\CpcBenchmarkResolver::class),
            app(\App\Services\ProspectUnsubscribeService::class),
        );

        $this->assertSame(0, OutreachEmail::count());
    }

    public function test_skips_when_email_is_suppressed(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => 'owner@example.com',
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        \App\Models\SuppressedEmail::create([
            'user_id' => $user->id,
            'email' => 'owner@example.com',
            'source' => \App\Enums\SuppressionSource::Operator,
        ]);

        $this->mock(OutreachEmailGeneratorService::class, function ($mock) {
            $mock->shouldNotReceive('generate');
        });

        (new GenerateOutreachEmailJob($prospect, $user))->handle(
            app(OutreachEmailGeneratorService::class),
            app(\App\Services\Outreach\CpcBenchmarkResolver::class),
            app(\App\Services\ProspectUnsubscribeService::class),
        );

        $this->assertSame(0, OutreachEmail::count());
    }

    public function test_appends_unsubscribe_footer_to_generated_body(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => 'owner@example.com',
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $this->mock(OutreachEmailGeneratorService::class, function ($mock) {
            $mock->shouldReceive('resolvedPitchAngle')->andReturn('gbp');
            $mock->shouldReceive('generate')->once()->andReturn([
                'subject_line' => 'Quick question',
                'email_body' => 'Hello there',
                'model_used' => 'claude-test',
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'pitch_angle' => 'gbp',
            ]);
        });

        (new GenerateOutreachEmailJob($prospect, $user, ['pitch_angle' => 'gbp']))->handle(
            app(OutreachEmailGeneratorService::class),
            app(\App\Services\Outreach\CpcBenchmarkResolver::class),
            app(\App\Services\ProspectUnsubscribeService::class),
        );

        $email = OutreachEmail::first();
        $this->assertStringContainsString('unsubscribe here:', $email->email_body);
        $this->assertStringContainsString('/unsubscribe?', $email->email_body);
    }
}
