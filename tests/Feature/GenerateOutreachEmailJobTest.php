<?php

namespace Tests\Feature;

use App\Enums\OutreachChannel;
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

    private function runJob(GenerateOutreachEmailJob $job): void
    {
        $job->handle(
            app(OutreachEmailGeneratorService::class),
            app(\App\Services\Outreach\OutreachFormMessageGeneratorService::class),
            app(\App\Services\Outreach\OutreachLinkedInTemplateService::class),
            app(\App\Services\Outreach\CpcBenchmarkResolver::class),
            app(\App\Services\ProspectUnsubscribeService::class),
        );
    }

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

        $this->runJob(new GenerateOutreachEmailJob($prospect, $user, ['pitch_angle' => 'auto']));

        $this->assertSame(1, OutreachEmail::where('prospect_id', $prospect->id)->count());
        $this->assertNotNull($report->id);
    }

    public function test_force_regenerates_when_matching_email_already_exists(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'dominant_angle' => 'gbp',
            'email' => 'owner@example.com',
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        OutreachEmail::factory()->create([
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'pitch_angle' => 'gbp',
            'subject_line' => 'Old subject',
        ]);

        $this->mock(OutreachEmailGeneratorService::class, function ($mock) {
            $mock->shouldReceive('resolvedPitchAngle')->andReturn('gbp');
            $mock->shouldReceive('generate')->once()->andReturn([
                'subject_line' => 'Fresh subject',
                'email_body' => 'Updated body',
                'model_used' => 'claude-test',
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'pitch_angle' => 'gbp',
            ]);
        });

        $this->runJob(new GenerateOutreachEmailJob($prospect, $user, ['pitch_angle' => 'auto', 'force' => true]));

        $this->assertSame(2, OutreachEmail::where('prospect_id', $prospect->id)->count());
        $this->assertDatabaseHas('outreach_emails', [
            'prospect_id' => $prospect->id,
            'subject_line' => 'Fresh subject',
        ]);
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

        $this->runJob(new GenerateOutreachEmailJob($prospect, $user, ['pitch_angle' => 'gbp']));

        $this->assertDatabaseHas('outreach_emails', [
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'pitch_angle' => 'gbp',
            'channel' => 'email',
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

        $this->runJob(new GenerateOutreachEmailJob($prospect, $user, ['pitch_angle' => 'gbp']));

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

        $this->runJob(new GenerateOutreachEmailJob($prospect, $user));

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

        $this->runJob(new GenerateOutreachEmailJob($prospect, $user));

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

        $this->runJob(new GenerateOutreachEmailJob($prospect, $user, ['pitch_angle' => 'gbp']));

        $email = OutreachEmail::first();
        $this->assertStringContainsString('unsubscribe here:', $email->email_body);
        $this->assertStringContainsString('/unsubscribe?', $email->email_body);
    }

    public function test_creates_linkedin_message_without_llm(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => null,
            'linkedin_url' => 'https://linkedin.com/company/acme',
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $this->mock(OutreachEmailGeneratorService::class, function ($mock) {
            $mock->shouldReceive('resolvedPitchAngle')->andReturn('gbp');
            $mock->shouldNotReceive('generate');
        });

        $this->runJob(new GenerateOutreachEmailJob(
            $prospect,
            $user,
            ['pitch_angle' => 'gbp', 'agency_name' => 'nthdesigns'],
            OutreachChannel::Linkedin,
        ));

        $this->assertDatabaseHas('outreach_emails', [
            'prospect_id' => $prospect->id,
            'channel' => 'linkedin',
            'model_used' => 'template',
        ]);
    }
}
