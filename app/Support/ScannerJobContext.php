<?php

namespace App\Support;

use Illuminate\Support\Facades\Context;

final class ScannerJobContext
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function add(string $job, array $context = []): void
    {
        Context::add(array_merge(
            ['job' => $job],
            array_filter($context, fn ($value) => $value !== null && $value !== ''),
        ));
    }
}
