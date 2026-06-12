<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportProspectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_includes_email_column(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Acme Dental',
            'email' => 'hello@acme.test',
        ]);

        $response = $this->actingAs($user)->post('/exports');

        $content = $response->streamedContent();
        $this->assertStringContainsString('email', $content);
        $this->assertStringContainsString('hello@acme.test', $content);
    }

    public function test_export_streams_csv_and_creates_export_record(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Acme Dental',
        ]);

        $response = $this->actingAs($user)->post('/exports');

        $response->assertOk();
        $this->assertStringContainsString('Acme Dental', $response->streamedContent());
        $this->assertDatabaseHas('exports', ['user_id' => $user->id, 'row_count' => 1]);
    }

    public function test_export_returns_error_when_no_rows(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/exports')
            ->assertRedirect()
            ->assertSessionHasErrors('export');
    }

    public function test_export_rejects_invalid_scan_type_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/exports', ['scan_type' => 'invalid'])
            ->assertSessionHasErrors('scan_type');
    }
}
