<?php

namespace App\Jobs;

use App\Models\ReportBooking;
use App\Services\Booking\ReportBookingConfirmationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
class SendReportBookingConfirmationJob implements ShouldQueue
{
    use Queueable;

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
