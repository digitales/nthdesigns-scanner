<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key', '');
        $this->model = config('services.anthropic.model', 'claude-sonnet-4-20250514');
    }

    /**
     * @return array{content: string, model: string, prompt_tokens: int, completion_tokens: int}
     */
    public function complete(string $systemPrompt, string $userPrompt): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY is not configured');
        }

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->model,
            'max_tokens' => 1024,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            Log::error('Anthropic API request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Anthropic API request failed: '.$response->status());
        }

        $data = $response->json();
        $text = collect($data['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        $usage = $data['usage'] ?? [];

        return [
            'content'            => trim($text),
            'model'              => $data['model'] ?? $this->model,
            'prompt_tokens'      => (int) ($usage['input_tokens'] ?? 0),
            'completion_tokens'  => (int) ($usage['output_tokens'] ?? 0),
        ];
    }
}
