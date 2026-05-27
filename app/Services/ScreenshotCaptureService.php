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

        $result = Process::timeout(config('scanner.screenshot_timeout'))
            ->env(PlaywrightEnv::forProcess())
            ->run([
                config('scanner.node_binary'),
                base_path('scripts/screenshot.js'),
                $url,
                $localDir,
            ]);

        $output = $this->parseScriptOutput($result->output(), $result->errorOutput());

        if (!is_array($output) || !empty($output['error'])) {
            throw new \RuntimeException($output['error'] ?? 'Screenshot script failed');
        }

        $filename = $output['desktop'] ?? 'desktop.png';
        $absolutePath = $localDir.'/'.basename($filename);

        if (!is_file($absolutePath)) {
            throw new \RuntimeException('Screenshot file was not created');
        }

        return $absolutePath;
    }

    /**
     * screenshot.js writes JSON errors to stdout before exiting non-zero.
     *
     * @return array<string, mixed>|null
     */
    private function parseScriptOutput(string $stdout, string $stderr): ?array
    {
        foreach ([$stdout, $stderr] as $chunk) {
            $trimmed = trim($chunk);

            if ($trimmed === '') {
                continue;
            }

            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
