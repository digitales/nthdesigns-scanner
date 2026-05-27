<?php

namespace App\Support;

/**
 * Resolve the reports filesystem disk at runtime on Laravel Cloud.
 *
 * Cloud injects buckets via LARAVEL_CLOUD_DISK_CONFIG (custom disk name), not always
 * via AWS_* on the stock config/filesystems.php "s3" disk. REPORTS_DISK=s3 then
 * points at a disk with a null bucket. See docs/deployment/laravel-cloud.md.
 */
final class ReportsDiskConfig
{
    public static function applyRuntimeOverrides(): void
    {
        $reportsDisk = (string) config('scanner.reports_disk', 'public');

        if (self::diskHasBucket($reportsDisk)) {
            return;
        }

        $default = (string) config('filesystems.default', 'local');

        if (self::diskHasBucket($default)) {
            config(['scanner.reports_disk' => $default]);

            return;
        }

        $cloudDisk = self::defaultCloudDiskName();

        if ($cloudDisk !== null) {
            config(['scanner.reports_disk' => $cloudDisk]);
        }
    }

    private static function diskHasBucket(string $disk): bool
    {
        $bucket = config("filesystems.disks.{$disk}.bucket");

        return is_string($bucket) && $bucket !== '';
    }

    private static function defaultCloudDiskName(): ?string
    {
        $json = $_SERVER['LARAVEL_CLOUD_DISK_CONFIG'] ?? env('LARAVEL_CLOUD_DISK_CONFIG');

        if (! is_string($json) || $json === '') {
            return null;
        }

        $disks = json_decode($json, true);

        if (! is_array($disks)) {
            return null;
        }

        foreach ($disks as $disk) {
            if (($disk['is_default'] ?? false) && isset($disk['disk'])) {
                return $disk['disk'];
            }
        }

        return $disks[0]['disk'] ?? null;
    }
}
