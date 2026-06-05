<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateApiQuotaSettingsRequest;
use App\Services\ApiUsage\ApiQuotaSettingsService;
use Illuminate\Http\RedirectResponse;

class ApiQuotaSettingsController extends Controller
{
    public function update(UpdateApiQuotaSettingsRequest $request, ApiQuotaSettingsService $quotas): RedirectResponse
    {
        $settings = $quotas->settings();
        $this->authorize('update', $settings);

        $quotas->update($request->normalizedOverrides());

        return back()->with('success', 'API quota limits saved.');
    }
}
