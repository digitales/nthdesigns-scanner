<?php

namespace App\Http\Resources;

use App\Enums\OutreachChannel;
use App\Models\OutreachEmail;

class OutreachEmailResource
{
    public static function format(OutreachEmail $email): array
    {
        $prospect = $email->prospect;
        $channel = $email->channel instanceof OutreachChannel
            ? $email->channel
            : OutreachChannel::tryFrom($email->getAttributes()['channel'] ?? 'email') ?? OutreachChannel::Email;

        return [
            'id' => $email->id,
            'channel' => $channel->value,
            'channel_label' => $channel->label(),
            'to_email' => $prospect?->email,
            'contact_page_url' => $prospect?->contact_page_url,
            'linkedin_url' => $prospect?->linkedin_url,
            'pitch_angle' => $email->pitch_angle,
            'subject_line' => $email->subject_line,
            'email_body' => $email->email_body,
            'generated_subject' => $email->generated_subject,
            'generated_body' => $email->generated_body,
            'sent_subject' => $email->sent_subject,
            'sent_body' => $email->sent_body,
            'send_source' => $email->send_source?->value,
            'from_mailbox_email' => $email->fromMailbox?->email,
            'was_edited' => $email->wasEditedBeforeSend(),
            'sent_at' => $email->sent_at?->toISOString(),
            'response_received' => $email->response_received,
            'created_at' => $email->created_at->diffForHumans(),
        ];
    }
}
