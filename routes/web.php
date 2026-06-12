<?php

use App\Http\Controllers\AgencyBookingSettingsController;
use App\Http\Controllers\ApiQuotaSettingsController;
use App\Http\Controllers\BookingDashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\IgnoredProspectController;
use App\Http\Controllers\NicheAnnotationController;
use App\Http\Controllers\NicheIgnoreController;
use App\Http\Controllers\NicheScanController;
use App\Http\Controllers\NicheScanSampleController;
use App\Http\Controllers\OAuthServerController;
use App\Http\Controllers\OAuthWellKnownController;
use App\Http\Controllers\MarketCpcController;
use App\Http\Controllers\OutreachController;
use App\Http\Controllers\OutreachEmailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProspectBookingController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\ProspectIgnoreController;
use App\Http\Controllers\ProspectListController;
use App\Http\Controllers\ProspectNoteController;
use App\Http\Controllers\ProspectUnsubscribeController;
use App\Http\Controllers\PublicUnsubscribeController;
use App\Http\Controllers\ProspectTagController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicReportBookingController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\PublicSharedListController;
use App\Http\Controllers\ReportDashboardController;
use App\Http\Controllers\SavedProspectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\ConnectedAppsController;
use App\Http\Controllers\Settings\McpKeyController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SharedListController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

