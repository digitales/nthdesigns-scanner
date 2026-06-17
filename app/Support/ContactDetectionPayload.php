<?php

namespace App\Support;

use App\Models\Prospect;

final class ContactDetectionPayload
{
    /**
     * @param  array<string, mixed>  $auditPayload
     * @return array<string, mixed>|null
     */
    public static function fromAuditPayload(array $auditPayload): ?array
    {
        $contact = $auditPayload['contact'] ?? null;

        if (! is_array($contact) || ! isset($contact['status'])) {
            return null;
        }

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $wrappedPayload  detect-cms.js output
     * @return array{cms: array<string, mixed>|null, contact: array<string, mixed>|null}
     */
    public static function splitSiteMetadataPayload(array $wrappedPayload): array
    {
        if (isset($wrappedPayload['cms']) || isset($wrappedPayload['contact'])) {
            return [
                'cms' => is_array($wrappedPayload['cms'] ?? null) ? $wrappedPayload['cms'] : null,
                'contact' => is_array($wrappedPayload['contact'] ?? null) ? $wrappedPayload['contact'] : null,
            ];
        }

        return [
            'cms' => isset($wrappedPayload['platform']) ? $wrappedPayload : null,
            'contact' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public static function prospectUpdatesFromDetection(Prospect $prospect, array $contact): array
    {
        $updates = ['contact_signals' => $contact];

        if (empty($prospect->contact_page_url) && ! empty($contact['contact_page_url'])) {
            $updates['contact_page_url'] = $contact['contact_page_url'];
        }

        if (empty($prospect->linkedin_url) && ! empty($contact['linkedin_url'])) {
            $updates['linkedin_url'] = $contact['linkedin_url'];
        }

        return $updates;
    }
}
