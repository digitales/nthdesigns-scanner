<?php

use App\Http\Controllers\OutreachEmailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\SearchController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
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

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::post('/searches', [SearchController::class, 'store'])->name('searches.store');
    Route::get('/searches/{search}', [SearchController::class, 'show'])->name('searches.show');

    Route::get('/prospects/{prospect}', [ProspectController::class, 'show'])->name('prospects.show');
    Route::post('/prospects/{prospect}/report', [ProspectController::class, 'generateReport'])->name('prospects.report');
    Route::post('/prospects/{prospect}/outreach', [ProspectController::class, 'generateOutreach'])->name('prospects.outreach');

    Route::patch('/outreach-emails/{outreachEmail}/sent', [OutreachEmailController::class, 'markSent'])->name('outreach.sent');
    Route::patch('/outreach-emails/{outreachEmail}/response', [OutreachEmailController::class, 'markResponse'])->name('outreach.response');
});

require __DIR__.'/auth.php';
