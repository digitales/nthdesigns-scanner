<?php

namespace App\Services;

use App\Models\AuditJob;
use App\Models\AuditJobErrorDetail;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditErrorRecorder
{
    public const int MAX_BODY_BYTES = 32_768;

    public const int MAX_SUMMARY_CHARS = 255;

    public function recordFailure(AuditJob $auditJob, string $fullBody): void
    {
        $fullBody = trim($fullBody);

        if ($fullBody === '') {
            $fullBody = 'Audit failed';
        }

        if (strlen($fullBody) > self::MAX_BODY_BYTES) {
            Log::warning('Audit error body truncated for storage', [
                'audit_job_id' => $auditJob->id,
                'prospect_id' => $auditJob->prospect_id,
                'bytes' => strlen($fullBody),
            ]);
            $fullBody = substr($fullBody, 0, self::MAX_BODY_BYTES);
        }

        $summary = $this->summarize($fullBody);

        $auditJob->update(['error_message' => $summary]);

        AuditJobErrorDetail::query()->updateOrCreate(
            ['audit_job_id' => $auditJob->id],
            [
                'body' => $fullBody,
                'created_at' => now(),
            ],
        );
    }

    public function summarize(string $fullBody): string
    {
        $line = strtok($fullBody, "\n\r");

        if ($line === false || trim($line) === '') {
            return 'Audit failed';
        }

        $line = trim($line);

        if (strlen($line) > self::MAX_SUMMARY_CHARS) {
            return substr($line, 0, self::MAX_SUMMARY_CHARS);
        }

        return $line;
    }

    public function formatThrowable(Throwable $e): string
    {
        $parts = [trim($e->getMessage())];

        $previous = $e->getPrevious();

        while ($previous !== null) {
            $message = trim($previous->getMessage());

            if ($message !== '' && ! in_array($message, $parts, true)) {
                $parts[] = $message;
            }

            $previous = $previous->getPrevious();
        }

        return implode("\n\n", array_filter($parts)) ?: 'Audit failed';
    }

    public function formatProcessOutput(string $stderr, string $stdout): string
    {
        $stderr = trim($stderr);
        $stdout = trim($stdout);

        if ($stderr !== '' && $stdout !== '' && $stderr !== $stdout) {
            return "Audit script failed:\n{$stderr}\n\n{$stdout}";
        }

        $combined = $stderr !== '' ? $stderr : $stdout;

        return $combined !== '' ? 'Audit script failed: '.$combined : 'Audit script failed';
    }
}
