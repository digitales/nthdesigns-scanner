<?php

namespace Tests\Feature;

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

        $response = $this->mcpCall($user, 'get_search', [
            'search_id' => $search->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('result.search.status', 'auditing');
        $response->assertJsonPath('result.progress.search_complete', false);
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
    }
}
