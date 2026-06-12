<?php

namespace App\Http\Resources;

use App\Models\OutreachEmail;

class OutreachEmailResource
{
    public static function format(OutreachEmail $email): array
    {
        return [
            'id' => $email->id,
            'to_email' => $email->prospect?->email,
            'pitch_angle' => $email->pitch_angle,
            'subject_line' => $email->subject_line,
            'email_body' => $email->email_body,
            'sent_at' => $email->sent_at?->toISOString(),
            'response_received' => $email->response_received,
            'created_at' => $email->created_at->diffForHumans(),
        ];
    }
}
