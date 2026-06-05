<?php

namespace App\Services\Browser;

final class ViolationScreenshotMaterializer
{
    /**
     * Write violation PNGs from base64 fields and strip them from the payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function materialize(array $payload, string $localDir): array
    {
        $shots = $payload['violation_screenshots'] ?? [];

        if (! is_array($shots)) {
            return $payload;
        }

        $materialized = [];

        foreach ($shots as $shot) {
            if (! is_array($shot)) {
                continue;
            }

            $file = $shot['file'] ?? null;
            $base64 = $shot['content_base64'] ?? null;

            if (is_string($file) && is_string($base64) && $base64 !== '') {
                $decoded = base64_decode($base64, true);

                if ($decoded !== false) {
                    $path = rtrim($localDir, '/').'/'.basename($file);
                    file_put_contents($path, $decoded);
                }
            }

            unset($shot['content_base64']);
            $materialized[] = $shot;
        }

        $payload['violation_screenshots'] = $materialized;

        return $payload;
    }
}
