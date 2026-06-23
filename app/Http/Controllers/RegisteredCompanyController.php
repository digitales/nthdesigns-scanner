<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegisteredCompanyRequest;
use App\Jobs\CheckCompaniesHouseJob;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;

class RegisteredCompanyController extends Controller
{
    public function store(StoreRegisteredCompanyRequest $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('view', $prospect);

        $validated = $request->validated();
        $shouldCheck = $prospect->companies_house_checked_at === null;

        $prospect->update([
            'registered_company_name' => $validated['name'],
            'registered_company_number' => $validated['number'],
            'registered_company_note' => $validated['note'],
            'registered_company_by' => $request->user()->id,
            'registered_company_at' => now(),
            'registered_company_cleared_by' => null,
            'registered_company_cleared_at' => null,
        ]);

        if ($shouldCheck) {
            CheckCompaniesHouseJob::dispatch($prospect->fresh());
        }

        return back()->with('success', $shouldCheck
            ? 'Registered company saved — Companies House check queued.'
            : 'Registered company saved.');
    }

    public function destroy(Prospect $prospect): RedirectResponse
    {
        $this->authorize('view', $prospect);

        $prospect->update([
            'registered_company_name' => null,
            'registered_company_number' => null,
            'registered_company_note' => null,
            'registered_company_by' => null,
            'registered_company_at' => null,
            'registered_company_cleared_by' => auth()->id(),
            'registered_company_cleared_at' => now(),
        ]);

        return back()->with('success', 'Registered company cleared.');
    }
}
