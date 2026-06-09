<?php

namespace Tests\Feature;

use App\Models\OauthMcpAuthorizationCode;
use App\Models\OauthMcpClient;
use App\Models\User;
use App\Services\OAuthMcpJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthServerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{verifier: string, challenge: string}
     */
    private function pkcePair(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    public function test_register_client_with_allowed_redirect_uri(): void
    {
        $response = $this->postJson('/oauth/register', [
            'redirect_uris' => ['http://127.0.0.1:5555/callback'],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['client_id', 'redirect_uris']);

        $this->assertDatabaseHas('oauth_mcp_clients', [
            'id' => $response->json('client_id'),
        ]);
    }

    public function test_register_rejects_disallowed_redirect_uri(): void
    {
        $this->postJson('/oauth/register', [
            'redirect_uris' => ['https://evil.example/callback'],
        ])->assertStatus(400);
    }

    public function test_authorization_code_grant_returns_tokens(): void
    {
        $user = User::factory()->create();
        $client = OauthMcpClient::create([
            'redirect_uris' => ['http://127.0.0.1:5555/callback'],
        ]);
        $pkce = $this->pkcePair();
        $resource = (string) config('oauth-mcp.resource');

        OauthMcpAuthorizationCode::query()->create([
            'code' => 'test-auth-code',
            'client_id' => $client->id,
            'user_id' => $user->id,
            'redirect_uri' => 'http://127.0.0.1:5555/callback',
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
            'resource' => OAuthMcpJwtService::normalizeResourceUrl($resource),
            'scope' => config('oauth-mcp.scope'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => 'test-auth-code',
            'redirect_uri' => 'http://127.0.0.1:5555/callback',
            'client_id' => $client->id,
            'code_verifier' => $pkce['verifier'],
            'resource' => $resource,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'refresh_token', 'scope']);

        $jwt = app(OAuthMcpJwtService::class);
        $jwt->verifyAccessToken($response->json('access_token'));
    }

    public function test_refresh_token_grant_rotates_tokens(): void
    {
        $user = User::factory()->create();
        $client = OauthMcpClient::create([
            'redirect_uris' => ['http://127.0.0.1:5555/callback'],
        ]);
        $pkce = $this->pkcePair();
        $resource = (string) config('oauth-mcp.resource');

        OauthMcpAuthorizationCode::query()->create([
            'code' => 'refresh-flow-code',
            'client_id' => $client->id,
            'user_id' => $user->id,
            'redirect_uri' => 'http://127.0.0.1:5555/callback',
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
            'resource' => OAuthMcpJwtService::normalizeResourceUrl($resource),
            'scope' => config('oauth-mcp.scope'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $initial = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => 'refresh-flow-code',
            'redirect_uri' => 'http://127.0.0.1:5555/callback',
            'client_id' => $client->id,
            'code_verifier' => $pkce['verifier'],
            'resource' => $resource,
        ])->assertOk();

        $refreshToken = $initial->json('refresh_token');

        $rotated = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $client->id,
            'resource' => $resource,
        ]);

        $rotated->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token']);

        $this->assertNotSame($refreshToken, $rotated->json('refresh_token'));
    }

    public function test_revoke_refresh_token_returns_ok(): void
    {
        $user = User::factory()->create();
        $client = OauthMcpClient::create([
            'redirect_uris' => ['http://127.0.0.1:5555/callback'],
        ]);
        $pkce = $this->pkcePair();
        $resource = (string) config('oauth-mcp.resource');

        OauthMcpAuthorizationCode::query()->create([
            'code' => 'revoke-flow-code',
            'client_id' => $client->id,
            'user_id' => $user->id,
            'redirect_uri' => 'http://127.0.0.1:5555/callback',
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
            'resource' => OAuthMcpJwtService::normalizeResourceUrl($resource),
            'scope' => config('oauth-mcp.scope'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $tokens = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => 'revoke-flow-code',
            'redirect_uri' => 'http://127.0.0.1:5555/callback',
            'client_id' => $client->id,
            'code_verifier' => $pkce['verifier'],
            'resource' => $resource,
        ])->assertOk();

        $this->post('/oauth/revoke', [
            'token' => $tokens->json('refresh_token'),
            'token_type_hint' => 'refresh_token',
            'client_id' => $client->id,
        ])->assertOk();
    }
}
