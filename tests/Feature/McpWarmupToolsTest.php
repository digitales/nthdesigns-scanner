<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WarmupAlert;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\OAuthMcpJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpWarmupToolsTest extends TestCase
{
    use RefreshDatabase;

    private function bearerFor(User $user): string
    {
        return 'test-access-token';
    }

    private function mockOAuthFor(User $user): void
    {
        $token = $this->bearerFor($user);

        $this->mock(OAuthMcpJwtService::class, function ($mock) use ($user, $token): void {
            $mock->shouldReceive('verifyAccessToken')
                ->with($token)
                ->zeroOrMoreTimes()
                ->andReturn([
                    'user_id' => $user->id,
                    'aud' => config('oauth-mcp.resource'),
                ]);
        });
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function mcpCall(User $user, string $method, array $params = [], mixed $id = 1): TestResponse
    {
        $this->mockOAuthFor($user);

        return $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ], [
            'Authorization' => 'Bearer '.$this->bearerFor($user),
            'Accept' => 'application/json',
        ]);
    }

    #[Test]
    public function test_tools_list_includes_warmup_tools(): void
    {
        $user = User::factory()->create();
        $this->mockOAuthFor($user);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$this->bearerFor($user),
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $names = array_column($response->json('result.tools'), 'name');
        $this->assertContains('list_warmup_mailboxes', $names);
        $this->assertContains('get_warmup_mailbox', $names);
    }

    #[Test]
    public function test_list_warmup_mailboxes_returns_plan_and_mailboxes(): void
    {
        $user = User::factory()->create();

        $outreach = WarmupMailbox::factory()->for($user)->outreach()->warming()->create([
            'email' => 'outreach@example.com',
            'deliverability_score' => 72,
        ]);
        $seed = WarmupMailbox::factory()->for($user)->create([
            'email' => 'seed@example.com',
            'is_outreach_mailbox' => false,
            'is_seed_mailbox' => true,
        ]);
        WarmupMailbox::factory()->for($user)->create([
            'is_outreach_mailbox' => false,
            'is_seed_mailbox' => true,
        ]);

        WarmupSend::factory()->create([
            'from_mailbox_id' => $outreach->id,
            'to_mailbox_id' => $seed->id,
            'sent_at' => now(),
        ]);

        WarmupAlert::create([
            'warmup_mailbox_id' => $outreach->id,
            'type' => 'at_risk',
            'message' => 'Score dropped.',
            'created_at' => now(),
        ]);

        $response = $this->mcpCall($user, 'list_warmup_mailboxes');

        $response->assertOk();
        $response->assertJsonPath('result.plan.tier', 'solo');
        $response->assertJsonPath('result.plan.usage.outreach_mailboxes', 1);
        $response->assertJsonPath('result.plan.usage.seed_mailboxes', 2);
        $response->assertJsonPath('result.plan.setup_complete', true);
        $response->assertJsonCount(3, 'result.mailboxes');

        $outreachRow = collect($response->json('result.mailboxes'))
            ->firstWhere('email', 'outreach@example.com');
        $this->assertSame(1, $outreachRow['sends_today']);
        $this->assertTrue($outreachRow['has_unread_alerts']);
    }

    #[Test]
    public function test_list_warmup_mailboxes_filters_by_status(): void
    {
        $user = User::factory()->create();

        WarmupMailbox::factory()->for($user)->outreach()->create(['status' => 'warming']);
        WarmupMailbox::factory()->for($user)->create(['status' => 'ready']);

        $response = $this->mcpCall($user, 'list_warmup_mailboxes', ['status' => 'warming']);

        $response->assertOk();
        $response->assertJsonCount(1, 'result.mailboxes');
        $response->assertJsonPath('result.mailboxes.0.status', 'warming');
    }

    #[Test]
    public function test_list_warmup_mailboxes_empty_when_no_mailboxes(): void
    {
        $user = User::factory()->create();

        $response = $this->mcpCall($user, 'list_warmup_mailboxes');

        $response->assertOk();
        $response->assertJsonPath('result.plan.setup_complete', false);
        $response->assertJsonCount(0, 'result.mailboxes');
    }

    #[Test]
    public function test_get_warmup_mailbox_returns_detail(): void
    {
        $user = User::factory()->create();
        $seed = WarmupMailbox::factory()->for($user)->create();
        $mailbox = WarmupMailbox::factory()->for($user)->outreach()->warming()->create([
            'deliverability_score' => 80,
            'warmup_ramp_days' => 14,
        ]);

        WarmupSend::factory()->replied()->create([
            'from_mailbox_id' => $mailbox->id,
            'to_mailbox_id' => $seed->id,
            'sent_at' => now()->startOfWeek()->addDay(),
            'subject' => 'Test subject',
        ]);

        WarmupSend::factory()->rescued()->create([
            'from_mailbox_id' => $mailbox->id,
            'to_mailbox_id' => $seed->id,
            'sent_at' => now()->startOfWeek()->addDays(2),
        ]);

        WarmupAlert::create([
            'warmup_mailbox_id' => $mailbox->id,
            'type' => 'at_risk',
            'message' => 'Review DNS settings.',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->mcpCall($user, 'get_warmup_mailbox', [
            'mailbox_id' => $mailbox->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('result.mailbox.email', $mailbox->email);
        $response->assertJsonPath('result.stats.sends_this_week', 2);
        $response->assertJsonPath('result.stats.replies_received', 1);
        $response->assertJsonPath('result.stats.spam_rescues', 1);
        $response->assertJsonCount(2, 'result.recent_sends');
        $response->assertJsonCount(1, 'result.alerts');
        $response->assertJsonPath('result.alerts.0.message', 'Review DNS settings.');
        $this->assertStringContainsString('/warmup/'.$mailbox->id, $response->json('result.app_url'));
    }

    #[Test]
    public function test_get_warmup_mailbox_rejects_other_users_mailbox(): void
    {
        $user = User::factory()->create();
        $mailbox = WarmupMailbox::factory()->for(User::factory())->outreach()->create();

        $response = $this->mcpCall($user, 'get_warmup_mailbox', [
            'mailbox_id' => $mailbox->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error.code', -32602);
    }
}
