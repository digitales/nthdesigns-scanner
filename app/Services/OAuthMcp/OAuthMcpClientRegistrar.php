<?php

namespace App\Services\OAuthMcp;

use App\Models\OauthMcpClient;
use Symfony\Component\HttpFoundation\Response;

class OAuthMcpClientRegistrar
{
    /**
     * @param  list<string>  $redirectUris
     */
    public function register(array $redirectUris): Response
    {
        $normalized = $this->normalizeRedirectUris($redirectUris);
        if ($normalized === []) {
            return response(
                ['error' => 'invalid_redirect_uri', 'error_description' => 'Redirect URIs must be allowlisted'],
                400,
            );
        }

        $client = OauthMcpClient::query()->create([
            'redirect_uris' => $normalized,
        ]);

        return response()->json([
            'client_id' => $client->id,
            'redirect_uris' => $client->redirect_uris,
        ], 201);
    }

    /**
     * @param  list<string>  $uris
     * @return list<string>
     */
    public function normalizeRedirectUris(array $uris): array
    {
        $allowed = config('oauth-mcp.allowed_redirect_hosts', []);
        $normalized = [];

        foreach ($uris as $uri) {
            $host = parse_url($uri, PHP_URL_HOST);
            if ($host && in_array($host, $allowed, true)) {
                $normalized[] = $uri;
            }
        }

        return $normalized;
    }
}
