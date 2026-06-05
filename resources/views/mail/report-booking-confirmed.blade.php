<x-mail::message>
# You're booked

Hi {{ $booking->attendee_name }},

Your **30-minute review call** about **{{ $businessName }}** is confirmed.

**When:** {{ $booking->starts_at->timezone($settings->timezone)->format('l j F Y, g:i A T') }}

We'll walk through the audit findings from your report and outline what fixing them would involve — no obligation.

<x-mail::button :url="$reportUrl">
View your audit report
</x-mail::button>

@php
    $icsUrl = url('/r/'.$booking->report->token.'/booking.ics');
    $googleUrl = \App\Services\Booking\BookingPresentation::googleCalendarUrl($booking, $settings, $booking->report->token);
@endphp

[Add to Google Calendar]({{ $googleUrl }}) · [Download .ics]({{ $icsUrl }})

If you need to change the time, reply to this email.

Thanks,<br>
{{ $settings->confirmation_from_name ?: 'nthdesigns' }}
</x-mail::message>
