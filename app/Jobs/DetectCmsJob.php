<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Services\CmsDetectionRunnerService;
use App\Support\ScannerJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Tries(2)]
#[Timeout(120)]
class DetectCmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 30;

    public function __construct(
        #[WithoutRelations]
        public Prospect $prospect,
        public bool $force = false,
    ) {}

    public function tries(): int
    {
        return 2;
    }

    public function handle(CmsDetectionRunnerService $runner): void
    {
        ScannerJobContext::add(self::class, ['prospect_id' => $this->prospect->id]);

        $prospect = $this->prospect->fresh();

        if (! $prospect || empty($prospect->website_url)) {
            return;
        }

        if (! $this->force && $this->alreadyDetected($prospect)) {
            return;
        }

        try {
            $payload = $runner->run($prospect->website_url);
            $prospect->update(['cms_detection' => $payload]);
        } catch (\Throwable $e) {
            Log::warning('DetectCmsJob failed', [
                'prospect_id' => $prospect->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries()) {
                $prospect->update([
                    'cms_detection' => [
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'failed_at' => now()->toIso8601String(),
                        'url' => $prospect->website_url,
                    ],
                ]);
            }

            throw $e;
        }
    }

    private function alreadyDetected(Prospect $prospect): bool
    {
        $stored = $prospect->cms_detection;

        if (! is_array($stored) || empty($stored['url'])) {
            return false;
        }

        return $this->normalizeUrl((string) $stored['url']) === $this->normalizeUrl((string) $prospect->website_url);
    }

    private function normalizeUrl(string $url): string
    {
        return Str::lower(rtrim($url, '/'));
    }
}
