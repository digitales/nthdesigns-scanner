<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_url_unsubscribes_prospect(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'place_id' => 'places/foo',
            'email' => 'owner@example.com',
        ]);

        $url = app(ProspectUnsubscribeService::class)->signedUnsubscribeUrl(
            $user,
            $prospect,
            'owner@example.com',
        );

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Unsubscribe')
                ->where('success', true));

        $this->assertDatabaseHas('suppressed_emails', [
            'user_id' => $user->id,
            'email' => 'owner@example.com',
        ]);
    }

    public function test_signed_url_is_idempotent_on_revisit(): void
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

        $this->get($url)->assertOk();
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('success', true));

        $this->assertSame(1, \App\Models\SuppressedEmail::count());
    }

    public function test_invalid_signature_returns_error_page(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'email' => 'owner@example.com',
        ]);

        $this->get('/unsubscribe?prospect='.$prospect->id.'&email=owner@example.com')
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Unsubscribe')
                ->where('success', false));
    }
}
