<?php

use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\OutreachController;
use App\Http\Controllers\OutreachEmailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\ReportDashboardController;
use App\Http\Controllers\SavedProspectController;
use App\Http\Controllers\NicheScanController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('search.index');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('search.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/r/{token}', [PublicReportController::class, 'show'])->name('reports.public');

Route::get('/admin/horizon', function () {
    return redirect('/horizon');
})->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/niches', [NicheScanController::class, 'index'])->name('niches.index');
    Route::post('/niches/scan', [NicheScanController::class, 'trigger'])->name('niches.scan');

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::post('/searches', [SearchController::class, 'store'])->name('searches.store');
    Route::get('/searches/{search}', [SearchController::class, 'show'])->name('searches.show');

    Route::get('/saved', [SavedProspectController::class, 'index'])->name('saved.index');
    Route::get('/reports', [ReportDashboardController::class, 'index'])->name('reports.index');
    Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');

    Route::get('/outreach', [OutreachController::class, 'index'])->name('outreach.index');
    Route::post('/outreach/selections', [OutreachController::class, 'storeSelection'])->name('outreach.selections.store');
    Route::delete('/outreach/selections', [OutreachController::class, 'clearSelections'])->name('outreach.selections.clear');
    Route::delete('/outreach/selections/{prospect}', [OutreachController::class, 'destroySelection'])->name('outreach.selections.destroy');
    Route::post('/outreach/generate', [OutreachController::class, 'generate'])->name('outreach.generate');

    Route::get('/prospects/{prospect}', [ProspectController::class, 'show'])->name('prospects.show');
    Route::post('/prospects/{prospect}/report', [ProspectController::class, 'generateReport'])->name('prospects.report');
    Route::post('/prospects/{prospect}/outreach', [ProspectController::class, 'generateOutreach'])->name('prospects.outreach');

    Route::patch('/outreach-emails/{outreachEmail}/sent', [OutreachEmailController::class, 'markSent'])->name('outreach.sent');
    Route::patch('/outreach-emails/{outreachEmail}/response', [OutreachEmailController::class, 'markResponse'])->name('outreach.response');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

require __DIR__.'/auth.php';
