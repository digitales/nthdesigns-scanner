<?php

namespace App\Console\Commands;

use App\Jobs\SendReportBookingConfirmationJob;
use App\Models\ReportBooking;
use Illuminate\Console\Command;

class RetryUnsentBookingConfirmations extends Command
{
    protected $signature = 'booking:retry-unsent-confirmations';

    protected $description = 'Queue confirmation emails for confirmed bookings that never received one';

    public function handle(): int
    {
        $bookings = ReportBooking::query()
            ->where('status', 'confirmed')
            ->whereNull('confirmation_sent_at')
            ->where('created_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->get();

        foreach ($bookings as $booking) {
            SendReportBookingConfirmationJob::dispatch($booking);
        }

        $this->info('Queued '.$bookings->count().' unsent booking confirmation(s).');

        return self::SUCCESS;
    }
}
