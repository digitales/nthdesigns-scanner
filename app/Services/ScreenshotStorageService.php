<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class ScreenshotStorageService
{
    public function diskName(): string
    {
        return config('scanner.reports_disk', 'public');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function storeLocalFile(string $relativePath, string $absoluteLocalPath): string
    {
        $this->disk()->put($relativePath, file_get_contents($absoluteLocalPath));

        return $this->url($relativePath);
    }

    public function url(string $relativePath): string
    {
        return $this->disk()->url($relativePath);
    }

    public function deleteDirectory(string $relativePath): void
    {
        if ($this->disk()->exists($relativePath)) {
            $this->disk()->deleteDirectory($relativePath);
        }
    }

    /**
     * @param  list<array{violation_id: string, index: int, file: string}>  $screenshots
     * @return list<array{violation_id: string, index: int, url: string}>
     */
    public function storeViolationScreenshots(int $prospectId, array $screenshots, string $localDir): array
    {
        $stored = [];

        foreach ($screenshots as $shot) {
            $localPath = rtrim($localDir, '/').'/'.$shot['file'];

            if (!is_file($localPath)) {
                continue;
            }

            $relative = "prospects/{$prospectId}/violations/{$shot['file']}";

            $stored[] = [
                'violation_id' => $shot['violation_id'],
                'index'        => $shot['index'],
                'url'          => $this->storeLocalFile($relative, $localPath),
            ];
        }

        return $stored;
    }
}
