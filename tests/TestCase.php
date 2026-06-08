<?php

namespace Tests;

use App\Support\ScannerConfig;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function useAuditingDatabaseQueue(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');
        ScannerConfig::registerQueueRoutes();
    }
}
