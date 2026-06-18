<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\OpenRouterService;
use App\Services\ProspectQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProspectQualificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_qualify_sets_caution_when_website_url_is_null(): void
    {
        $prospect = $this->makeProspect(['website_url' => null]);

        app(ProspectQualificationService::class)->qualify($prospect);

        $prospect->refresh();

        $this->assertSame('caution', $prospect->qualification_status);
        $this->assertSame('No website URL available to assess.', $prospect->qualification_summary);
        $this->assertSame(['No website URL recorded'], $prospect->qualification_flags);
        $this->assertNotNull($prospect->qualification_ran_at);
    }

    public function test_qualify_sets_caution_when_http_fetch_fails(): void
    {
        $prospect = $this->makeProspect(['website_url' => 'https://example.com']);

        Http::fake([
            '*' => Http::response('', 500),
        ]);

        app(ProspectQualificationService::class)->qualify($prospect);

        $prospect->refresh();

        $this->assertSame('caution', $prospect->qualification_status);
        $this->assertSame('Website could not be fetched — verify manually.', $prospect->qualification_summary);
        $this->assertSame(['Could not fetch website — robots.txt or timeout'], $prospect->qualification_flags);
        $this->assertNotNull($prospect->qualification_ran_at);
    }

    public function test_qualify_persists_valid_claude_response(): void
    {
        $prospect = $this->makeProspect(['website_url' => 'https://acorndental.co.uk']);

        Http::fake([
            'acorndental.co.uk/*' => Http::response('<html>Dr Smith established this practice</html>', 200),
        ]);

        $this->mock(OpenRouterService::class, function ($mock) {
            $mock->shouldReceive('complete')->once()->andReturn([
                'content' => json_encode([
                    'status' => 'qualified',
                    'summary' => 'Independent practice with named owner on site.',
                    'flags' => ['Named dentist visible', 'Direct email present'],
                ]),
                'model' => 'anthropic/claude-sonnet-4',
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
            ]);
        });

        app(ProspectQualificationService::class)->qualify($prospect);

        $prospect->refresh();

        $this->assertSame('qualified', $prospect->qualification_status);
        $this->assertSame('Independent practice with named owner on site.', $prospect->qualification_summary);
        $this->assertSame(['Named dentist visible', 'Direct email present'], $prospect->qualification_flags);
        $this->assertNotNull($prospect->qualification_ran_at);
    }

    public function test_qualify_parses_json_wrapped_in_markdown_fences(): void
    {
        $prospect = $this->makeProspect(['website_url' => 'https://example.com']);

        Http::fake([
            '*' => Http::response('<html>Dr Jones family practice</html>', 200),
        ]);

        $this->mock(OpenRouterService::class, function ($mock) {
            $mock->shouldReceive('complete')->once()->andReturn([
                'content' => "```json\n".json_encode([
                    'status' => 'qualified',
                    'summary' => 'Independent family-run practice.',
                    'flags' => ['Named dentist visible'],
                ])."\n```",
                'model' => 'anthropic/claude-sonnet-4',
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
            ]);
        });

        app(ProspectQualificationService::class)->qualify($prospect);

        $prospect->refresh();

        $this->assertSame('qualified', $prospect->qualification_status);
        $this->assertSame('Independent family-run practice.', $prospect->qualification_summary);
    }

    public function test_qualify_falls_back_to_caution_on_malformed_claude_response(): void
    {
        $prospect = $this->makeProspect(['website_url' => 'https://example.com']);

        Http::fake([
            '*' => Http::response('<html>content</html>', 200),
        ]);

        $this->mock(OpenRouterService::class, function ($mock) {
            $mock->shouldReceive('complete')->once()->andReturn([
                'content' => 'not valid json',
                'model' => 'anthropic/claude-sonnet-4',
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
            ]);
        });

        app(ProspectQualificationService::class)->qualify($prospect);

        $prospect->refresh();

        $this->assertSame('caution', $prospect->qualification_status);
        $this->assertSame('Qualification assessment failed — verify manually.', $prospect->qualification_summary);
        $this->assertStringContainsString('Claude assessment error:', $prospect->qualification_flags[0]);
        $this->assertNotNull($prospect->qualification_ran_at);
    }

    public function test_qualify_prompt_uses_search_niche(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'accountancy firm',
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
        ]);

        Http::fake([
            '*' => Http::response('<html>Local accountants</html>', 200),
        ]);

        $this->mock(OpenRouterService::class, function ($mock) {
            $mock->shouldReceive('complete')
                ->once()
                ->with(
                    \Mockery::on(fn (string $prompt) => str_contains($prompt, 'accountancy firm')),
                    \Mockery::type('string'),
                )
                ->andReturn([
                    'content' => json_encode([
                        'status' => 'qualified',
                        'summary' => 'Independent local firm.',
                        'flags' => ['Named partner visible'],
                    ]),
                    'model' => 'anthropic/claude-sonnet-4',
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                ]);
        });

        app(ProspectQualificationService::class)->qualify($prospect);
    }

    private function makeProspect(array $attributes = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        return Prospect::factory()->create(array_merge([
            'search_id' => $search->id,
        ], $attributes));
    }
}