if (config('oauth-mcp.enabled', true)) {
    Route::get('.well-known/oauth-protected-resource', [OAuthWellKnownController::class, 'protectedResource']);
    Route::get('.well-known/oauth-authorization-server', [OAuthWellKnownController::class, 'authorizationServer']);
    Route::get('.well-known/jwks.json', [OAuthWellKnownController::class, 'jwks']);
    Route::post('oauth/register', [OAuthServerController::class, 'register']);
    Route::get('oauth/authorize', [OAuthServerController::class, 'showConsent'])->name('oauth.authorize');
    Route::post('oauth/token', [OAuthServerController::class, 'token']);
    Route::post('oauth/revoke', [OAuthServerController::class, 'revoke']);
}

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
Route::get('/s/{token}', [PublicSharedListController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('lists.public');
Route::get('/r/{token}/slots', [PublicReportBookingController::class, 'slots'])
    ->middleware('throttle:60,1')
    ->name('reports.public.slots');
Route::post('/r/{token}/book', [PublicReportBookingController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('reports.public.book');
Route::get('/r/{token}/booking.ics', [PublicReportBookingController::class, 'ics'])
    ->middleware('throttle:60,1')
    ->name('reports.public.booking.ics');
Route::get('/book', [PublicBookingController::class, 'show'])->name('book.index');
Route::get('/unsubscribe', [PublicUnsubscribeController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('unsubscribe');

Route::get('/admin/horizon', function () {
    return redirect('/horizon');
})->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/niches', [NicheScanController::class, 'index'])->name('niches.index');
    Route::post('/niches/{nicheScan}/refresh', [NicheScanController::class, 'refresh'])->name('niches.refresh');
    Route::get('/niches/{nicheScan}/status', [NicheScanController::class, 'status'])->name('niches.status');
    Route::get('/niches/{nicheScan}/sample', [NicheScanSampleController::class, 'show'])->name('niches.sample');
    Route::post('/niches/ignore', [NicheIgnoreController::class, 'store'])->name('niches.ignore.store');
    Route::post('/niches/ignore/remove', [NicheIgnoreController::class, 'destroy'])->name('niches.ignore.destroy');

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::post('/market-cpc/load', [MarketCpcController::class, 'load'])->name('market-cpc.load');
    Route::post('/market-cpc/fetch', [MarketCpcController::class, 'fetch'])->name('market-cpc.fetch');
    Route::get('/searches', [SearchController::class, 'history'])->name('searches.index');
    Route::post('/searches', [SearchController::class, 'store'])->name('searches.store');
    Route::post('/searches/direct', [SearchController::class, 'storeDirectUrl'])->name('searches.store-direct');
    Route::get('/searches/{search}', [SearchController::class, 'show'])->name('searches.show');
    Route::patch('/searches/{search}/cpc', [SearchController::class, 'updateCpc'])->name('searches.cpc.update');
    Route::post('/searches/{search}/cpc/fetch', [SearchController::class, 'fetchCpc'])->name('searches.cpc.fetch');

    Route::get('/saved', [SavedProspectController::class, 'index'])->name('saved.index');

    Route::get('/lists', [ProspectListController::class, 'index'])->name('lists.index');
    Route::get('/lists/browse', [ProspectListController::class, 'browse'])->name('lists.browse');
    Route::post('/lists', [ProspectListController::class, 'store'])->name('lists.store');
    Route::get('/lists/{list}', [ProspectListController::class, 'show'])->name('lists.show');
    Route::patch('/lists/{list}', [ProspectListController::class, 'update'])->name('lists.update');
    Route::delete('/lists/{list}', [ProspectListController::class, 'destroy'])->name('lists.destroy');
    Route::post('/lists/{list}/items', [ProspectListController::class, 'storeItems'])->name('lists.items.store');
    Route::patch('/lists/{list}/items/{item}', [ProspectListController::class, 'updateItem'])->name('lists.items.update');
    Route::delete('/lists/{list}/items/{item}', [ProspectListController::class, 'destroyItem'])->name('lists.items.destroy');
    Route::post('/lists/{list}/share', [ProspectListController::class, 'share'])->name('lists.share');
    Route::delete('/shared-lists/{sharedList}', [SharedListController::class, 'destroy'])->name('shared-lists.destroy');

    Route::get('/niches/annotations', [NicheAnnotationController::class, 'show'])->name('niches.annotations.show');
    Route::post('/niche-notes', [NicheAnnotationController::class, 'storeNote'])->name('niche-notes.store');
    Route::post('/niche-tags', [NicheAnnotationController::class, 'syncTags'])->name('niche-tags.sync');
    Route::post('/prospects/{prospect}/tags', [ProspectTagController::class, 'sync'])->name('prospects.tags.sync');
    Route::get('/ignored', [IgnoredProspectController::class, 'index'])->name('ignored.index');
    Route::delete('/ignored/{ignoredProspect}', [IgnoredProspectController::class, 'destroy'])->name('ignored.destroy');
    Route::get('/reports', [ReportDashboardController::class, 'index'])->name('reports.index');
    Route::get('/bookings', [BookingDashboardController::class, 'index'])->name('bookings.index');
    Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');

    Route::get('/outreach', [OutreachController::class, 'index'])->name('outreach.index');
    Route::post('/outreach/selections', [OutreachController::class, 'storeSelection'])->name('outreach.selections.store');
    Route::delete('/outreach/selections', [OutreachController::class, 'clearSelections'])->name('outreach.selections.clear');
    Route::delete('/outreach/selections/{prospect}', [OutreachController::class, 'destroySelection'])->name('outreach.selections.destroy');
    Route::post('/outreach/generate', [OutreachController::class, 'generate'])->name('outreach.generate');

    Route::get('/prospects/{prospect}', [ProspectController::class, 'show'])->name('prospects.show');
    Route::patch('/prospects/{prospect}', [ProspectController::class, 'update'])->name('prospects.update');
    Route::post('/prospects/{prospect}/notes', [ProspectNoteController::class, 'store'])->name('prospects.notes.store');
    Route::post('/prospects/{prospect}/ignore', [ProspectIgnoreController::class, 'store'])->name('prospects.ignore.store');
    Route::delete('/prospects/{prospect}/ignore', [ProspectIgnoreController::class, 'destroy'])->name('prospects.ignore.destroy');
    Route::post('/prospects/{prospect}/unsubscribe', [ProspectUnsubscribeController::class, 'store'])->name('prospects.unsubscribe.store');
    Route::post('/prospects/{prospect}/niche-scan', [ProspectController::class, 'refreshMarketScan'])->name('prospects.niche-scan');
    Route::post('/prospects/{prospect}/audit', [ProspectController::class, 'reauditSite'])->name('prospects.audit');
    Route::post('/prospects/{prospect}/report', [ProspectController::class, 'generateReport'])->name('prospects.report');
    Route::post('/prospects/{prospect}/outreach', [ProspectController::class, 'generateOutreach'])->name('prospects.outreach');
    Route::post('/prospects/{prospect}/booking/resend-confirmation', [ProspectBookingController::class, 'resendConfirmation'])->name('prospects.booking.resend');

    Route::patch('/outreach-emails/{outreachEmail}/sent', [OutreachEmailController::class, 'markSent'])->name('outreach.sent');
    Route::patch('/outreach-emails/{outreachEmail}/response', [OutreachEmailController::class, 'markResponse'])->name('outreach.response');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/connected-apps', [ConnectedAppsController::class, 'index'])->name('settings.connected-apps.index');
    Route::delete('/settings/connected-apps/{family}', [ConnectedAppsController::class, 'destroy'])->name('settings.connected-apps.destroy');
    Route::delete('/settings/connected-apps', [ConnectedAppsController::class, 'destroyAll'])->name('settings.connected-apps.destroy-all');
    Route::get('/settings/mcp-keys', [McpKeyController::class, 'index'])->name('settings.mcp-keys.index');
    Route::post('/settings/mcp-keys', [McpKeyController::class, 'store'])->name('settings.mcp-keys.store');
    Route::patch('/settings/mcp-keys/{mcpKey}', [McpKeyController::class, 'update'])->name('settings.mcp-keys.update');
    Route::delete('/settings/mcp-keys/{mcpKey}', [McpKeyController::class, 'destroy'])->name('settings.mcp-keys.destroy');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::patch('/settings/api-quotas', [ApiQuotaSettingsController::class, 'update'])->name('settings.api-quotas.update');
    Route::patch('/settings/agency-booking', [AgencyBookingSettingsController::class, 'update'])->name('settings.agency-booking.update');
    Route::post('/settings/agency-booking/test', [AgencyBookingSettingsController::class, 'testConnection'])->name('settings.agency-booking.test');
    Route::post('/settings/niches/scan', [SettingsController::class, 'scanNiches'])->name('settings.niches.scan');
    Route::post('/settings/niches/bootstrap', [SettingsController::class, 'bootstrapNiches'])->name('settings.niches.bootstrap');
});

require __DIR__.'/auth.php';
