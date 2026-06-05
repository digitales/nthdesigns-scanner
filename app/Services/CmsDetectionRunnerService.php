<?php

namespace App\Services;

use App\Support\PlaywrightEnv;
use Illuminate\Support\Facades\Process;

class CmsDetectionRunnerService
{
    public function __construct(
        private BrowserServiceClient $browserService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(string $url): array
    {
        return match (config('scanner.cms_detect_driver')) {
            'http' => $this->browserService->fetchCmsDetection($url),
            default => $this->runPlaywright($url),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function runPlaywright(string $url): array
    {
        $result = Process::timeout((int) config('scanner.cms_detect_timeout', 90))
            ->env(PlaywrightEnv::forProcess())
            ->run([
                config('scanner.node_binary'),
                config('scanner.cms_detect_script_path'),
                $url,
            ]);

        if (! $result->successful()) {
            $output = trim($result->output());

            if ($output !== '') {
                $payload = json_decode($output, true);

                if (is_array($payload)) {
                    return $payload;
                }
            }

            throw new \RuntimeException(
                'CMS detect script failed: '.trim($result->errorOutput() ?: $result->output())
            );
        }

        $payload = json_decode($result->output(), true);

        if (! is_array($payload)) {
            throw new \RuntimeException('CMS detect script returned invalid JSON');
        }

        return $payload;
    }
}
