<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMcpKey;
use App\Services\Mcp\McpJsonRpcDispatcher;
use App\Services\Mcp\McpProgressStreamHandler;
use App\Services\McpSessionService;
use App\Services\OAuthMcpJwtService;
use App\Support\BearerTokenExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class McpController extends Controller
{
    public function __construct(
        private McpSessionService $mcpSessions,
        private OAuthMcpJwtService $oauthJwt,
        private McpJsonRpcDispatcher $dispatcher,
        private McpProgressStreamHandler $progressStream,
    ) {}

    public function show(Request $request): JsonResponse|Response
    {
        $accept = strtolower((string) $request->headers->get('Accept', ''));
        if (str_contains($accept, 'text/event-stream')) {
            if ($deny = $this->progressStream->rejectInvalidOrigin($request)) {
                return $deny;
            }

            return response('', Response::HTTP_METHOD_NOT_ALLOWED)
                ->header('Allow', 'DELETE, GET, POST');
        }

        return response()->json([
            'name' => 'nthdesigns-scanner',
            'version' => '1.0',
            'protocol' => 'json-rpc',
            'auth' => 'OAuth Bearer token (scanner:mcp) or x-scanner-key header',
            'methods' => $this->dispatcher->methodNames(),
        ]);
    }

    public function destroy(Request $request): JsonResponse|Response
    {
        $user = $this->resolveUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $sessionId = $request->header('Mcp-Session-Id');
        if (! is_string($sessionId) || $sessionId === '') {
            return response('', Response::HTTP_METHOD_NOT_ALLOWED);
        }

        if ($this->mcpSessions->userId($sessionId) !== $user->id) {
            return response()->json(['message' => 'Invalid or expired session'], Response::HTTP_NOT_FOUND);
        }

        $this->mcpSessions->destroy($sessionId);

        return response('', Response::HTTP_NO_CONTENT);
    }

    public function __invoke(Request $request): JsonResponse|Response
    {
        if ($this->progressStream->wantsStreamablePost($request)) {
            return $this->handleStreamablePost($request);
        }

        return $this->handleLegacyPost($request);
    }

    private function handleLegacyPost(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        return $this->dispatcher->process($request, $user, $request->all(), legacyTransport: true);
    }

    private function handleStreamablePost(Request $request): JsonResponse|Response
    {
        if ($deny = $this->progressStream->rejectInvalidOrigin($request)) {
            return $deny;
        }

        $messages = $this->progressStream->normalizeMessages($request->all());
        if ($messages === null) {
            return response()->json(['message' => 'Invalid JSON-RPC body'], Response::HTTP_BAD_REQUEST);
        }

        if ($messages === []) {
            return response()->json(['message' => 'Empty body'], Response::HTTP_BAD_REQUEST);
        }

        if (count($messages) > 1) {
            return response()->json(['message' => 'Batched requests are not supported'], Response::HTTP_BAD_REQUEST);
        }

        $msg = $messages[0];
        if (! array_key_exists('id', $msg)) {
            $user = $this->resolveUser($request);
            if ($user === null) {
                return $this->unauthorizedResponse();
            }

            return $this->progressStream->handleNotification($request, $user, $this->mcpSessions);
        }

        $user = $this->resolveUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $method = $msg['method'] ?? null;
        if ($method !== 'initialize') {
            if ($sessionError = $this->progressStream->validateSession($request, $user, $this->mcpSessions)) {
                return $sessionError;
            }
        }

        return $this->dispatcher->process($request, $user, $msg, legacyTransport: false);
    }

    private function resolveUser(Request $request): ?User
    {
        $token = BearerTokenExtractor::fromRequest($request);
        if ($token !== null && config('oauth-mcp.enabled', true)) {
            try {
                $payload = $this->oauthJwt->verifyAccessToken($token);

                return User::find($payload['user_id']);
            } catch (\Throwable $e) {
                if (config('mcp.log_oauth_failures', false)) {
                    Log::warning('MCP OAuth JWT rejected', [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $key = $request->header('x-scanner-key');
        $key = is_string($key) ? trim($key) : '';
        if ($key === '') {
            return null;
        }

        $mcpKey = UserMcpKey::findByPlainKey($key);
        if ($mcpKey === null) {
            return null;
        }

        $user = $mcpKey->user;
        if ($user !== null) {
            $mcpKey->update(['last_used_at' => now()]);
        }

        return $user;
    }

    private function unauthorizedResponse(): JsonResponse
    {
        $resource = config('oauth-mcp.resource');
        $scope = config('oauth-mcp.scope', 'scanner:mcp');
        $metadata = urlencode((string) $resource);

        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => 'Unauthorized',
            ],
        ], Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Bearer resource_metadata="'.$metadata.'", scope="'.$scope.'"',
        ]);
    }
}
