<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openrouter.key', '');
        $this->model = (string) config('services.openrouter.model', 'anthropic/claude-sonnet-4');
        $this->baseUrl = (string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');
    }

    /**
     * @return array{content: string, model: string, prompt_tokens: int, completion_tokens: int}
     */
    public function complete(string $systemPrompt, string $userPrompt): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OPENROUTER_API_KEY is not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])->timeout(60)->post($this->baseUrl.'/chat/completions', [
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            Log::error('OpenRouter API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OpenRouter API request failed: '.$response->status());
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        return [
            'content' => trim($text),
            'model' => $data['model'] ?? $this->model,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
        ];
    }
}
