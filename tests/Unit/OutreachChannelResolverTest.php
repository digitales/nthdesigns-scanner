<?php

namespace Tests\Unit;

use App\Enums\ProspectOutreachChannel;
use App\Enums\UseFormOutreach;
use App\Models\Prospect;
use App\Services\Outreach\OutreachChannelResolver;
use Tests\TestCase;

class OutreachChannelResolverTest extends TestCase
{
    public function test_email_takes_priority_when_present_and_auto(): void
    {
        $prospect = new Prospect([
            'email' => 'owner@example.com',
            'outreach_channel' => ProspectOutreachChannel::Auto,
            'use_form_outreach' => UseFormOutreach::Auto,
            'contact_signals' => ['has_contact_form' => true, 'suggested_emails' => []],
        ]);

        $channels = app(OutreachChannelResolver::class)->channelsFor($prospect);

        $this->assertSame(['email'], array_map(fn ($channel) => $channel->value, $channels));
    }

    public function test_form_and_linkedin_when_no_email_and_form_confirmed(): void
    {
        $prospect = new Prospect([
            'email' => null,
            'outreach_channel' => ProspectOutreachChannel::Auto,
            'use_form_outreach' => UseFormOutreach::Auto,
            'contact_signals' => ['has_contact_form' => true, 'suggested_emails' => []],
        ]);

        $channels = app(OutreachChannelResolver::class)->channelsFor($prospect);

        $this->assertSame(['contact_form', 'linkedin'], array_map(fn ($channel) => $channel->value, $channels));
    }

    public function test_alternative_channel_overrides_email(): void
    {
        $prospect = new Prospect([
            'email' => 'owner@example.com',
            'outreach_channel' => ProspectOutreachChannel::Alternative,
            'use_form_outreach' => UseFormOutreach::Auto,
        ]);

        $channels = app(OutreachChannelResolver::class)->channelsFor($prospect);

        $this->assertSame(['contact_form', 'linkedin'], array_map(fn ($channel) => $channel->value, $channels));
    }

    public function test_no_channels_when_form_path_blocked(): void
    {
        $prospect = new Prospect([
            'email' => null,
            'outreach_channel' => ProspectOutreachChannel::Auto,
            'use_form_outreach' => UseFormOutreach::No,
            'contact_signals' => ['has_contact_form' => true, 'suggested_emails' => []],
        ]);

        $channels = app(OutreachChannelResolver::class)->channelsFor($prospect);

        $this->assertSame([], $channels);
    }
}
