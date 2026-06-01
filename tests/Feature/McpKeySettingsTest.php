<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserMcpKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpKeySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_keys_page_requires_auth(): void
    {
        $this->get(route('settings.mcp-keys.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_mcp_key(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('settings.mcp-keys.store'));

        $response->assertRedirect(route('settings.mcp-keys.index'));
        $response->assertSessionHas('new_mcp_key');

        $plainKey = $response->getSession()->get('new_mcp_key');
        $this->assertStringStartsWith('scanner_', $plainKey);

        $this->assertDatabaseHas('user_mcp_keys', [
            'user_id' => $user->id,
            'label' => 'Created in scanner',
        ]);
    }

    public function test_user_can_revoke_own_key(): void
    {
        $user = User::factory()->create();
        $plain = 'scanner_'.str_repeat('a', 32);
        $key = $user->userMcpKeys()->create([
            'key_hash' => UserMcpKey::hashKey($plain),
            'label' => 'Test',
        ]);

        $this->actingAs($user)
            ->delete(route('settings.mcp-keys.destroy', $key))
            ->assertRedirect(route('settings.mcp-keys.index'));

        $this->assertDatabaseMissing('user_mcp_keys', ['id' => $key->id]);
    }

    public function test_mcp_accepts_scanner_key_header(): void
    {
        $user = User::factory()->create();
        $plain = 'scanner_'.str_repeat('b', 32);
        UserMcpKey::query()->create([
            'user_id' => $user->id,
            'key_hash' => UserMcpKey::hashKey($plain),
        ]);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list_searches',
            'params' => [],
        ], [
            'x-scanner-key' => $plain,
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonPath('result.searches', []);
    }
}
