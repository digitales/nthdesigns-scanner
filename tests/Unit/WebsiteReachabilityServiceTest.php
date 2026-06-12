<?php

namespace Tests\Unit;

use App\Services\WebsiteReachabilityService;
use App\Support\ReachabilityResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteReachabilityServiceTest extends TestCase
{
    public function test_returns_reachable_on_successful_response(): void
    {
        Http::fake([
            'https://example.com' => Http::response('ok', 200),
        ]);

        $result = app(WebsiteReachabilityService::class)->check('https://example.com');

        $this->assertTrue($result->isReachable());
    }

    public function test_treats_client_errors_as_reachable(): void
    {
        Http::fake([
            'https://example.com' => Http::response('missing', 404),
        ]);

        $result = app(WebsiteReachabilityService::class)->check('https://example.com');

        $this->assertTrue($result->isReachable());
    }

    public function test_permanent_dns_failure_does_not_retry(): void
    {
        config(['scanner.site_preflight_retries' => 2]);

        Http::fake(function () {
            throw new ConnectionException('cURL error 6: Could not resolve host: dead.example');
        });

        $result = app(WebsiteReachabilityService::class)->check('https://dead.example');

        $this->assertFalse($result->isReachable());
        $this->assertTrue($result->permanent);
    }

    public function test_transient_server_error_retries_then_fails(): void
    {
        config(['scanner.site_preflight_retries' => 2]);

        Http::fake([
            'https://flaky.example' => Http::sequence()
                ->push('error', 503)
                ->push('error', 503)
                ->push('error', 503),
        ]);

        $result = app(WebsiteReachabilityService::class)->check('https://flaky.example');

        $this->assertInstanceOf(ReachabilityResult::class, $result);
        $this->assertFalse($result->isReachable());
        $this->assertFalse($result->permanent);
        Http::assertSentCount(3);
    }

    public function test_skips_check_when_preflight_disabled(): void
    {
        config(['scanner.site_preflight_enabled' => false]);

        Http::fake();

        $result = app(WebsiteReachabilityService::class)->check('https://dead.example');

        $this->assertTrue($result->isReachable());
        Http::assertNothingSent();
    }
}
