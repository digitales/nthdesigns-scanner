<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OauthMcpRefreshTokenFamily;
use App\Services\OAuthMcpRefreshTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConnectedAppsController extends Controller
{
    public function __construct(private OAuthMcpRefreshTokenService $refreshTokens) {}

    public function index(Request $request): Response
    {
        $families = OauthMcpRefreshTokenFamily::active()
            ->where('user_id', $request->user()->id)
            ->with('client')
            ->orderByDesc('last_used_at')
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn (OauthMcpRefreshTokenFamily $family) => [
                'id' => $family->id,
                'scope' => $family->scope ?? config('oauth-mcp.scope'),
                'redirect_host' => parse_url($family->client->redirect_uris[0] ?? '', PHP_URL_HOST)
                    ?: substr($family->client_id, 0, 16),
                'issued_at' => $family->issued_at->toIso8601String(),
                'absolute_expires_at' => $family->absolute_expires_at->toIso8601String(),
                'last_used_at' => $family->last_used_at?->toIso8601String(),
                'ip_address' => $family->ip_address,
                'user_agent' => $family->user_agent,
            ]);

        return Inertia::render('Settings/ConnectedApps', [
            'families' => $families,
        ]);
    }

    public function destroy(Request $request, OauthMcpRefreshTokenFamily $family): RedirectResponse
    {
        $this->authorize('delete', $family);

        $this->refreshTokens->revokeFamily($family, 'user');

        return redirect()
            ->route('settings.connected-apps.index')
            ->with('success', 'App disconnected.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $count = 0;
        OauthMcpRefreshTokenFamily::active()
            ->where('user_id', $request->user()->id)
            ->get()
            ->each(function (OauthMcpRefreshTokenFamily $family) use (&$count) {
                $this->refreshTokens->revokeFamily($family, 'user');
                $count++;
            });

        $message = $count === 1
            ? '1 connected app disconnected.'
            : "{$count} connected apps disconnected.";

        return redirect()
            ->route('settings.connected-apps.index')
            ->with('success', $message);
    }
}
