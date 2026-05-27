<?php

namespace Tests\Unit;

use App\Support\AuditingQueue;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuditingQueueTest extends TestCase
{
    public function test_connection_uses_auditing_queue_connection_config(): void
    {
        Config::set('scanner.auditing_queue_connection', 'sqs');
        Config::set('queue.default', 'database');

        $this->assertSame('sqs', AuditingQueue::connection());
    }
}
