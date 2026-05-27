<?php

namespace Tests\Unit;

use App\Support\ReportsDiskConfig;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReportsDiskConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['LARAVEL_CLOUD_DISK_CONFIG']);

        parent::tearDown();
    }

    public function test_uses_filesystem_default_when_reports_disk_s3_has_no_bucket(): void
    {
        Config::set('scanner.reports_disk', 's3');
        Config::set('filesystems.disks.s3.bucket', null);
        Config::set('filesystems.default', 'r2');
        Config::set('filesystems.disks.r2.bucket', 'cloud-bucket');

        ReportsDiskConfig::applyRuntimeOverrides();

        $this->assertSame('r2', config('scanner.reports_disk'));
    }

    public function test_uses_laravel_cloud_default_disk_from_server_config(): void
    {
        Config::set('scanner.reports_disk', 's3');
        Config::set('filesystems.disks.s3.bucket', null);
        Config::set('filesystems.default', 'local');

        $_SERVER['LARAVEL_CLOUD_DISK_CONFIG'] = json_encode([
            [
                'disk' => 'reports',
                'access_key_id' => 'key',
                'access_key_secret' => 'secret',
                'bucket' => 'my-bucket',
                'url' => 'https://example.com',
                'endpoint' => 'https://r2.example.com',
                'is_default' => true,
            ],
        ]);

        Config::set('filesystems.disks.reports.bucket', 'my-bucket');

        ReportsDiskConfig::applyRuntimeOverrides();

        $this->assertSame('reports', config('scanner.reports_disk'));
    }

    public function test_leaves_config_when_reports_disk_already_has_bucket(): void
    {
        Config::set('scanner.reports_disk', 's3');
        Config::set('filesystems.disks.s3.bucket', 'existing');

        ReportsDiskConfig::applyRuntimeOverrides();

        $this->assertSame('s3', config('scanner.reports_disk'));
    }
}
