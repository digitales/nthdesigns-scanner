<?php

namespace Tests\Unit;

use App\Enums\IgnoredProspectReason;
use App\Enums\SuppressionSource;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\SuppressedEmail;
use App\Models\User;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProspectUnsubscribeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizes_email_for_suppression_checks(): void
    {
        $service = app(ProspectUnsubscribeService::class);

        $this->assertSame('jane@example.com', $service->normalizeEmail('  Jane@Example.COM '));
        $this->assertNull($service->normalizeEmail(null));
        $this->assertNull($service->normalizeEmail('   '));
    }

    public function test_unsubscribe_creates_suppression_and_ignores_prospect(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'place_id' => 'places/foo',
            'email' => 'owner@example.com',
        ]);

        app(ProspectUnsubscribeService::class)->unsubscribe(
            $user,
            $prospect,
            SuppressionSource::Operator,
        );

        $this->assertDatabaseHas('suppressed_emails', [
            'user_id' => $user->id,
            'email' => 'owner@example.com',
            'source' => SuppressionSource::Operator->value,
        ]);

        $this->assertDatabaseHas('ignored_prospects', [
            'user_id' => $user->id,
            'place_id' => 'places/foo',
            'reason' => IgnoredProspectReason::Unsubscribed->value,
        ]);
    }

    public function test_unsubscribe_is_idempotent(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'email' => 'owner@example.com',
        ]);

        $service = app(ProspectUnsubscribeService::class);
        $service->unsubscribe($user, $prospect, SuppressionSource::Operator);
        $service->unsubscribe($user, $prospect, SuppressionSource::SelfService);

        $this->assertSame(1, SuppressedEmail::where('user_id', $user->id)->count());
    }

    public function test_unsubscribe_removes_outreach_selections_for_matching_email(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        $prospectA = Prospect::factory()->create([
            'search_id' => $search->id,
            'place_id' => 'places/a',
            'email' => 'shared@example.com',
        ]);
        $prospectB = Prospect::factory()->create([
            'search_id' => $search->id,
            'place_id' => 'places/b',
            'email' => 'shared@example.com',
        ]);

        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $prospectA->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $prospectB->id]);

        app(ProspectUnsubscribeService::class)->unsubscribe(
            $user,
            $prospectA,
            SuppressionSource::Operator,
        );

        $this->assertDatabaseMissing('outreach_selections', ['prospect_id' => $prospectA->id]);
        $this->assertDatabaseMissing('outreach_selections', ['prospect_id' => $prospectB->id]);
    }

    public function test_signed_unsubscribe_url_uses_route_name(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'email' => 'owner@example.com',
        ]);

        $url = app(ProspectUnsubscribeService::class)->signedUnsubscribeUrl(
            $user,
            $prospect,
            'owner@example.com',
        );

        $this->assertStringContainsString('/unsubscribe?', $url);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
    }
}
