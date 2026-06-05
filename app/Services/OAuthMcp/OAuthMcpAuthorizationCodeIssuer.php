<?php

namespace App\Services\OAuthMcp;

use App\Http\Requests\OAuthAuthorizeRequest;
use App\Models\OauthMcpAuthorizationCode;
use App\Models\OauthMcpClient;
use App\Services\OAuthMcpJwtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class OAuthMcpAuthorizationCodeIssuer
{
    public function createAndRedirect(OAuthAuthorizeRequest $request, OauthMcpClient $client): RedirectResponse
    {
        $code = Str::random(64);
        $ttl = config('oauth-mcp.authorization_code_ttl_seconds', 600);

        OauthMcpAuthorizationCode::query()->create([
            'code' => $code,
            'client_id' => $client->id,
            'user_id' => auth()->id(),
            'redirect_uri' => $request->redirect_uri,
            'code_challenge' => $request->code_challenge,
            'code_challenge_method' => $request->code_challenge_method,
            'resource' => OAuthMcpJwtService::normalizeResourceUrl((string) $request->resource),
            'scope' => $request->scope ?? config('oauth-mcp.scope'),
            'state' => $request->state,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return redirect()->away($request->redirect_uri.'?'.http_build_query([
            'code' => $code,
            'state' => $request->state,
        ]));
    }
}
