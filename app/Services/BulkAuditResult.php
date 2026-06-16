<?php

namespace App\Services;

final readonly class BulkAuditResult
{
    public function __construct(
        public int $queued,
        public int $skippedPending,
        public int $skippedNoUrl,
        public int $skippedNotFailed,
        public int $notQueuedDueToCap,
    ) {}

    public function flashMessage(): string
    {
        $message = $this->queued > 0
            ? 'Queued '.$this->queued.' site audit'.($this->queued === 1 ? '' : 's').'.'
            : 'No site audits queued.';

        $skippedTotal = $this->skippedPending + $this->skippedNoUrl + $this->skippedNotFailed;

        if ($skippedTotal > 0) {
            $reasons = [];

            if ($this->skippedPending > 0) {
                $reasons[] = $this->skippedPending.' pending';
            }

            if ($this->skippedNoUrl > 0) {
                $reasons[] = $this->skippedNoUrl.' no website';
            }

            if ($this->skippedNotFailed > 0) {
                $reasons[] = $this->skippedNotFailed.' not failed';
            }

            $message .= ' Skipped '.$skippedTotal.' ('.implode(', ', $reasons).').';
        }

        if ($this->notQueuedDueToCap > 0) {
            $message .= ' '.$this->notQueuedDueToCap.' not queued — run again to queue the rest.';
        }

        return $message;
    }
}
