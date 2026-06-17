<?php

namespace App\Services\Outreach;

use App\Enums\OutreachChannel;
use App\Enums\ProspectOutreachChannel;
use App\Enums\UseFormOutreach;
use App\Models\Prospect;

class OutreachChannelResolver
{
    /**
     * @return list<OutreachChannel>
     */
    public function channelsFor(Prospect $prospect): array
    {
        $preference = $this->resolvePreference($prospect);
        $hasEmail = filled($prospect->email);

        if ($preference === ProspectOutreachChannel::Email) {
            return $hasEmail ? [OutreachChannel::Email] : [];
        }

        if ($preference === ProspectOutreachChannel::Alternative) {
            return [OutreachChannel::ContactForm, OutreachChannel::Linkedin];
        }

        if ($hasEmail) {
            return [OutreachChannel::Email];
        }

        if ($this->isFormPathConfirmed($prospect)) {
            return [OutreachChannel::ContactForm, OutreachChannel::Linkedin];
        }

        return [];
    }

    public function isFormPathConfirmed(Prospect $prospect): bool
    {
        $useForm = $this->resolveUseFormOutreach($prospect);

        if ($useForm === UseFormOutreach::No) {
            return false;
        }

        if ($useForm === UseFormOutreach::Yes) {
            return true;
        }

        $signals = $prospect->contact_signals;

        if (! is_array($signals) || ! ($signals['has_contact_form'] ?? false)) {
            return false;
        }

        return empty($signals['suggested_emails'] ?? []);
    }

    private function resolvePreference(Prospect $prospect): ProspectOutreachChannel
    {
        $value = $prospect->outreach_channel;

        if ($value instanceof ProspectOutreachChannel) {
            return $value;
        }

        return ProspectOutreachChannel::tryFrom((string) $value) ?? ProspectOutreachChannel::Auto;
    }

    private function resolveUseFormOutreach(Prospect $prospect): UseFormOutreach
    {
        $value = $prospect->use_form_outreach;

        if ($value instanceof UseFormOutreach) {
            return $value;
        }

        return UseFormOutreach::tryFrom((string) $value) ?? UseFormOutreach::Auto;
    }
}
