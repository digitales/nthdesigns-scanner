<?php

namespace App\Services;

use App\Support\PlaywrightEnv;
use Illuminate\Support\Facades\Process;

class ScreenshotCaptureService
{
    public function __construct(
        private CloudflareBrowserService $cloudflare,
        private BrowserServiceClient $browserService,
    ) {}

    /**
     * Capture a desktop screenshot and return the absolute path to the PNG file.
     */
    public function captureDesktop(string $url, string $localDir): string
    {
        $outputPath = $localDir.'/desktop.png';

        if (config('scanner.screenshot_driver') === 'cloudflare') {
            $this->cloudflare->captureScreenshot($url, $outputPath);

            return $outputPath;
        }

        if (config('scanner.screenshot_driver') === 'http') {
            return $this->browserService->captureDesktop($url, $localDir);
        }

        $result = Process::timeout(90)
            ->env(PlaywrightEnv::forProcess())
            ->run([
                config('scanner.node_binary'),
                base_path('scripts/screenshot.js'),
                $url,
                $localDir,
            ]);

        if (!$result->successful()) {
            throw new \RuntimeException(trim($result->errorOutput() ?: $result->output()));
        }

        $output = json_decode($result->output(), true);

        if (!is_array($output) || !empty($output['error'])) {
            throw new \RuntimeException($output['error'] ?? 'Screenshot script returned invalid JSON');
        }

        $filename = $output['desktop'] ?? 'desktop.png';
        $absolutePath = $localDir.'/'.basename($filename);

        if (!is_file($absolutePath)) {
            throw new \RuntimeException('Screenshot file was not created');
        }

        return $absolutePath;
    }
}
