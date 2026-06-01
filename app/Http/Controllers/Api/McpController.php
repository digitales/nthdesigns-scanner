<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMcpKey;
use App\Services\Mcp\McpSearchService;
use App\Services\Mcp\McpSingleSiteAuditService;
use App\Services\McpSessionService;
use App\Services\OAuthMcpJwtService;
use App\Support\BearerTokenExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class McpController extends Controller
{
    public function __construct(
        private McpSessionService $mcpSessions,
        private OAuthMcpJwtService $oauthJwt,
        private McpSearchService $searches,
        private McpSingleSiteAuditService $singleSiteAudits,
    ) {}

    public function show(Request $request): JsonResponse|Response
    {
        $accept = strtolower((string) $request->headers->get('Accept', ''));
        if (str_contains($accept, 'text/event-stream')) {
            if ($deny = $this->rejectInvalidStreamableOrigin($request)) {
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
            'methods' => $this->mcpMethodNames(),
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

    /**
     * @return array<int, string>
     */
    private function mcpMethodNames(): array
    {
        return [
            'list_searches',
            'get_search',
            'list_search_prospects',
            'start_single_site_audit',
        ];
    }

    public function __invoke(Request $request): JsonResponse|Response
    {
        if ($this->wantsStreamableHttpPost($request)) {
            return $this->handleStreamablePost($request);
        }

        return $this->handleLegacyPost($request);
    }

    private function wantsStreamableHttpPost(Request $request): bool
    {
        $accept = strtolower((string) $request->headers->get('Accept', ''));

        return str_contains($accept, 'application/json')
            && str_contains($accept, 'text/event-stream');
    }

    private function handleLegacyPost(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        return $this->processSingleJsonRpcRequest($request, $user, $request->all(), legacyTransport: true);
    }

    private function handleStreamablePost(Request $request): JsonResponse|Response
    {
        if ($deny = $this->rejectInvalidStreamableOrigin($request)) {
            return $deny;
        }

        $messages = $this->normalizeMcpMessages($request->all());
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
            return $this->handleStreamableNotification($request, $msg);
        }

        $user = $this->resolveUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $method = $msg['method'] ?? null;
        if ($method !== 'initialize') {
            $sessionId = $request->header('Mcp-Session-Id');
            if (! is_string($sessionId) || $sessionId === '') {
                return response()->json(['message' => 'Mcp-Session-Id required'], Response::HTTP_BAD_REQUEST);
            }
            if ($this->mcpSessions->userId($sessionId) !== $user->id) {
                return response()->json(['message' => 'Invalid or expired session'], Response::HTTP_NOT_FOUND);
            }
        }

        return $this->processSingleJsonRpcRequest($request, $user, $msg, legacyTransport: false);
    }

    /**
     * @param  array<string, mixed>  $msg
     */
    private function handleStreamableNotification(Request $request, array $msg): JsonResponse|Response
    {
        $user = $this->resolveUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $sessionId = $request->header('Mcp-Session-Id');
        if (! is_string($sessionId) || $sessionId === '') {
            return response()->json(['message' => 'Mcp-Session-Id required'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->mcpSessions->userId($sessionId) !== $user->id) {
            return response()->json(['message' => 'Invalid or expired session'], Response::HTTP_NOT_FOUND);
        }

        return response('', Response::HTTP_ACCEPTED);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return ?array<int, array<string, mixed>>
     */
    private function normalizeMcpMessages(array $body): ?array
    {
        if ($body === []) {
            return null;
        }

        if (isset($body['method'])) {
            return [$body];
        }

        if (! array_is_list($body)) {
            return null;
        }

        foreach ($body as $item) {
            if (! is_array($item) || ! isset($item['method'])) {
                return null;
            }
        }

        return $body;
    }

    private function rejectInvalidStreamableOrigin(Request $request): ?JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '') {
            return null;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return response()->json(['message' => 'Invalid Origin'], Response::HTTP_FORBIDDEN);
        }

        foreach ($this->streamableAllowedHosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return null;
            }
        }

        return response()->json(['message' => 'Origin not allowed'], Response::HTTP_FORBIDDEN);
    }

    /**
     * @return array<int, string>
     */
    private function streamableAllowedHosts(): array
    {
        $hosts = [
            'claude.ai',
            'claude.com',
            'chatgpt.com',
            'chat.openai.com',
            'platform.openai.com',
            'cursor.sh',
            'cursor.com',
        ];

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = $appHost;
        }

        $extra = config('mcp.streamable_allowed_hosts_extra', '');
        if (is_string($extra) && $extra !== '') {
            foreach (array_map('trim', explode(',', $extra)) as $h) {
                if ($h !== '') {
                    $hosts[] = $h;
                }
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function processSingleJsonRpcRequest(Request $request, User $user, array $body, bool $legacyTransport): JsonResponse|Response
    {
        $method = $body['method'] ?? null;
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];
        $id = $body['id'] ?? null;

        if (! is_string($method) || $method === '') {
            return $this->jsonRpcError(-32600, 'Invalid request: method required', $id);
        }

        $request->setUserResolver(fn () => $user);
        Auth::setUser($user);

        if ($method === 'initialize') {
            return $this->respondInitialize($params, $id, $legacyTransport, $user);
        }
        if ($method === 'notifications/initialized') {
            return $legacyTransport
                ? response()->json(['jsonrpc' => '2.0'])
                : response('', Response::HTTP_ACCEPTED);
        }
        if ($method === 'tools/list') {
            return $this->respondToolsList($id);
        }
        if ($method === 'tools/call') {
            return $this->respondToolsCall($params, $id, $user);
        }

        if (! in_array($method, $this->mcpMethodNames(), true)) {
            return $this->jsonRpcError(-32601, 'Method not found', $id);
        }

        try {
            $result = $this->dispatch($method, $params, $user);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonRpcError(-32602, $e->getMessage(), $id);
        } catch (\Throwable $e) {
            Log::error('MCP dispatch failed', [
                'method' => $method,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return $this->jsonRpcError(-32603, 'Internal error', $id);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ]);
    }

    private function respondInitialize(array $params, mixed $id, bool $legacyTransport, ?User $user): JsonResponse
    {
        $requestedVersion = $params['protocolVersion'] ?? '2024-11-05';
        $supported = ['2024-11-05', '2025-03-26'];
        $protocolVersion = in_array($requestedVersion, $supported, true) ? $requestedVersion : '2024-11-05';

        $response = response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => $protocolVersion,
                'capabilities' => [
                    'tools' => (object) [],
                ],
                'serverInfo' => [
                    'name' => 'nthdesigns-scanner',
                    'version' => '1.0',
                ],
            ],
        ]);

        if (! $legacyTransport && $user !== null) {
            $response->headers->set('Mcp-Session-Id', $this->mcpSessions->create($user->id));
        }

        return $response;
    }

    private function respondToolsList(mixed $id): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => ['tools' => $this->toolDefinitions()],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'list_searches',
                'description' => 'List recent operator searches for the authenticated user.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Max results (1–50, default 10)'],
                        'status' => ['type' => 'string', 'description' => 'Optional filter: pending, discovering, auditing, complete, failed'],
                    ],
                ],
            ],
            [
                'name' => 'get_search',
                'description' => 'Get search status and progress aggregates. Set include_prospects=true for per-prospect detail.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search_id' => ['type' => 'integer', 'description' => 'Search ID'],
                        'include_prospects' => ['type' => 'boolean', 'description' => 'Include prospect summaries (default false)'],
                    ],
                    'required' => ['search_id'],
                ],
            ],
            [
                'name' => 'list_search_prospects',
                'description' => 'List prospect summaries for a search (scores, audit status, flags).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search_id' => ['type' => 'integer', 'description' => 'Search ID'],
                    ],
                    'required' => ['search_id'],
                ],
            ],
            [
                'name' => 'start_single_site_audit',
                'description' => 'Start a single-site URL audit (direct_url search). Returns search_id for monitoring.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'website_url' => ['type' => 'string', 'description' => 'Website URL to audit'],
                    ],
                    'required' => ['website_url'],
                ],
            ],
        ];
    }

    private function respondToolsCall(array $params, mixed $id, User $user): JsonResponse
    {
        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if (config('mcp.log_tool_calls', false)) {
            Log::info('MCP tools/call', ['tool' => $name]);
        }

        if (! is_string($name) || $name === '') {
            return $this->jsonRpcError(-32602, 'tools/call requires "name"', $id);
        }

        if (! in_array($name, $this->mcpMethodNames(), true)) {
            return $this->jsonRpcError(-32601, 'Method not found', $id);
        }

        try {
            $result = $this->dispatch($name, $arguments, $user);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [['type' => 'text', 'text' => 'Error: '.$e->getMessage()]],
                    'isError' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('MCP tools/call failed', [
                'tool' => $name,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [['type' => 'text', 'text' => 'Internal error']],
                    'isError' => true,
                ],
            ]);
        }

        $text = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'content' => [['type' => 'text', 'text' => $text]],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function dispatch(string $method, array $params, User $user): array
    {
        return match ($method) {
            'list_searches' => $this->searches->listSearches(
                $user,
                isset($params['limit']) ? (int) $params['limit'] : 10,
                isset($params['status']) ? (string) $params['status'] : null,
            ),
            'get_search' => $this->searches->getSearch(
                $user,
                (int) ($params['search_id'] ?? 0),
                (bool) ($params['include_prospects'] ?? false),
            ),
            'list_search_prospects' => $this->searches->listSearchProspects(
                $user,
                (int) ($params['search_id'] ?? 0),
            ),
            'start_single_site_audit' => $this->singleSiteAudits->start(
                $user,
                (string) ($params['website_url'] ?? ''),
            ),
            default => throw new \InvalidArgumentException('Method not found'),
        };
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

    private function jsonRpcError(int $code, string $message, mixed $id): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ]);
    }
}
