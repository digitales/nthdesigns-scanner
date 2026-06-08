<?php

namespace Tests\Unit;

use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    public function test_complete_parses_openrouter_chat_response(): void
    {
        Config::set('services.openrouter.key', 'test-key');
        Config::set('services.openrouter.model', 'anthropic/claude-sonnet-4');
        Config::set('services.openrouter.base_url', 'https://openrouter.ai/api/v1');

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'anthropic/claude-sonnet-4',
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => '{"subject_line":"Hi","email_body":"Body"}']],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                ],
            ]),
        ]);

        $result = (new OpenRouterService)->complete('system', 'user');

        $this->assertSame('{"subject_line":"Hi","email_body":"Body"}', $result['content']);
        $this->assertSame('anthropic/claude-sonnet-4', $result['model']);
        $this->assertSame(100, $result['prompt_tokens']);
        $this->assertSame(50, $result['completion_tokens']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $body['model'] === 'anthropic/claude-sonnet-4'
                && $body['messages'][0]['role'] === 'system'
                && $body['messages'][1]['role'] === 'user';
        });
    }

    public function test_complete_requires_openrouter_api_key(): void
    {
        Config::set('services.openrouter.key', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OPENROUTER_API_KEY is not configured');

        (new OpenRouterService)->complete('system', 'user');
    }
}
