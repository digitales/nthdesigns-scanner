<?php

namespace App\Services\OAuthMcp;

use App\Http\Requests\OAuthTokenRequest;
use App\Models\OauthMcpAuthorizationCode;
use App\Services\OAuthMcpJwtService;
use App\Services\OAuthMcpRefreshTokenService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OAuthMcpTokenGrantHandler
{
    public function __construct(
        private OAuthMcpJwtService $jwt,
        private OAuthMcpRefreshTokenService $refreshTokens,
    ) {}

    public function resolveTokenRequest(Request $request): OAuthTokenRequest
    {
        $form = OAuthTokenRequest::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->validateResolved();

        return $form;
    }

    public function exchangeAuthorizationCode(OAuthTokenRequest $request): Response
    {
        $code = OauthMcpAuthorizationCode::query()
            ->where('code', $request->code)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $code) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'Invalid or expired code'], 400);
        }

        if ($code->client_id !== $request->client_id || $code->redirect_uri !== $request->redirect_uri) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        if (OAuthMcpJwtService::normalizeResourceUrl((string) $code->resource)
            !== OAuthMcpJwtService::normalizeResourceUrl((string) $request->resource)) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'Resource mismatch'], 400);
        }

        $expectedChallenge = hash('sha256', $request->code_verifier, true);
        $expectedChallenge = rtrim(strtr(base64_encode($expectedChallenge), '+/', '-_'), '=');
        if (! hash_equals($expectedChallenge, $code->code_challenge)) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'Invalid code_verifier'], 400);
        }

        $code->update(['used_at' => now()]);

        $accessToken = $this->jwt->issueAccessToken($code->user, $request->resource);

        $issued = $this->refreshTokens->issueForCodeExchange(
            $code->user,
            $code->client,
            (string) $request->resource,
            $code->scope ?? config('oauth-mcp.scope'),
            $request,
        );

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('oauth-mcp.access_token_ttl_seconds', 3600),
            'refresh_token' => $issued['raw'],
            'scope' => $code->scope ?? config('oauth-mcp.scope'),
        ]);
    }

    public function exchangeRefreshToken(OAuthTokenRequest $request): Response
    {
        try {
            $result = $this->refreshTokens->rotate(
                (string) $request->input('refresh_token'),
                (string) $request->input('client_id'),
                (string) $request->input('resource'),
                $request->input('scope'),
                $request,
            );
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
            if (! in_array($error, ['invalid_grant', 'invalid_scope'], true)) {
                $error = 'invalid_grant';
            }

            return response()->json(['error' => $error], 400);
        }

        $accessToken = $this->jwt->issueAccessToken($result['user'], $result['resource']);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('oauth-mcp.access_token_ttl_seconds', 3600),
            'refresh_token' => $result['raw'],
            'scope' => $result['scope'] ?? config('oauth-mcp.scope'),
        ]);
    }
}
