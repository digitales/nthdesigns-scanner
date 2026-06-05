<?php

namespace Tests\Feature;

use App\Models\OauthMcpClient;
use App\Models\OauthMcpRefreshTokenFamily;
use App\Models\User;
use App\Services\OAuthMcpRefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ConnectedAppsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeFamily(User $user): OauthMcpRefreshTokenFamily
    {
        $client = OauthMcpClient::create([
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ]);

        return app(OAuthMcpRefreshTokenService::class)
            ->issueForCodeExchange(
                $user,
                $client,
                config('oauth-mcp.resource'),
                config('oauth-mcp.scope'),
                Request::create('/oauth/token', 'POST'),
            )['family'];
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/settings/connected-apps')->assertRedirect('/login');
    }

    public function test_destroy_revokes_own_family(): void
    {
        $user = User::factory()->create();
        $family = $this->makeFamily($user);

        $response = $this->actingAs($user)
            ->delete('/settings/connected-apps/'.$family->id);

        $response->assertRedirect('/settings/connected-apps');
        $family->refresh();
        $this->assertNotNull($family->revoked_at);
    }

    public function test_destroy_rejects_another_users_family(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $family = $this->makeFamily($owner);

        $this->actingAs($other)
            ->delete('/settings/connected-apps/'.$family->id)
            ->assertForbidden();

        $family->refresh();
        $this->assertNull($family->revoked_at);
    }

    public function test_destroy_all_revokes_only_authenticated_users_families(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownFamily = $this->makeFamily($user);
        $otherFamily = $this->makeFamily($other);

        $this->actingAs($user)
            ->delete('/settings/connected-apps')
            ->assertRedirect('/settings/connected-apps');

        $ownFamily->refresh();
        $otherFamily->refresh();

        $this->assertNotNull($ownFamily->revoked_at);
        $this->assertNull($otherFamily->revoked_at);
    }
}
