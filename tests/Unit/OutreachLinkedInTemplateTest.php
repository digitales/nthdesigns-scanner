<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Services\Outreach\OutreachLinkedInTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachLinkedInTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_template_under_300_characters(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Acme Dental',
            'dominant_angle' => 'gbp',
        ]);
        $report = ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $result = app(OutreachLinkedInTemplateService::class)->render(
            $prospect,
            $report,
            ['agency_name' => 'nthdesigns', 'pitch_angle' => 'gbp'],
        );

        $this->assertStringContainsString('Acme Dental', $result['email_body']);
        $this->assertLessThanOrEqual(300, strlen($result['email_body']));
        $this->assertSame('gbp', $result['pitch_angle']);
    }
}
