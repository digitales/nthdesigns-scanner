<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserMcpKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class McpKeyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', UserMcpKey::class);

        $keys = $request->user()
            ->userMcpKeys()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UserMcpKey $key) => [
                'id' => $key->id,
                'label' => $key->label ?? 'Created in scanner',
                'last_used_at' => $key->last_used_at?->toIso8601String(),
                'created_at' => $key->created_at->toIso8601String(),
            ]);

        return Inertia::render('Settings/McpKeys', [
            'keys' => $keys,
            'newKey' => $request->session()->pull('new_mcp_key'),
            'mcpUrl' => url('/api/mcp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', UserMcpKey::class);

        $validated = $request->validate([
            'label' => 'nullable|string|max:64',
        ]);

        $plainKey = 'scanner_'.Str::random(32);

        $request->user()->userMcpKeys()->create([
            'key_hash' => UserMcpKey::hashKey($plainKey),
            'label' => $this->resolvedLabel($validated['label'] ?? null),
        ]);

        return redirect()
            ->route('settings.mcp-keys.index')
            ->with('new_mcp_key', $plainKey);
    }

    public function update(Request $request, UserMcpKey $mcpKey): RedirectResponse
    {
        $this->authorize('update', $mcpKey);

        $field = 'label_'.$mcpKey->id;
        $validated = $request->validate([
            $field => 'nullable|string|max:64',
        ]);

        $mcpKey->update([
            'label' => $this->resolvedLabel($validated[$field] ?? null),
        ]);

        return redirect()
            ->route('settings.mcp-keys.index')
            ->with('success', 'Label updated.');
    }

    public function destroy(Request $request, UserMcpKey $mcpKey): RedirectResponse
    {
        $this->authorize('delete', $mcpKey);

        $mcpKey->delete();

        return redirect()
            ->route('settings.mcp-keys.index')
            ->with('success', 'MCP key revoked. Clients using it will need a new key.');
    }

    private function resolvedLabel(?string $label): string
    {
        $trimmed = trim((string) ($label ?? ''));

        return $trimmed === '' ? 'Created in scanner' : $trimmed;
    }
}
