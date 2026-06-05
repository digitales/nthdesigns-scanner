<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\OAuthMcpJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OAuthMcpJwtServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_access_token_fails_verification(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        $service = app(OAuthMcpJwtService::class);
        $token = $service->issueAccessToken($user, (string) config('oauth-mcp.resource'));

        $service->verifyAccessToken($token);

        $service->revokeAccessToken($token);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token revoked');

        $service->verifyAccessToken($token);
    }
}
