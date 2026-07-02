<?php

namespace App\Services\Outreach;

final readonly class OutreachSendReadiness
{
    public function __construct(
        public string $tier,
        public string $reason,
        public bool $requiresConfirmation,
    ) {}
}
