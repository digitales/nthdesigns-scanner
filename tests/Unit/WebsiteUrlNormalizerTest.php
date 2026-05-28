<?php

namespace Tests\Unit;

use App\Support\WebsiteUrlNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteUrlNormalizerTest extends TestCase
{
    private WebsiteUrlNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new WebsiteUrlNormalizer();
    }

    #[DataProvider('normalizeProvider')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    public static function normalizeProvider(): array
    {
        return [
            'bare domain'           => ['example.com', 'https://example.com'],
            'https with www'        => ['https://www.example.com', 'https://example.com'],
            'http with path'        => ['http://www.example.com/about', 'http://example.com/about'],
            'https with path'       => ['https://example.com/about/', 'https://example.com/about'],
        ];
    }

    public function test_host_strips_www(): void
    {
        $this->assertSame('example.com', $this->normalizer->host('https://www.example.com/foo'));
    }

    public function test_display_name_from_url(): void
    {
        $name = $this->normalizer->displayNameFromUrl('https://birminghamdentalpractice.co.uk');
        $this->assertSame('Birminghamdentalpractice', $name);
    }

    public function test_rejects_non_http_scheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize('javascript:alert(1)');
    }
}
