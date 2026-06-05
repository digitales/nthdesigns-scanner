<?php

namespace App\Mail;

use App\Models\AgencyBookingSetting;
use App\Models\ReportBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportBookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ReportBooking $booking,
        public string $businessName,
        public string $reportUrl,
        public AgencyBookingSetting $settings,
    ) {}

    public function envelope(): Envelope
    {
        $fromEmail = $this->settings->confirmation_from_email
            ?: $this->settings->fastmail_username
            ?: config('mail.from.address');
        $fromName = $this->settings->confirmation_from_name ?: 'nthdesigns';

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Your review call is booked — '.$this->businessName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.report-booking-confirmed',
        );
    }
}
