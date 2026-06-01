<?php

namespace Tests\Unit;

use App\Support\QueueDispatchDelay;
use Tests\TestCase;

class QueueDispatchDelayTest extends TestCase
{
    public function test_for_index_caps_at_sqs_max(): void
    {
        $this->assertSame(0, QueueDispatchDelay::forIndex(0, 5));
        $this->assertSame(900, QueueDispatchDelay::forIndex(180, 5));
        $this->assertSame(900, QueueDispatchDelay::forIndex(200, 5));
    }

    public function test_max_jobs_per_batch(): void
    {
        $this->assertNull(QueueDispatchDelay::maxJobsPerBatch(0));
        $this->assertSame(181, QueueDispatchDelay::maxJobsPerBatch(5));
    }
}
