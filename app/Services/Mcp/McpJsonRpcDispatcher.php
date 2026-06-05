<?php

namespace App\Services\Mcp;

use App\Models\User;
use App\Services\McpSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class McpJsonRpcDispatcher
{
    public function __construct(
        private McpSessionService $mcpSessions,
        private McpSearchService $searches,
        private McpSingleSiteAuditService $singleSiteAudits,
        private McpProgressStreamHandler $progressStream,
    ) {}

    /**
     * @return array<int, string>
     */
    public function methodNames(): array
    {
        return [
            'list_searches',
            'get_search',
            'list_search_prospects',
            'get_search_progress_flow',
            'watch_search_progress',
            'start_single_site_audit',
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function process(Request $request, User $user, array $body, bool $legacyTransport): JsonResponse|Response
    {
        $method = $body['method'] ?? null;
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];
        $id = $body['id'] ?? null;

        if (! is_string($method) || $method === '') {
            return $this->error(-32600, 'Invalid request: method required', $id);
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
            return $this->respondToolsCall($params, $id, $user, $legacyTransport);
        }

        if (! in_array($method, $this->methodNames(), true)) {
            return $this->error(-32601, 'Method not found', $id);
        }

        try {
            $result = $this->dispatch($method, $params, $user);
        } catch (\InvalidArgumentException $e) {
            return $this->error(-32602, $e->getMessage(), $id);
        } catch (\Throwable $e) {
            Log::error('MCP dispatch failed', [
                'method' => $method,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return $this->error(-32603, 'Internal error', $id);
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
            [
                'name' => 'get_search_progress_flow',
                'description' => 'Get progress flow snapshot for a search, with optional per-prospect progress rows.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search_id' => ['type' => 'integer', 'description' => 'Search ID'],
                        'include_prospects' => ['type' => 'boolean', 'description' => 'Include prospect progress rows (default true)'],
                    ],
                    'required' => ['search_id'],
                ],
            ],
            [
                'name' => 'watch_search_progress',
                'description' => 'Watch progress for up to 45s; supports progressToken notifications on streamable transport.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search_id' => ['type' => 'integer', 'description' => 'Search ID'],
                        'timeout_seconds' => ['type' => 'integer', 'description' => 'Max watch duration (5-45, default 45)'],
                        'include_prospects' => ['type' => 'boolean', 'description' => 'Include prospect rows in snapshots (default false)'],
                    ],
                    'required' => ['search_id'],
                ],
            ],
        ];
    }

    private function respondToolsCall(
        array $params,
        mixed $id,
        User $user,
        bool $legacyTransport,
    ): JsonResponse|Response {
        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $progressToken = $this->progressStream->progressTokenFromMeta($params['_meta'] ?? null);

        if (config('mcp.log_tool_calls', false)) {
            Log::info('MCP tools/call', ['tool' => $name]);
        }

        if (! is_string($name) || $name === '') {
            return $this->error(-32602, 'tools/call requires "name"', $id);
        }

        if (! in_array($name, $this->methodNames(), true)) {
            return $this->error(-32601, 'Method not found', $id);
        }

        if (! $legacyTransport && $progressToken !== null && $this->progressStream->supportsProgressStreaming($name)) {
            return $this->progressStream->streamToolCall($id, $name, $arguments, $user, $progressToken);
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
            'get_search_progress_flow' => $this->searches->getSearchProgressFlow(
                $user,
                (int) ($params['search_id'] ?? 0),
                (bool) ($params['include_prospects'] ?? true),
            ),
            'watch_search_progress' => $this->searches->watchSearchProgress(
                $user,
                (int) ($params['search_id'] ?? 0),
                (int) ($params['timeout_seconds'] ?? 45),
                (bool) ($params['include_prospects'] ?? false),
            ),
            'start_single_site_audit' => $this->singleSiteAudits->start(
                $user,
                (string) ($params['website_url'] ?? ''),
            ),
            default => throw new \InvalidArgumentException('Method not found'),
        };
    }

    public function error(int $code, string $message, mixed $id): JsonResponse
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
