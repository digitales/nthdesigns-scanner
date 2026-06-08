<?php

namespace Tests\Unit;

use App\Support\PlaywrightScripts;
use Tests\TestCase;

class PlaywrightScriptsTest extends TestCase
{
    public function test_dependencies_installed_when_playwright_package_present(): void
    {
        if (! is_dir(base_path('scripts/node_modules/playwright'))) {
            $this->markTestSkipped('scripts/node_modules/playwright not installed');
        }

        $this->assertTrue(PlaywrightScripts::dependenciesInstalled());
    }

    public function test_missing_dependencies_message_includes_cloud_guidance(): void
    {
        $message = PlaywrightScripts::missingDependenciesMessage();

        $this->assertStringContainsString('scripts/node_modules/playwright missing', $message);
        $this->assertStringContainsString('AUDIT_SERVICE_URL', $message);
        $this->assertStringContainsString('docs/deployment/laravel-cloud.md', $message);
    }
}
