<?php

namespace App\Http\Controllers;

use App\Http\Requests\OAuthAuthorizeRequest;
use App\Http\Requests\OAuthRegisterClientRequest;
use App\Http\Requests\OAuthRevokeTokenRequest;
use App\Models\OauthMcpClient;
use App\Services\OAuthMcp\OAuthMcpAuthorizationCodeIssuer;
use App\Services\OAuthMcp\OAuthMcpClientRegistrar;
use App\Services\OAuthMcp\OAuthMcpTokenGrantHandler;
use App\Services\OAuthMcpRefreshTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OAuthServerController extends Controller
{
    public function __construct(
        private OAuthMcpClientRegistrar $clientRegistrar,
        private OAuthMcpAuthorizationCodeIssuer $authorizationCodes,
        private OAuthMcpTokenGrantHandler $tokenGrants,
        private OAuthMcpRefreshTokenService $refreshTokens,
    ) {}

    /**
     * Dynamic client registration (RFC 7591).
     */
    public function register(OAuthRegisterClientRequest $request): Response
    {
        return $this->clientRegistrar->register($request->validated('redirect_uris'));
    }

    /**
     * Authorization endpoint — show consent or redirect to login.
     */
    public function showConsent(OAuthAuthorizeRequest $request): View|RedirectResponse
    {
        $client = OauthMcpClient::find($request->client_id);
        if (! $client || ! in_array($request->redirect_uri, $client->redirect_uris, true)) {
            return redirect()->route('login')->with('error', 'Invalid OAuth client or redirect URI.');
        }

        if (! auth()->check()) {
            $intended = url('/oauth/authorize').'?'.http_build_query($request->only(
                'response_type', 'client_id', 'redirect_uri', 'scope', 'state',
                'code_challenge', 'code_challenge_method', 'resource'
            ));

            // Must persist until POST /login (redirect()->intended()). Flashing with ->with('url.intended')
            // loses the URL after the GET /login request, so users fell through to idea.index instead of OAuth.
            $request->session()->put('url.intended', $intended);

            return redirect()->route('login')->with('error', 'Please log in to connect the prospect scanner.');
        }

        if ($request->has('approve') && $request->approve === '1') {
            return $this->authorizationCodes->createAndRedirect($request, $client);
        }

        return view('oauth.consent', [
            'authorizeUrl' => url('/oauth/authorize').'?'.http_build_query(array_merge($request->only(
                'response_type', 'client_id', 'redirect_uri', 'scope', 'state',
                'code_challenge', 'code_challenge_method', 'resource'
            ), ['approve' => '1'])),
            'scope' => $request->scope ?? config('oauth-mcp.scope'),
        ]);
    }

    /**
     * Token endpoint — dispatches to the correct grant handler.
     */
    public function token(Request $request): Response
    {
        $grantType = (string) $request->input('grant_type');

        if ($grantType === 'authorization_code') {
            return $this->tokenGrants->exchangeAuthorizationCode(
                $this->tokenGrants->resolveTokenRequest($request),
            );
        }

        if ($grantType === 'refresh_token') {
            return $this->tokenGrants->exchangeRefreshToken(
                $this->tokenGrants->resolveTokenRequest($request),
            );
        }

        return response()->json([
            'error' => 'unsupported_grant_type',
        ], 400);
    }

    /**
     * RFC 7009 OAuth 2.0 Token Revocation.
     * Always returns 200 regardless of whether the token existed.
     */
    public function revoke(OAuthRevokeTokenRequest $request): Response
    {
        $hint = (string) $request->input('token_type_hint', 'refresh_token');
        if ($hint === 'refresh_token') {
            $this->refreshTokens->revokeByRawToken(
                (string) $request->input('token'),
                'user',
                (string) $request->input('client_id'),
            );
        }

        return response()->json([], 200);
    }
}
