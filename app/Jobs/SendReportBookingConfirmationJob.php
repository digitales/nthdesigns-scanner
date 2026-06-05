<?php

namespace App\Jobs;

use App\Models\ReportBooking;
use App\Services\Booking\ReportBookingConfirmationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendReportBookingConfirmationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $bookingId,
    ) {}

    public function handle(ReportBookingConfirmationSender $sender): void
    {
        $booking = ReportBooking::query()->find($this->bookingId);

        if (! $booking || $booking->confirmation_sent_at !== null) {
            return;
        }

        $sender->send($booking);
    }
}
