<?php

namespace App\Jobs;

use App\Models\ReportBooking;
use App\Services\Booking\ReportBookingConfirmationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\WithoutRelations;

#[Tries(3)]
#[DeleteWhenMissingModels]
class SendReportBookingConfirmationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[WithoutRelations]
        public ReportBooking $booking,
    ) {}

    public function handle(ReportBookingConfirmationSender $sender): void
    {
        $booking = $this->booking->fresh();

        if (! $booking || $booking->confirmation_sent_at !== null) {
            return;
        }

        $sender->send($booking);
    }
}
