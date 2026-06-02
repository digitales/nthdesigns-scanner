<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\OAuthMcpJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpScanToolsTest extends TestCase
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
    private function mcpCall(User $user, string $method, array $params = [], mixed $id = 1): \Illuminate\Testing\TestResponse
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

    /**
     * @param  array<string, mixed>  $body
     */
    private function mcpStreamableCall(User $user, array $body, ?string $sessionId = null): \Illuminate\Testing\TestResponse
    {
        $this->mockOAuthFor($user);

        $headers = [
            'Authorization' => 'Bearer '.$this->bearerFor($user),
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
        ];

        if ($sessionId !== null) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        return $this->postJson('/api/mcp', $body, $headers);
    }

    #[Test]
    public function test_get_mcp_describes_scan_tools(): void
    {
        $response = $this->getJson('/api/mcp');

        $response->assertOk();
        $response->assertJsonPath('name', 'nthdesigns-scanner');
        $this->assertContains('get_search', $response->json('methods'));
    }

    #[Test]
    public function test_list_searches_returns_only_own_searches(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Search::factory()->for($user)->create(['niche' => 'mine', 'city' => 'Leeds']);
        Search::factory()->for($other)->create(['niche' => 'theirs', 'city' => 'Leeds']);

        $response = $this->mcpCall($user, 'list_searches');

        $response->assertOk();
        $response->assertJsonPath('result.searches.0.niche', 'mine');
        $response->assertJsonCount(1, 'result.searches');
    }

    #[Test]
    public function test_get_search_summary_without_prospects(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create(['status' => 'auditing']);
        Prospect::factory()->for($search)->create([
            'audit_status' => 'pending',
            'business_name' => 'Alpha',
        ]);

        $response = $this->mcpCall($user, 'get_search', [
            'search_id' => $search->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('result.search.status', 'auditing');
        $response->assertJsonPath('result.progress.search_complete', false);
        $response->assertJsonPath('result.progress_flow.phase', 'auditing');
        $response->assertJsonPath('result.progress_flow.total', 1);
        $response->assertJsonMissingPath('result.prospects');
    }

    #[Test]
    public function test_get_search_rejects_other_users_search(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for(User::factory()->create())->create();

        $response = $this->mcpCall($user, 'get_search', [
            'search_id' => $search->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error.code', -32602);
    }

    #[Test]
    public function test_start_single_site_audit_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->mcpCall($user, 'start_single_site_audit', [
            'website_url' => 'https://example.com/path',
        ]);

        $response->assertOk();
        $searchId = $response->json('result.search_id');
        $this->assertNotNull($searchId);

        $search = Search::query()->find($searchId);
        $this->assertSame('direct_url', $search->source);
        $this->assertSame('https://example.com/path', $search->submitted_url);

        Queue::assertPushed(\App\Jobs\DirectUrlScanJob::class);
    }

    #[Test]
    public function test_unauthorized_without_bearer(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list_searches',
            'params' => [],
        ], ['Accept' => 'application/json']);

        $response->assertUnauthorized();
    }

    #[Test]
    public function test_tools_list_includes_scan_tools(): void
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
        $this->assertContains('start_single_site_audit', $names);
        $this->assertContains('get_search', $names);
        $this->assertContains('get_search_progress_flow', $names);
        $this->assertContains('watch_search_progress', $names);
    }

    #[Test]
    public function test_get_search_progress_flow_returns_snapshot(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create([
            'status' => 'auditing',
            'total_found' => 2,
        ]);

        Prospect::factory()->for($search)->create([
            'business_name' => 'Alpha',
            'audit_status' => 'pending',
        ]);
        Prospect::factory()->for($search)->create([
            'business_name' => 'Beta',
            'audit_status' => 'complete',
        ]);

        $response = $this->mcpCall($user, 'get_search_progress_flow', [
            'search_id' => $search->id,
            'include_prospects' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('result.search_id', $search->id);
        $response->assertJsonPath('result.progress_flow.phase', 'auditing');
        $response->assertJsonPath('result.progress_flow.progress', 1);
        $response->assertJsonCount(2, 'result.prospects');
        $response->assertJsonStructure([
            'result' => [
                'prospects' => [
                    '*' => [
                        'id',
                        'business_name',
                        'audit_status',
                        'progress_flow' => ['current_step', 'status_message'],
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function test_watch_search_progress_returns_snapshot_payload(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create([
            'status' => 'auditing',
            'total_found' => 1,
        ]);
        Prospect::factory()->for($search)->create([
            'audit_status' => 'pending',
        ]);

        $response = $this->mcpCall($user, 'watch_search_progress', [
            'search_id' => $search->id,
            'timeout_seconds' => 10,
            'include_prospects' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('result.watch.search_id', $search->id);
        $response->assertJsonPath('result.watch.timeout_seconds', 10);
        $response->assertJsonPath('result.snapshot.progress_flow.phase', 'auditing');
    }

    #[Test]
    public function test_streamable_tools_call_can_emit_progress_notifications(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create([
            'status' => 'complete',
            'total_found' => 1,
        ]);
        Prospect::factory()->for($search)->create([
            'audit_status' => 'complete',
        ]);

        $initialize = $this->mcpStreamableCall($user, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]);

        $initialize->assertOk();
        $sessionId = $initialize->headers->get('Mcp-Session-Id');
        $this->assertNotEmpty($sessionId);

        $response = $this->mcpStreamableCall($user, [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'watch_search_progress',
                'arguments' => [
                    'search_id' => $search->id,
                    'timeout_seconds' => 5,
                ],
                '_meta' => [
                    'progressToken' => 'search-progress-1',
                ],
            ],
        ], $sessionId);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/event-stream; charset=UTF-8');
        $streamed = $response->streamedContent();
        $this->assertStringContainsString('notifications/progress', $streamed);
        $this->assertStringContainsString('"progressToken":"search-progress-1"', $streamed);
    }
}
