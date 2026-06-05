<?php

namespace App\Services\Mcp;

use App\Models\User;
use App\Services\McpSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpProgressStreamHandler
{
    public function __construct(private McpSearchService $searches) {}

    public function wantsStreamablePost(Request $request): bool
    {
        $accept = strtolower((string) $request->headers->get('Accept', ''));

        return str_contains($accept, 'application/json')
            && str_contains($accept, 'text/event-stream');
    }

    public function rejectInvalidOrigin(Request $request): ?JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '') {
            return null;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return response()->json(['message' => 'Invalid Origin'], Response::HTTP_FORBIDDEN);
        }

        foreach ($this->allowedHosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return null;
            }
        }

        return response()->json(['message' => 'Origin not allowed'], Response::HTTP_FORBIDDEN);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return ?array<int, array<string, mixed>>
     */
    public function normalizeMessages(array $body): ?array
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

    public function handleNotification(Request $request, User $user, McpSessionService $sessions): JsonResponse|Response
    {
        $sessionId = $request->header('Mcp-Session-Id');
        if (! is_string($sessionId) || $sessionId === '') {
            return response()->json(['message' => 'Mcp-Session-Id required'], Response::HTTP_BAD_REQUEST);
        }

        if ($sessions->userId($sessionId) !== $user->id) {
            return response()->json(['message' => 'Invalid or expired session'], Response::HTTP_NOT_FOUND);
        }

        return response('', Response::HTTP_ACCEPTED);
    }

    public function validateSession(Request $request, User $user, McpSessionService $sessions): ?JsonResponse
    {
        $sessionId = $request->header('Mcp-Session-Id');
        if (! is_string($sessionId) || $sessionId === '') {
            return response()->json(['message' => 'Mcp-Session-Id required'], Response::HTTP_BAD_REQUEST);
        }

        if ($sessions->userId($sessionId) !== $user->id) {
            return response()->json(['message' => 'Invalid or expired session'], Response::HTTP_NOT_FOUND);
        }

        return null;
    }

    public function supportsProgressStreaming(string $toolName): bool
    {
        return in_array($toolName, [
            'get_search',
            'get_search_progress_flow',
            'watch_search_progress',
        ], true);
    }

    public function progressTokenFromMeta(mixed $meta): string|int|null
    {
        if (! is_array($meta)) {
            return null;
        }

        $token = $meta['progressToken'] ?? null;

        return is_string($token) || is_int($token) ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function streamToolCall(
        mixed $id,
        string $name,
        array $arguments,
        User $user,
        string|int $progressToken,
    ): StreamedResponse {
        return response()->stream(function () use ($id, $name, $arguments, $user, $progressToken): void {
            $searchId = (int) ($arguments['search_id'] ?? 0);
            $timeout = max(5, min((int) ($arguments['timeout_seconds'] ?? 45), 45));
            $startedAt = microtime(true);
            $lastProgress = -1.0;

            do {
                $snapshot = $name === 'watch_search_progress'
                    ? $this->searches->watchSearchProgress(
                        $user,
                        $searchId,
                        $timeout,
                        (bool) ($arguments['include_prospects'] ?? false),
                    )
                    : ($name === 'get_search'
                        ? $this->searches->getSearch($user, $searchId, (bool) ($arguments['include_prospects'] ?? false))
                        : $this->searches->getSearchProgressFlow(
                            $user,
                            $searchId,
                            (bool) ($arguments['include_prospects'] ?? true),
                        ));

                $flow = $name === 'watch_search_progress'
                    ? ($snapshot['snapshot']['progress_flow'] ?? [])
                    : ($snapshot['progress_flow'] ?? []);

                $progress = (float) ($flow['progress'] ?? 0);
                $total = $flow['total'] ?? null;
                $message = (string) ($flow['message'] ?? 'In progress');
                $complete = (bool) ($flow['search_complete'] ?? false);

                if ($progress > $lastProgress) {
                    $lastProgress = $progress;
                    $notification = [
                        'jsonrpc' => '2.0',
                        'method' => 'notifications/progress',
                        'params' => [
                            'progressToken' => $progressToken,
                            'progress' => $progress,
                            'message' => $message,
                        ],
                    ];

                    if (is_int($total) || is_float($total)) {
                        $notification['params']['total'] = $total;
                    }

                    echo "event: message\n";
                    echo 'data: '.json_encode($notification, JSON_UNESCAPED_SLASHES)."\n\n";
                    ob_flush();
                    flush();
                }

                if ($complete) {
                    $result = $name === 'get_search'
                        ? $this->searches->getSearch($user, $searchId, (bool) ($arguments['include_prospects'] ?? false))
                        : $snapshot;

                    $this->emitFinalResult($id, $result);

                    return;
                }

                if (connection_aborted()) {
                    return;
                }

                sleep(max(1, (int) config('scanner.mcp_progress_poll_seconds', 2)));
            } while ((microtime(true) - $startedAt) < $timeout);

            $result = $name === 'get_search'
                ? $this->searches->getSearch($user, $searchId, (bool) ($arguments['include_prospects'] ?? false))
                : $this->searches->getSearchProgressFlow(
                    $user,
                    $searchId,
                    $name === 'watch_search_progress'
                        ? (bool) ($arguments['include_prospects'] ?? false)
                        : (bool) ($arguments['include_prospects'] ?? true),
                );

            $this->emitFinalResult($id, $result);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function emitFinalResult(mixed $id, array $result): void
    {
        $final = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                ]],
            ],
        ];

        echo "event: message\n";
        echo 'data: '.json_encode($final, JSON_UNESCAPED_SLASHES)."\n\n";
        ob_flush();
        flush();
    }

    /**
     * @return array<int, string>
     */
    private function allowedHosts(): array
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
}
